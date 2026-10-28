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
use logstore_xapi\log\moveback;
use logstore_xapi\log\store;
use logstore_xapi\task\emit_task;
use logstore_xapi\task\failed_task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for queued events that fail to send, and for moving events back to the queue.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\task\emit_task
 * @covers    \logstore_xapi\task\failed_task
 * @covers    \logstore_xapi\log\moveback
 * @covers    ::logstore_xapi_record_retry_events
 */
final class failed_events_test extends \advanced_testcase {
    /**
     * Point the plugin at an LRS that rejects every batch as bad data (400), and route
     * course_viewed.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        set_config('endpoint', 'https://lrs.example.org/xapi/', 'logstore_xapi');
        set_config('username', 'user', 'logstore_xapi');
        set_config('password', 'pass', 'logstore_xapi');
        set_config('routes', '\\core\\event\\course_viewed', 'logstore_xapi');
        $this->lrs_answers(400, 'Invalid statement');
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
     * Make the LRS answer every request with this status.
     *
     * @param int $status The HTTP status.
     * @param string $body The response body.
     * @return void
     */
    private function lrs_answers(int $status, string $body = ''): void {
        store::$mocklrs = function () use ($status, $body) {
            return Create::promiseFor(new Response($status, [], $body));
        };
    }

    /**
     * Add course_viewed events to the standard log.
     *
     * @param int $count The number of events.
     * @return int[] The logstore_standard_log ids.
     */
    private function add_course_views(int $count): array {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $record = (object) [
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
            ];
            $ids[] = $DB->insert_record('logstore_standard_log', $record);
        }
        return $ids;
    }

    /**
     * Queue standard log events for sending, as the logstore does in background mode.
     *
     * @param int[] $standardids The logstore_standard_log ids.
     * @return void
     */
    private function queue_events(array $standardids): void {
        global $DB;

        foreach ($DB->get_records_list('logstore_standard_log', 'id', $standardids) as $record) {
            $record->logstorestandardlogid = $record->id;
            $record->type = XAPI_IMPORT_TYPE_LIVE;
            unset($record->id);
            $DB->insert_record('logstore_xapi_log', $record);
        }
    }

    /**
     * Run a scheduled task, discarding its mtrace() output and the debugging() calls the
     * loader makes for a failed batch.
     *
     * @param \core\task\scheduled_task $task The task.
     * @return void
     */
    private function run_task(\core\task\scheduled_task $task): void {
        ob_start();
        $task->execute();
        ob_end_clean();

        $messages = array_map(function ($debug) {
            return $debug->message;
        }, $this->getDebuggingMessages());
        $this->assertNotEmpty(preg_grep('/^Failed load batch/', $messages));
        $this->resetDebugging();
    }

    /**
     * A batch the LRS can't receive leaves the queue and is kept in the failed log, with the
     * error, and nothing is recorded as sent.
     *
     * @return void
     */
    public function test_failed_batch_moves_to_failed_log(): void {
        global $DB;

        $standardids = $this->add_course_views(3);
        $this->queue_events($standardids);

        $this->run_task(new emit_task());

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_sent_log'));

        $failed = $DB->get_records('logstore_xapi_failed_log', null, 'logstorestandardlogid');
        $this->assertCount(3, $failed);
        $this->assertEquals($standardids, array_values(array_column($failed, 'logstorestandardlogid')));
        foreach ($failed as $event) {
            $this->assertEquals(XAPI_IMPORT_TYPE_LIVE, $event->type);
            $this->assertEquals(400, $event->errortype);
            $this->assertSame('Invalid statement', $event->response);
        }
    }

    /**
     * An LRS that can't be reached leaves the batch in the queue, with nothing in the failed log.
     *
     * @return void
     */
    public function test_unreachable_lrs_keeps_the_queue(): void {
        global $DB;
        store::$mocklrs = null;
        // Nothing listens on port 1, so the connection is refused at once.
        set_config('endpoint', 'http://127.0.0.1:1', 'logstore_xapi');
        $this->queue_events($this->add_course_views(3));

        ob_start();
        (new emit_task())->execute();
        $output = ob_get_clean();
        $this->resetDebugging();

        $this->assertEquals(3, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertStringContainsString('3 event(s) left in the queue to send later: cURL error: ', $output);
    }

    /**
     * While the LRS is down (5xx), refuses the credentials or isn't at the endpoint, the batch
     * stays in the queue, and the last error is kept for the status report. Once the LRS works
     * again, the batch is sent and the error cleared.
     *
     * @return void
     */
    public function test_unavailable_lrs_keeps_the_queue_until_it_works(): void {
        global $DB;
        $this->queue_events($this->add_course_views(3));
        $cache = \cache::make('logstore_xapi', 'senderror');

        foreach ([503 => 'Service Unavailable', 401 => 'Unauthorized', 404 => 'Not Found'] as $status => $body) {
            $this->lrs_answers($status, $body);
            $this->run_task(new emit_task());

            $this->assertEquals(3, $DB->count_records('logstore_xapi_log'));
            $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
            $this->assertSame($status, $cache->get('last')->code);
            $this->assertSame($body, $cache->get('last')->message);
        }

        $this->lrs_answers(200, '[]');
        ob_start();
        (new emit_task())->execute();
        ob_end_clean();

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertEquals(3, $DB->count_records('logstore_xapi_sent_log'));
        $this->assertFalse($cache->get('last'));
    }

    /**
     * Moving failed events back queues them as failed-type events with no error columns,
     * and the failed task sends them again. Only the given ids are moved.
     *
     * @return void
     */
    public function test_moveback_requeues_failed_events(): void {
        global $DB;

        $this->queue_events($this->add_course_views(3));
        $this->run_task(new emit_task());
        $failedids = array_keys($DB->get_records('logstore_xapi_failed_log', null, 'id'));

        $mover = new moveback(array_slice($failedids, 0, 2));
        $this->assertTrue($mover->execute());

        $this->assertEquals(2, $DB->count_records('logstore_xapi_log', ['type' => XAPI_IMPORT_TYPE_FAILED]));
        $this->assertEquals(2, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals([$failedids[2]], array_keys($DB->get_records('logstore_xapi_failed_log')));

        // The emit task only sends live events, so the moved events wait for the failed task.
        ob_start();
        (new emit_task())->execute();
        ob_end_clean();
        $this->assertEquals(2, $DB->count_records('logstore_xapi_log'));

        $this->run_task(new failed_task());
        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(2, $DB->count_records('logstore_xapi_failed_log', ['type' => XAPI_IMPORT_TYPE_FAILED]));
        $this->assertEquals(3, $DB->count_records('logstore_xapi_failed_log'));
    }

    /**
     * Moving standard log events queues them as historic events linked to their standard log
     * row, and an event that is already queued is not queued twice.
     *
     * @return void
     */
    public function test_moveback_queues_historic_events(): void {
        global $DB;

        $standardids = $this->add_course_views(3);

        $mover = new moveback($standardids, true);
        $this->assertTrue($mover->execute());

        $queued = $DB->get_records('logstore_xapi_log', null, 'logstorestandardlogid');
        $this->assertCount(3, $queued);
        $this->assertEquals($standardids, array_values(array_column($queued, 'logstorestandardlogid')));
        foreach ($queued as $event) {
            $this->assertEquals(XAPI_IMPORT_TYPE_HISTORIC, $event->type);
        }

        $mover = new moveback($standardids, true);
        $this->assertTrue($mover->execute());
        $this->assertEquals(3, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(3, $DB->count_records('logstore_standard_log'));
    }
}
