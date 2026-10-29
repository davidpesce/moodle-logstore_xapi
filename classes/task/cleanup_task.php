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
 * Remove rows the plugin no longer needs from its log tables.
 *
 * - Sent log rows record which standard log rows have been sent, so the historic report can leave
 *   them out. Once the standard log has deleted its rows (its "Keep logs for" setting),
 *   the sent log rows for them are deleted too.
 * - Only the newest row of the notification log is read, so the older ones are deleted.
 * - Failed events older than "Keep failed events for" are deleted. They are kept by default.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_task extends \core\task\scheduled_task {
    /** @var int Rows deleted at a time. */
    public $chunksize = 10000;

    /** @var int Seconds a run may take before it leaves the rest to the next run. */
    public $timelimit = 5 * MINSECS;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcleanup', 'logstore_xapi');
    }

    /**
     * Delete the rows.
     *
     * @return void
     */
    public function execute() {
        global $DB;
        $timeend = time() + $this->timelimit;

        // Standard log ids only grow, and the standard log deletes its oldest rows, so every sent
        // log row below its oldest row is for a row that is gone; with an empty standard log, all
        // of them are.
        $oldest = (int) $DB->get_field_sql('SELECT MIN(id) FROM {logstore_standard_log}');
        $count = $oldest > 0
            ? $this->delete_in_chunks('logstore_xapi_sent_log', 'logstorestandardlogid < ?', [$oldest], $timeend)
            : $this->delete_in_chunks('logstore_xapi_sent_log', '1 = 1', [], $timeend);
        mtrace("Deleted $count sent log row(s) for standard log rows that are gone.");

        // The notification task only reads the newest failed event id it has notified about.
        $newest = (int) $DB->get_field_sql('SELECT MAX(failedlogid) FROM {logstore_xapi_notif_sent_log}');
        $this->delete_in_chunks('logstore_xapi_notif_sent_log', 'failedlogid < ?', [$newest], $timeend);

        $lifetime = (int) get_config('logstore_xapi', 'failedloglifetime');
        if ($lifetime > 0) {
            $before = time() - $lifetime * DAYSECS;
            $count = $this->delete_in_chunks('logstore_xapi_failed_log', 'timecreated < ?', [$before], $timeend);
            mtrace("Deleted $count failed event(s) from before $lifetime day(s) ago.");
        }
    }

    /**
     * Delete the rows of a table that match, a chunk at a time, until the time is up.
     *
     * @param string $table The table.
     * @param string $select The condition.
     * @param array $params Its parameters.
     * @param int $timeend When to stop.
     * @return int The number of rows deleted.
     */
    private function delete_in_chunks(string $table, string $select, array $params, int $timeend): int {
        global $DB;
        $deleted = 0;
        do {
            $ids = array_keys($DB->get_records_select($table, $select, $params, 'id', 'id', 0, $this->chunksize));
            $DB->delete_records_list($table, 'id', $ids);
            $deleted += count($ids);
        } while (count($ids) === $this->chunksize && time() < $timeend);
        return $deleted;
    }
}
