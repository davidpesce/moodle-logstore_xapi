<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace logstore_xapi\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Backfill the response hash of failed events stored before the hash was added.
 *
 * Queued by the upgrade to 5.3.0. Each run hashes a batch of rows, within a time limit, and
 * queues the task again until every row has a hash. Transform errors stored before 5.3.0 include
 * the event id in their message; it is taken out, as it is for new failures, so the same error in
 * many events is one option in the error report's response filter.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hash_failed_responses extends \core\task\adhoc_task {
    /** @var int Rows read at a time. */
    public $batchsize = 1000;

    /** @var int Seconds a run may take before it leaves the rest to the next run. */
    public $timelimit = 60;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskhashfailedresponses', 'logstore_xapi');
    }

    /**
     * Hash a batch of failed responses, and queue the task again if any are left.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $select = 'responsehash IS NULL AND response IS NOT NULL';
        $timeend = time() + $this->timelimit;
        $hashed = 0;
        do {
            $rows = $DB->get_records_select('logstore_xapi_failed_log', $select, null, 'id', 'id, response', 0, $this->batchsize);
            foreach ($rows as $row) {
                $response = self::remove_transform_event_id($row->response);
                $DB->update_record('logstore_xapi_failed_log', (object) [
                    'id' => $row->id,
                    'response' => $response,
                    'responsehash' => logstore_xapi_hash_response($response),
                ]);
            }
            $batchcount = count($rows);
            $hashed += $batchcount;
        } while ($batchcount === $this->batchsize && time() < $timeend);

        mtrace("Hashed $hashed failed event responses.");

        // Not checked for an existing task: under cron, this task's own row is still in the queue
        // while it runs, and is deleted once it ends, so the check would always find it and the
        // rest would never be hashed.
        if ($DB->record_exists_select('logstore_xapi_failed_log', $select)) {
            \core\task\manager::queue_adhoc_task(new self());
        }
    }

    /**
     * Take the event id out of a transform error stored before 5.3.0.
     *
     * Those were stored as {"transfromerror":"Failed transform for event id #123: message"}.
     *
     * @param string $response The stored response.
     * @return string The response, without the event id when it is a transform error.
     */
    public static function remove_transform_event_id(string $response): string {
        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['transfromerror']) || !is_string($decoded['transfromerror'])) {
            return $response;
        }
        $message = preg_replace('/^Failed transform for event id #[^:]*: /', 'Failed transform: ', $decoded['transfromerror']);
        return $message === $decoded['transfromerror'] ? $response : json_encode(['transfromerror' => $message]);
    }
}
