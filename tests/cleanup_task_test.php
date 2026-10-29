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

use logstore_xapi\task\cleanup_task;

/**
 * Tests for removing rows the plugin no longer needs from its log tables.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\task\cleanup_task
 */
final class cleanup_task_test extends \advanced_testcase {
    /**
     * Run the task.
     *
     * @return void
     */
    private function run_task(): void {
        ob_start();
        (new cleanup_task())->execute();
        ob_end_clean();
    }

    /**
     * Add a standard log row.
     *
     * @return int Its id.
     */
    private function add_standard_log_row(): int {
        global $DB;
        return $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\\core\\event\\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => 1,
            'contextlevel' => CONTEXT_SYSTEM,
            'contextinstanceid' => 0,
            'userid' => 2,
            'anonymous' => 0,
            'timecreated' => time(),
        ]);
    }

    /**
     * Sent log rows for standard log rows that are gone are deleted.
     *
     * @return void
     */
    public function test_sent_log_rows_for_deleted_standard_log_rows_are_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('logstore_standard_log');
        $gone = $this->add_standard_log_row();
        $kept = $this->add_standard_log_row();
        foreach ([$gone, $kept] as $id) {
            $DB->insert_record('logstore_xapi_sent_log', (object) [
                'logstorestandardlogid' => $id,
                'type' => 0,
                'timecreated' => time(),
            ]);
        }
        $DB->delete_records('logstore_standard_log', ['id' => $gone]);

        $this->run_task();

        $sent = $DB->get_fieldset_select('logstore_xapi_sent_log', 'logstorestandardlogid', '1 = 1');
        $this->assertEquals([$kept], array_map('intval', $sent));
    }

    /**
     * Only the notification log rows for the newest notified failure are kept.
     *
     * @return void
     */
    public function test_only_the_newest_notification_is_kept(): void {
        global $DB;
        $this->resetAfterTest();
        foreach ([3, 7, 7] as $failedlogid) {
            $DB->insert_record('logstore_xapi_notif_sent_log', (object) ['failedlogid' => $failedlogid, 'timecreated' => time()]);
        }

        $this->run_task();

        $notified = $DB->get_fieldset_select('logstore_xapi_notif_sent_log', 'failedlogid', '1 = 1');
        $this->assertEquals([7, 7], array_map('intval', $notified));
    }

    /**
     * Failed events are kept by default, and deleted once older than "Keep failed events for".
     *
     * @return void
     */
    public function test_failed_events_are_deleted_after_their_lifetime(): void {
        global $DB;
        $this->resetAfterTest();
        foreach ([time() - 10 * DAYSECS, time() - DAYSECS] as $timecreated) {
            $DB->insert_record('logstore_xapi_failed_log', (object) [
                'eventname' => '\\core\\event\\course_viewed',
                'component' => 'core',
                'action' => 'viewed',
                'target' => 'course',
                'crud' => 'r',
                'edulevel' => 2,
                'contextid' => 1,
                'contextlevel' => CONTEXT_SYSTEM,
                'contextinstanceid' => 0,
                'userid' => 2,
                'anonymous' => 0,
                'timecreated' => $timecreated,
            ]);
        }

        $this->run_task();
        $this->assertEquals(2, $DB->count_records('logstore_xapi_failed_log'));

        set_config('failedloglifetime', 5, 'logstore_xapi');
        $this->run_task();
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log'));
    }
}
