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

use logstore_xapi\log\store;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for matching queued events to their logstore_standard_log rows, and for the sent log.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\log\store
 * @covers    ::logstore_xapi_save_sent_events
 */
final class standard_log_ids_test extends \advanced_testcase {
    /** @var \stdClass The user who views the course. */
    private $user;

    /** @var \stdClass The course. */
    private $course;

    /**
     * Route course_viewed, and create the user and course.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('routes', '\\core\\event\\course_viewed', 'logstore_xapi');
        $this->user = $this->getDataGenerator()->create_user();
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * A store whose loader accepts every statement, and that can capture events directly.
     *
     * @return store
     */
    private function get_store(): store {
        return new class (get_log_manager()) extends store {
            /**
             * The handler config, with a loader that accepts every statement.
             *
             * @return array
             */
            protected function get_handler_config() {
                $config = parent::get_handler_config();
                $config['loader']['loader'] = 'none';
                return $config;
            }

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
    }

    /**
     * Course viewed event data, as the event's get_data() returns it.
     *
     * @param int $timecreated The time of the event.
     * @return array
     */
    private function course_view(int $timecreated): array {
        $context = \context_course::instance($this->course->id);
        return [
            'eventname' => '\\core\\event\\course_viewed',
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
            'userid' => $this->user->id,
            'courseid' => $this->course->id,
            'relateduserid' => null,
            'anonymous' => 0,
            'other' => 'N;',
            'timecreated' => $timecreated,
        ];
    }

    /**
     * Log events in the standard log.
     *
     * @param int[] $times The time of each event.
     * @return int[] The logstore_standard_log ids.
     */
    private function log_standard(array $times): array {
        global $DB;
        return array_map(function ($time) use ($DB) {
            return $DB->insert_record('logstore_standard_log', (object) $this->course_view($time));
        }, $times);
    }

    /**
     * Queue events for sending.
     *
     * @param int[] $times The time of each event.
     * @param int $type The import type.
     * @param int|null $standardid The standard log id already known, as for historic events.
     * @return void
     */
    private function queue(array $times, int $type = XAPI_IMPORT_TYPE_LIVE, ?int $standardid = null): void {
        global $DB;
        foreach ($times as $time) {
            $DB->insert_record('logstore_xapi_log', (object) ($this->course_view($time) + [
                'type' => $type,
                'logstorestandardlogid' => $standardid,
            ]));
        }
    }

    /**
     * Send the queued events of a type through the store.
     *
     * @param int $type The import type.
     * @return array The loaded events.
     */
    private function send(int $type = XAPI_IMPORT_TYPE_LIVE): array {
        global $DB;
        $events = $DB->get_records('logstore_xapi_log', ['type' => $type]);
        $loaded = $this->get_store()->process_events($events, $type);
        foreach ($loaded as $event) {
            $this->assertTrue($event['loaded']);
        }
        return $loaded;
    }

    /**
     * The logstore_standard_log ids recorded in the sent log.
     *
     * @return int[] The ids, in order.
     */
    private function get_sent_ids(): array {
        global $DB;
        $ids = array_map('intval', $DB->get_fieldset_select('logstore_xapi_sent_log', 'logstorestandardlogid', '1 = 1'));
        sort($ids);
        return $ids;
    }

    /**
     * Capturing an event in background mode doesn't look up its standard log row.
     *
     * @return void
     */
    public function test_capture_leaves_the_id_to_the_task(): void {
        global $DB;
        set_config('backgroundmode', 1, 'logstore_xapi');
        $this->log_standard([1000]);

        $this->get_store()->capture([$this->course_view(1000)]);

        $queued = $DB->get_records('logstore_xapi_log');
        $this->assertCount(1, $queued);
        $this->assertNull(reset($queued)->logstorestandardlogid);
        $this->assertEquals(XAPI_IMPORT_TYPE_LIVE, reset($queued)->type);
    }

    /**
     * Each queued event is matched to its own standard log row when it is sent, including
     * identical events in the same second, and each row is recorded as sent once.
     *
     * @return void
     */
    public function test_ids_are_found_when_sent(): void {
        $standardids = $this->log_standard([1000, 1000, 1001]);
        $this->queue([1000, 1000, 1001]);

        $loaded = $this->send();

        $ids = array_map(function ($event) {
            return (int) $event['event']->logstorestandardlogid;
        }, $loaded);
        sort($ids);
        $this->assertEquals($standardids, $ids);
        $this->assertEquals($standardids, $this->get_sent_ids());
    }

    /**
     * Identical events in the same second sent in different batches of one run still get their
     * own rows: the store remembers the rows it has given out.
     *
     * @return void
     */
    public function test_ids_are_distinct_across_batches(): void {
        global $DB;
        $standardids = $this->log_standard([1000, 1000, 1000]);
        $this->queue([1000, 1000, 1000]);
        $store = $this->get_store();

        $ids = [];
        foreach ($DB->get_records('logstore_xapi_log', ['type' => XAPI_IMPORT_TYPE_LIVE], 'id') as $event) {
            $loaded = $store->process_events([$event]);
            $ids[] = (int) $loaded[0]['event']->logstorestandardlogid;
        }
        sort($ids);

        $this->assertEquals($standardids, $ids);
    }

    /**
     * Identical events in the same second sent in different runs (by different stores) still get
     * their own rows: a row already in the sent log isn't given out again.
     *
     * @return void
     */
    public function test_ids_are_distinct_across_runs(): void {
        global $DB;
        $standardids = $this->log_standard([1000, 1000]);

        foreach ([1, 2] as $run) {
            $DB->delete_records('logstore_xapi_log');
            $this->queue([1000]);
            $this->send();
        }

        $this->assertEquals($standardids, $this->get_sent_ids());
    }

    /**
     * A historic event keeps the standard log id it was queued with, even when an identical
     * event was logged in the same second.
     *
     * @return void
     */
    public function test_known_ids_are_kept(): void {
        [$first] = $this->log_standard([1000, 1000]);
        $this->queue([1000], XAPI_IMPORT_TYPE_HISTORIC, $first);

        $this->send(XAPI_IMPORT_TYPE_HISTORIC);

        $this->assertEquals([$first], $this->get_sent_ids());
    }

    /**
     * Sending an event again doesn't record it twice, and an event with no standard log row
     * isn't recorded.
     *
     * @return void
     */
    public function test_sent_log_records_each_row_once(): void {
        global $DB;
        $standardids = $this->log_standard([1000]);
        $this->queue([1000, 2000]);

        $loaded = $this->send();
        logstore_xapi_save_sent_events($loaded);

        $this->assertEquals($standardids, $this->get_sent_ids());
        $this->assertEquals(XAPI_IMPORT_TYPE_LIVE, $DB->get_field('logstore_xapi_sent_log', 'type', []));
    }
}
