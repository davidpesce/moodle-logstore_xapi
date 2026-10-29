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

namespace logstore_xapi;

use logstore_xapi\task\resend_task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for queueing the events a report's filters match, to be sent again.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\task\resend_task
 * @covers    \logstore_xapi\log\moveback
 * @covers    ::logstore_xapi_get_report_where
 */
final class resend_task_test extends \advanced_testcase {
    /**
     * Add course views to a table: the failed log or the standard log.
     *
     * @param string $table The table.
     * @param int $count The number of events.
     * @param array $extra More fields for each row.
     * @return int[] The ids, oldest first.
     */
    private function add_course_views(string $table, int $count, array $extra = []): array {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $DB->insert_record($table, (object) ([
                'eventname' => '\\core\\event\\course_viewed',
                'component' => 'core',
                'action' => 'viewed',
                'target' => 'course',
                'crud' => 'r',
                'edulevel' => 2,
                'contextid' => $context->id,
                'contextlevel' => $context->contextlevel,
                'contextinstanceid' => $context->instanceid,
                'userid' => $user->id,
                'courseid' => $course->id,
                'anonymous' => 0,
                'other' => 'N;',
                'timecreated' => time() - $count + $i,
                'origin' => 'web',
            ] + $extra));
        }
        return $ids;
    }

    /**
     * Run a resend task.
     *
     * @param resend_task $task The task.
     * @return void
     */
    private function run_task(resend_task $task): void {
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * Make a resend task, as "Resend" on a report does.
     *
     * @param int $reportid The report.
     * @param array $filters The report's filters.
     * @return resend_task
     */
    private function make_task(int $reportid, array $filters): resend_task {
        $task = new resend_task();
        $task->set_custom_data([
            'reportid' => $reportid,
            'filters' => (object) $filters,
            'eventnames' => ['\\core\\event\\course_viewed'],
        ]);
        return $task;
    }

    /**
     * The failed events the error report's filters match are queued as failed-type events, a
     * chunk at a time, and the others stay in the failed log.
     *
     * @return void
     */
    public function test_failed_events_matching_the_filters_are_queued(): void {
        global $DB;
        $this->resetAfterTest();
        $matching = $this->add_course_views('logstore_xapi_failed_log', 5, ['errortype' => 500, 'type' => 0]);
        $other = $this->add_course_views('logstore_xapi_failed_log', 2, ['errortype' => 400, 'type' => 0]);

        $task = $this->make_task(XAPI_REPORT_ID_ERROR, ['errortype' => 500]);
        $task->chunksize = 2;
        $this->run_task($task);

        $this->assertEquals(5, $DB->count_records('logstore_xapi_log', ['type' => XAPI_IMPORT_TYPE_FAILED]));
        $this->assertEquals($other, array_map('intval', array_keys($DB->get_records('logstore_xapi_failed_log', null, 'id'))));
        $this->assertCount(5, $matching);
    }

    /**
     * A run that runs out of time queues the task again, to carry on after the last event it
     * queued.
     *
     * @return void
     */
    public function test_task_carries_on_in_another_run(): void {
        global $DB;
        $this->resetAfterTest();
        $this->add_course_views('logstore_xapi_failed_log', 5, ['errortype' => 500, 'type' => 0]);

        $task = $this->make_task(XAPI_REPORT_ID_ERROR, []);
        $task->chunksize = 2;
        $task->timelimit = 0;
        $this->run_task($task);

        $this->assertEquals(2, $DB->count_records('logstore_xapi_log'));
        $next = \core\task\manager::get_adhoc_tasks(resend_task::class);
        $this->assertCount(1, $next);
        $next = reset($next);
        $this->assertNotEmpty($next->get_custom_data()->lastid);

        $next->chunksize = 10;
        $this->run_task($next);
        $this->assertEquals(5, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
    }

    /**
     * Standard log rows the historic report's filters match are queued as historic events, once,
     * and rows already sent are left out.
     *
     * @return void
     */
    public function test_historic_events_are_queued(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('routes', '\\core\\event\\course_viewed', 'logstore_xapi');
        $ids = $this->add_course_views('logstore_standard_log', 4);
        $DB->insert_record('logstore_xapi_sent_log', (object) [
            'logstorestandardlogid' => $ids[0],
            'type' => XAPI_IMPORT_TYPE_LIVE,
            'timecreated' => time(),
        ]);

        $this->run_task($this->make_task(XAPI_REPORT_ID_HISTORIC, ['datefrom' => time() - DAYSECS]));
        $this->run_task($this->make_task(XAPI_REPORT_ID_HISTORIC, ['datefrom' => time() - DAYSECS]));

        $queued = $DB->get_records('logstore_xapi_log', null, 'logstorestandardlogid');
        $this->assertEquals(array_slice($ids, 1), array_map('intval', array_column($queued, 'logstorestandardlogid')));
        foreach ($queued as $event) {
            $this->assertEquals(XAPI_IMPORT_TYPE_HISTORIC, $event->type);
        }
    }
}
