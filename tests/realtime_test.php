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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for real-time mode (background mode off), where events are sent in the page request.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\log\store::insert_event_entries
 */
final class realtime_test extends \advanced_testcase {
    /**
     * Turn real-time mode on, and route course_viewed.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('backgroundmode', 0, 'logstore_xapi');
        set_config('endpoint', 'https://lrs.example.org/xapi/', 'logstore_xapi');
        set_config('routes', '\\core\\event\\course_viewed', 'logstore_xapi');
    }

    /**
     * Stop standing in for the LRS.
     *
     * @return void
     */
    protected function tearDown(): void {
        store::$mocklrs = null;
        \cache::make('logstore_xapi', 'senderror')->purge();
        parent::tearDown();
    }

    /**
     * Capture a course view, as the log manager does when the buffer is flushed, with the LRS
     * answering this status.
     *
     * @param int $status The HTTP status the LRS answers.
     * @return void
     */
    private function capture_course_view(int $status): void {
        store::$mocklrs = function () use ($status) {
            return Create::promiseFor(new Response($status, [], 'Error ' . $status));
        };
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $store = new class (get_log_manager()) extends store {
            /**
             * Capture events as the log manager does when the buffer is flushed.
             *
             * @param array $events Event data.
             * @return void
             */
            public function capture(array $events): void {
                $this->insert_event_entries($events);
            }
        };
        $store->capture([[
            'eventname' => '\core\event\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'objecttable' => null,
            'objectid' => null,
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => $context->id,
            'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid' => $user->id,
            'courseid' => $course->id,
            'relateduserid' => null,
            'anonymous' => 0,
            'other' => 'N;',
            'timecreated' => time(),
        ]]);
        $this->resetDebugging();
    }

    /**
     * When the LRS can't take the event now, it goes to the queue for the emit task, not to the
     * failed log.
     *
     * @return void
     */
    public function test_unavailable_lrs_queues_the_event(): void {
        global $DB;

        $this->capture_course_view(503);

        $queued = $DB->get_records('logstore_xapi_log');
        $this->assertCount(1, $queued);
        $this->assertEquals(XAPI_IMPORT_TYPE_LIVE, reset($queued)->type);
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertSame(503, \cache::make('logstore_xapi', 'senderror')->get('last')->code);
    }

    /**
     * An event the LRS rejects goes to the failed log, as before.
     *
     * @return void
     */
    public function test_rejected_event_is_failed(): void {
        global $DB;

        $this->capture_course_view(400);

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log'));
    }
}
