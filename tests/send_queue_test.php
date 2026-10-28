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
 * Tests for sending the queue batch after batch in one run.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_send_queued_events
 * @covers    ::logstore_xapi_extract_events
 */
final class send_queue_test extends \advanced_testcase {
    /** @var int[] The number of statements in each request the LRS received. */
    private $requests = [];

    /**
     * Route course_viewed and point the plugin at the mock LRS.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * Make the LRS answer each request in turn with these statuses, recording each request.
     *
     * @param int[] $statuses The status for each request; the last one repeats.
     * @param float $seconds How long each answer takes.
     * @return void
     */
    private function lrs_answers(array $statuses, float $seconds = 0): void {
        store::$mocklrs = function ($request) use (&$statuses, $seconds) {
            $this->requests[] = count(json_decode((string) $request->getBody(), true));
            usleep((int) ($seconds * 1000000));
            $status = count($statuses) > 1 ? array_shift($statuses) : $statuses[0];
            return Create::promiseFor(new Response($status, [], $status === 200 ? '[]' : 'Service Unavailable'));
        };
    }

    /**
     * Queue course views.
     *
     * @param int $count The number of events.
     * @return int[] The queue ids, oldest first.
     */
    private function queue(int $count): array {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = $DB->insert_record('logstore_xapi_log', (object) [
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
                'timecreated' => time() - $count + $i,
                'type' => XAPI_IMPORT_TYPE_LIVE,
            ]);
        }
        return $ids;
    }

    /**
     * Send the live queue, as the emit task does.
     *
     * @param store|null $store The store, or null for the plugin's.
     * @return string The task output.
     */
    private function send(?store $store = null): string {
        ob_start();
        logstore_xapi_send_queued_events($store ?? new store(get_log_manager()), XAPI_IMPORT_TYPE_LIVE, 3);
        $this->resetDebugging();
        return ob_get_clean();
    }

    /**
     * One run sends the whole queue, batch after batch.
     *
     * @return void
     */
    public function test_whole_queue_is_sent_in_one_run(): void {
        global $DB;
        $this->queue(7);
        $this->lrs_answers([200]);

        $output = $this->send();

        $this->assertSame([3, 3, 1], $this->requests);
        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertStringContainsString('7 event(s) have been successfully processed', $output);
    }

    /**
     * The oldest events are sent first, and the run stops when the LRS can't take statements.
     *
     * @return void
     */
    public function test_oldest_first_and_stop_when_the_lrs_is_unavailable(): void {
        global $DB;
        $ids = $this->queue(7);
        $this->lrs_answers([200, 503]);

        $output = $this->send();

        $this->assertSame([3, 3], $this->requests);
        $this->assertSame(array_slice($ids, 3), array_map('intval', array_keys($DB->get_records('logstore_xapi_log', null, 'id'))));
        $this->assertStringContainsString('3 event(s) have been successfully processed', $output);
        $this->assertStringContainsString('3 event(s) left in the queue to send later: HTTP 503: Service Unavailable', $output);
    }

    /**
     * The run stops once the time limit has passed.
     *
     * @return void
     */
    public function test_time_limit(): void {
        global $DB;
        set_config('maxruntime', 1, 'logstore_xapi');
        $this->queue(7);
        $this->lrs_answers([200], 1.1);

        $this->send();

        $this->assertSame([3], $this->requests);
        $this->assertEquals(4, $DB->count_records('logstore_xapi_log'));
    }

    /**
     * A batch that removes nothing from the queue ends the run, instead of sending it again.
     *
     * @return void
     */
    public function test_stops_when_nothing_is_removed(): void {
        global $DB;
        set_config('maxruntime', 30, 'logstore_xapi');
        $this->queue(2);
        $store = new class (get_log_manager()) extends store {
            /** @var int The number of times events were processed. */
            public $runs = 0;

            /**
             * Process nothing, as when the whole handler fails.
             *
             * @param array $events Event data.
             * @param int $eventtype The import type.
             * @return array
             */
            public function process_events(array $events, $eventtype = XAPI_IMPORT_TYPE_LIVE) {
                $this->runs++;
                return [];
            }
        };

        $this->send($store);

        $this->assertSame(1, $store->runs);
        $this->assertEquals(2, $DB->count_records('logstore_xapi_log'));
    }
}
