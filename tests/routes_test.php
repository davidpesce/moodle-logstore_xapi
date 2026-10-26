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

use logstore_xapi\task\emit_task;

/**
 * Unit tests for applying the routes and logguests settings to queued events.
 *
 * Events reach the queue from the historic report and the failed log as well as from
 * capture, so the settings must also apply when the queue is processed.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\log\store::process_events
 */
final class routes_test extends \advanced_testcase {
    /**
     * Queue an event in logstore_xapi_log.
     *
     * @param string $eventname The event class name.
     * @param int $userid The user who triggered the event.
     * @param int $courseid The course the event happened in.
     * @return int The queue row id.
     */
    private function queue_event(string $eventname, int $userid, int $courseid): int {
        global $DB;

        $context = \context_course::instance($courseid);
        return $DB->insert_record('logstore_xapi_log', (object) [
            'eventname' => $eventname,
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => $context->id,
            'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid' => $userid,
            'courseid' => $courseid,
            'timecreated' => time(),
            'type' => XAPI_IMPORT_TYPE_LIVE,
        ]);
    }

    /**
     * Run the emit task, discarding its output.
     *
     * @return void
     */
    private function run_emit_task(): void {
        set_debugging(DEBUG_NONE);
        ob_start();
        (new emit_task())->execute();
        ob_end_clean();
    }

    /**
     * Events whose route is off leave the queue without being sent or recorded as failed.
     *
     * @return void
     */
    public function test_unrouted_events_are_skipped(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        set_config('routes', '\core\event\course_viewed', 'logstore_xapi');

        $this->queue_event('\core\event\course_viewed', $user->id, $course->id);
        $this->queue_event('\core\event\message_sent', $user->id, $course->id);

        $this->run_emit_task();

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        // No LRS is configured, so the routed event fails to load; the other is never attempted.
        $failed = $DB->get_records('logstore_xapi_failed_log');
        $this->assertCount(1, $failed);
        $this->assertEquals('\core\event\course_viewed', reset($failed)->eventname);
        $this->assertEquals(0, $DB->count_records('logstore_xapi_sent_log'));
    }

    /**
     * Guest events are skipped when logguests is off.
     *
     * @return void
     */
    public function test_guest_events_are_skipped_without_logguests(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('routes', '\core\event\course_viewed', 'logstore_xapi');
        set_config('logguests', 0, 'logstore_xapi');

        $this->queue_event('\core\event\course_viewed', $CFG->siteguest, $course->id);

        $this->run_emit_task();

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
    }

    /**
     * Guest events are processed when logguests is on.
     *
     * @return void
     */
    public function test_guest_events_are_processed_with_logguests(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        set_config('routes', '\core\event\course_viewed', 'logstore_xapi');
        set_config('logguests', 1, 'logstore_xapi');

        $this->queue_event('\core\event\course_viewed', $CFG->siteguest, $course->id);

        $this->run_emit_task();

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log'));
    }
}
