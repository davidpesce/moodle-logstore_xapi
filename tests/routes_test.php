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

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use logstore_xapi\log\store;
use logstore_xapi\task\emit_task;

/**
 * Unit tests for applying the routes and logguests settings and the anonymous flag to queued events.
 *
 * Events reach the queue from the historic report and the failed log as well as from
 * capture, so the settings must also apply when the queue is processed.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\log\store::process_events
 * @covers    \logstore_xapi\log\store::is_event_ignored
 */
final class routes_test extends \advanced_testcase {
    /**
     * Stand in for an LRS that rejects every batch as bad data (400), so that an event that is
     * sent ends up in the failed log, and one that is skipped doesn't.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('endpoint', 'https://lrs.example.org/xapi/', 'logstore_xapi');
        store::$mocklrs = function () {
            return Create::promiseFor(new Response(400, [], 'Invalid statement'));
        };
    }

    /**
     * Stop standing in for the LRS.
     *
     * @return void
     */
    protected function tearDown(): void {
        store::$mocklrs = null;
        parent::tearDown();
    }

    /**
     * Queue an event in logstore_xapi_log.
     *
     * @param string $eventname The event class name.
     * @param int $userid The user who triggered the event.
     * @param int $courseid The course the event happened in.
     * @param int $anonymous The event's anonymous flag.
     * @param string $origin Where the event came from: web, cli, ws...
     * @return int The queue row id.
     */
    private function queue_event(
        string $eventname,
        int $userid,
        int $courseid,
        int $anonymous = 0,
        string $origin = 'web'
    ): int {
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
            'anonymous' => $anonymous,
            'origin' => $origin,
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
        // The LRS rejects the routed event, so it fails to load; the other is never attempted.
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

    /**
     * Events from someone who isn't logged in (user id 0) are skipped when logguests is off, as
     * guest events are, except those logged by CLI scripts.
     *
     * @return void
     */
    public function test_logged_out_events_are_skipped_without_logguests(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('routes', '\core\event\course_viewed', 'logstore_xapi');
        set_config('logguests', 0, 'logstore_xapi');

        $this->queue_event('\core\event\course_viewed', 0, SITEID);
        $this->queue_event('\core\event\course_viewed', 0, SITEID, 0, 'cli');

        $this->run_emit_task();

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        // The LRS rejects the CLI event, so it fails to load; the web one is never attempted.
        $failed = $DB->get_records('logstore_xapi_failed_log');
        $this->assertCount(1, $failed);
        $this->assertEquals('cli', reset($failed)->origin);
    }

    /**
     * Events from someone who isn't logged in are transformed when logguests is on, and fail
     * only to load.
     *
     * @return void
     */
    public function test_logged_out_events_are_processed_with_logguests(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('routes', '\core\event\course_viewed', 'logstore_xapi');
        set_config('logguests', 1, 'logstore_xapi');

        $this->queue_event('\core\event\course_viewed', 0, SITEID);

        $this->run_emit_task();

        $failed = $DB->get_records('logstore_xapi_failed_log');
        $this->assertCount(1, $failed);
        $this->assertNotEquals(XAPI_REPORT_ERRORTYPE_TRANSFORM, reset($failed)->errortype);
    }

    /**
     * Events triggered by a guest, or by someone who isn't logged in, aren't captured when
     * logguests is off.
     *
     * @return void
     */
    public function test_logged_out_events_are_not_captured_without_logguests(): void {
        $this->resetAfterTest();

        set_config('routes', '\core\event\course_viewed', 'logstore_xapi');
        $store = new class (get_log_manager()) extends \logstore_xapi\log\store {
            /**
             * Whether the store ignores an event.
             *
             * @param \core\event\base $event The event.
             * @return bool
             */
            public function ignores(\core\event\base $event): bool {
                return $this->is_event_ignored($event);
            }
        };
        $context = \context_course::instance(SITEID);
        $user = $this->getDataGenerator()->create_user();

        foreach ([0 => true, 1 => false] as $logguests => $ignored) {
            set_config('logguests', $logguests, 'logstore_xapi');

            $this->setUser(0);
            $this->assertSame($ignored, $store->ignores(\core\event\course_viewed::create(['context' => $context])));
            $this->setGuestUser();
            $this->assertSame($ignored, $store->ignores(\core\event\course_viewed::create(['context' => $context])));
            $this->setUser($user);
            $this->assertFalse($store->ignores(\core\event\course_viewed::create(['context' => $context])));
        }
    }

    /**
     * Anonymous events (such as assignment events under blind marking) are skipped when the
     * anonymousevents setting is "Don't send".
     *
     * @return void
     */
    public function test_anonymous_events_are_skipped_when_set(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        set_config('routes', '\mod_assign\event\submission_graded', 'logstore_xapi');
        set_config('anonymousevents', XAPI_ANONYMOUS_EVENTS_SKIP, 'logstore_xapi');

        $this->queue_event('\mod_assign\event\submission_graded', $user->id, $course->id, 1);

        $this->run_emit_task();

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_sent_log'));
    }

    /**
     * By default anonymous events are processed (and sent without the hidden user's identity).
     *
     * @return void
     */
    public function test_anonymous_events_are_processed_by_default(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        set_config('routes', '\mod_assign\event\submission_graded', 'logstore_xapi');

        $this->queue_event('\mod_assign\event\submission_graded', $user->id, $course->id, 1);

        $this->run_emit_task();

        // The queued row has no grade behind it, so it fails to transform: it was attempted.
        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log'));
    }
}
