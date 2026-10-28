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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for recording a processed batch and removing it from the queue, all or nothing.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_finish_batch
 * @covers    ::logstore_xapi_record_retry_events
 */
final class finish_batch_test extends \advanced_testcase {
    /**
     * Queue two course views, and return them as loaded events: the first sent, the second
     * failed to load.
     *
     * @return array The loaded events.
     */
    private function queue_batch(): array {
        global $DB;
        $this->resetAfterTest();

        $loaded = [];
        foreach ([true, false] as $sent) {
            $event = (object) [
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
                'courseid' => SITEID,
                'anonymous' => 0,
                'timecreated' => 1000,
                'type' => XAPI_IMPORT_TYPE_LIVE,
            ];
            $event->id = $DB->insert_record('logstore_xapi_log', $event);
            if (!$sent) {
                $event->response = 'cURL error: connection refused';
                $event->errortype = 7;
            }
            $loaded[] = ['event' => $event, 'statements' => [], 'transformed' => true, 'loaded' => $sent];
        }
        return $loaded;
    }

    /**
     * Run finish_batch, discarding its mtrace() output.
     *
     * @param array $loaded The loaded events.
     * @return void
     */
    private function finish(array $loaded): void {
        ob_start();
        try {
            logstore_xapi_finish_batch($loaded);
        } finally {
            ob_end_clean();
        }
    }

    /**
     * The failure is stored and both events leave the queue.
     *
     * @return void
     */
    public function test_batch_is_recorded_and_dequeued(): void {
        global $DB;
        $loaded = $this->queue_batch();

        $this->finish($loaded);

        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log'));
    }

    /**
     * When storing the failure throws, nothing is recorded and the batch stays queued, to be
     * sent again.
     *
     * @return void
     */
    public function test_nothing_changes_when_it_throws(): void {
        global $DB;
        $loaded = $this->queue_batch();
        // A failed event that can't be stored: eventname is NOT NULL.
        $loaded[1]['event']->eventname = null;

        try {
            $this->finish($loaded);
            $this->fail('Storing the failure must throw.');
        } catch (\dml_write_exception $e) {
            $this->assertEquals(2, $DB->count_records('logstore_xapi_log'));
            $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
        }
    }

    /**
     * Events the LRS couldn't take now stay in the queue, out of the failed log, and the error is
     * kept for the status report.
     *
     * @return void
     */
    public function test_retry_events_stay_queued(): void {
        global $DB;
        $loaded = $this->queue_batch();
        $loaded[1]['loaded'] = XAPI_LOADED_RETRY;
        $loaded[1]['event']->response = 'Service Unavailable';
        $loaded[1]['event']->errortype = 503;

        $counts = logstore_xapi_finish_batch($loaded);

        $this->assertSame([$loaded[1]['event']->id], array_keys($DB->get_records('logstore_xapi_log')));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertSame(['sent' => 1, 'failed' => 0, 'retry' => 1], array_slice($counts, 0, 3));
        $this->assertSame(503, $counts['error']->code);
        $this->assertSame(503, \cache::make('logstore_xapi', 'senderror')->get('last')->code);
    }
}
