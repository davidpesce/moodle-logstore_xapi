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

use logstore_xapi\task\hash_failed_responses;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

/**
 * Tests for the response hashes of failed events, and the task that backfills them.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\task\hash_failed_responses
 * @covers    ::logstore_xapi_hash_response
 * @covers    ::logstore_xapi_has_unhashed_responses
 */
final class hash_failed_responses_test extends \advanced_testcase {
    /**
     * Store a failed event as 5.2 did, with no response hash.
     *
     * @param ?string $response The stored response.
     * @return int The failed log id.
     */
    private function add_unhashed_failure(?string $response): int {
        global $DB;
        return $DB->insert_record('logstore_xapi_failed_log', (object) [
            'eventname' => '\\core\\event\\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => 1,
            'contextlevel' => 50,
            'contextinstanceid' => 2,
            'userid' => 2,
            'courseid' => 2,
            'anonymous' => 0,
            'timecreated' => 1433946701,
            'type' => 0,
            'errortype' => 400,
            'response' => $response,
        ]);
    }

    /**
     * Run a task, discarding its mtrace() output.
     *
     * @param hash_failed_responses $task The task.
     * @return void
     */
    private function run_task(hash_failed_responses $task): void {
        ob_start();
        $task->execute();
        ob_end_clean();
    }

    /**
     * Existing responses get their hash, and stored transform errors lose their event id.
     *
     * @return void
     */
    public function test_backfill_hashes_responses(): void {
        global $DB;
        $this->resetAfterTest();
        // An upgraded test site may already have the task queued by the upgrade.
        $DB->delete_records('task_adhoc', ['classname' => '\\' . hash_failed_responses::class]);

        $lrs = $this->add_unhashed_failure('{"errorMessage":"Bad statement"}');
        $transform = $this->add_unhashed_failure('{"transfromerror":"Failed transform for event id #123: user not found."}');
        $none = $this->add_unhashed_failure(null);
        $this->assertTrue(logstore_xapi_has_unhashed_responses());

        $this->run_task(new hash_failed_responses());

        $this->assertSame(
            sha1('{"errorMessage":"Bad statement"}'),
            $DB->get_field('logstore_xapi_failed_log', 'responsehash', ['id' => $lrs])
        );
        $expected = json_encode(['transfromerror' => 'Failed transform: user not found.']);
        $this->assertSame($expected, $DB->get_field('logstore_xapi_failed_log', 'response', ['id' => $transform]));
        $this->assertSame(sha1($expected), $DB->get_field('logstore_xapi_failed_log', 'responsehash', ['id' => $transform]));
        $this->assertNull($DB->get_field('logstore_xapi_failed_log', 'responsehash', ['id' => $none]));
        $this->assertFalse(logstore_xapi_has_unhashed_responses());
        $this->assertEmpty(\core\task\manager::get_adhoc_tasks(hash_failed_responses::class));
    }

    /**
     * A run that stops before every row is hashed queues the task again.
     *
     * @return void
     */
    public function test_backfill_requeues_until_done(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('task_adhoc', ['classname' => '\\' . hash_failed_responses::class]);
        foreach (['a', 'b', 'c'] as $response) {
            $this->add_unhashed_failure($response);
        }

        $task = new hash_failed_responses();
        $task->batchsize = 2;
        $task->timelimit = 0;
        $this->run_task($task);

        $this->assertTrue(logstore_xapi_has_unhashed_responses());
        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(hash_failed_responses::class));

        ob_start();
        $this->runAdhocTasks(hash_failed_responses::class);
        ob_end_clean();
        $this->assertFalse(logstore_xapi_has_unhashed_responses());
    }

    /**
     * Under cron, the running task's own row is still queued while it runs: the task is queued
     * again anyway, so the rest is hashed after the running row is deleted.
     *
     * @return void
     */
    public function test_backfill_requeues_under_cron(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('task_adhoc', ['classname' => '\\' . hash_failed_responses::class]);
        foreach (['a', 'b', 'c'] as $response) {
            $this->add_unhashed_failure($response);
        }
        // Queued as the upgrade queues it; this row stands for the running task.
        \core\task\manager::queue_adhoc_task(new hash_failed_responses(), true);
        $running = $DB->get_field('task_adhoc', 'id', ['classname' => '\\' . hash_failed_responses::class]);

        $task = new hash_failed_responses();
        $task->batchsize = 2;
        $task->timelimit = 0;
        $this->run_task($task);
        // Core deletes the running task's row once it ends.
        $DB->delete_records('task_adhoc', ['id' => $running]);

        $this->assertCount(1, \core\task\manager::get_adhoc_tasks(hash_failed_responses::class));
    }

    /**
     * New failures are stored with their hash, and transform errors without the event id.
     *
     * @return void
     */
    public function test_new_failures_are_hashed(): void {
        global $DB;
        $this->resetAfterTest();

        $event = (object) [
            'id' => 42,
            'eventname' => '\\core\\event\\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => 1,
            'contextlevel' => 50,
            'contextinstanceid' => 2,
            'userid' => 2,
            'courseid' => 2,
            'anonymous' => 0,
            'timecreated' => 1433946701,
            'type' => 0,
        ];
        $config = [
            'log_error' => function () {
            },
            'log_info' => function () {
            },
            'source_url' => 'http://moodle.org',
            'source_name' => 'Moodle',
            'source_version' => '1.0.0',
            'source_lang' => 'en',
            'send_mbox' => false,
            'send_name' => true,
            'send_response_choices' => false,
            'send_short_course_id' => false,
            'send_course_and_module_idnumber' => false,
            'send_username' => false,
            'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
            'plugin_version' => '0.0.0-development',
            // No user record, so the transform fails.
            'repo' => new \src\transformer\repos\TestRepository((object) ['user' => [], 'course' => []]),
            'app_url' => 'http://www.example.org',
        ];

        // Events that fail to transform are stored as not loaded, as the loader does.
        $loaded = array_map(function ($transformed) {
            return [...$transformed, 'loaded' => false];
        }, \src\transformer\handler($config, [$event]));
        $this->assertFalse($loaded[0]['transformed']);
        logstore_xapi_store_failed_events($loaded, false);

        $failed = $DB->get_record('logstore_xapi_failed_log', []);
        $this->assertStringNotContainsString('42', $failed->response);
        $this->assertStringContainsString('Failed transform: ', $failed->response);
        $this->assertSame(sha1($failed->response), $failed->responsehash);
    }
}
