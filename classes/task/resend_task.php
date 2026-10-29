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

use logstore_xapi\log\moveback;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Queue the events matched by the error or historic report's filters, to be sent again.
 *
 * Queued by "Resend" on either report, with the report's filters. Each run queues the matching
 * events a chunk at a time, oldest first, within a time limit, and queues the task again to carry
 * on from the last event it queued.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resend_task extends \core\task\adhoc_task {
    /** @var int Events queued at a time. */
    public $chunksize = 1000;

    /** @var int Seconds a run may take before it leaves the rest to the next run. */
    public $timelimit = 60;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskresend', 'logstore_xapi');
    }

    /**
     * Queue the matching events, chunk after chunk.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        $reportid = (int) $data->reportid;
        $historic = $reportid == XAPI_REPORT_ID_HISTORIC;
        $table = $historic ? XAPI_REPORT_SOURCE_HISTORICAL : XAPI_REPORT_SOURCE_FAILED;
        [$where, $params] = logstore_xapi_get_report_where($reportid, (object) $data->filters, (array) $data->eventnames);

        $lastid = (int) ($data->lastid ?? 0);
        $timeend = time() + $this->timelimit;
        $queued = 0;
        do {
            $ids = array_keys($DB->get_records_sql(
                "SELECT x.id
                   FROM {{$table}} x
              LEFT JOIN {user} u ON u.id = x.userid
                  WHERE $where AND x.id > :resendlastid
               ORDER BY x.id",
                $params + ['resendlastid' => $lastid],
                0,
                $this->chunksize
            ));
            if (!empty($ids)) {
                (new moveback($ids, $historic))->execute();
                $lastid = (int) end($ids);
                $queued += count($ids);
            }
        } while (count($ids) === $this->chunksize && time() < $timeend);

        mtrace("Queued $queued event(s) to be sent again.");

        if (count($ids) === $this->chunksize) {
            $task = new self();
            $task->set_custom_data(['lastid' => $lastid] + (array) $data);
            \core\task\manager::queue_adhoc_task($task);
        }
    }
}
