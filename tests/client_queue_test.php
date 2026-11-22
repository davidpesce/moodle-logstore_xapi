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

use logstore_xapi\client\queue_processor;
use logstore_xapi\client\verb_policy;

/**
 * Tests for client-side xAPI statement queue helpers.
 *
 * @package   logstore_xapi
 * @copyright 2026 Lachlan Keown <lachlankeown@gmail.com>, NZ ADL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class client_queue_test extends \advanced_testcase {
    /**
     * Return a valid client statement.
     *
     * @return array
     */
    private function statement(): array {
        return [
            'id' => 'client-statement-1',
            'actor' => ['mbox' => 'mailto:learner@example.test'],
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/completed'],
            'object' => ['id' => 'https://example.test/activity/1'],
        ];
    }

    /**
     * Valid statements are accepted and malformed statements are rejected.
     *
     * @return void
     */
    public function test_validate_client_statement(): void {
        $this->resetAfterTest();

        $error = null;
        $this->assertTrue(logstore_xapi_validate_client_statement($this->statement(), $error));
        $this->assertNull($error);

        $invalid = $this->statement();
        unset($invalid['verb']);
        $this->assertFalse(logstore_xapi_validate_client_statement($invalid, $error));
        $this->assertNotEmpty($error);
    }

    /**
     * Non-IRI verbs and activity ids are rejected, non-activity objects are not.
     *
     * @return void
     */
    public function test_validate_client_statement_rejects_invalid_iris(): void {
        $error = null;

        $badverb = $this->statement();
        $badverb['verb']['id'] = 'completed';
        $this->assertFalse(logstore_xapi_validate_client_statement($badverb, $error));
        $this->assertStringContainsString('verb', $error);

        $badobject = $this->statement();
        $badobject['object']['id'] = 'activity 1';
        $this->assertFalse(logstore_xapi_validate_client_statement($badobject, $error));

        // Statement references may carry a non-IRI id.
        $ref = $this->statement();
        $ref['object'] = ['objectType' => 'StatementRef', 'id' => 'some-reference-id'];
        $this->assertTrue(logstore_xapi_validate_client_statement($ref, $error));
    }

    /**
     * Values must be IRIs with a scheme, a colon and further content.
     *
     * @return void
     */
    public function test_is_valid_iri(): void {
        $this->assertTrue(logstore_xapi_is_valid_iri('http://adlnet.gov/expapi/verbs/completed'));
        $this->assertTrue(logstore_xapi_is_valid_iri('mailto:learner@example.com'));
        $this->assertTrue(logstore_xapi_is_valid_iri('HTTPS://example.test/activity/1'));
        $this->assertFalse(logstore_xapi_is_valid_iri('not-an-iri'));
        $this->assertFalse(logstore_xapi_is_valid_iri('http:'));
        $this->assertFalse(logstore_xapi_is_valid_iri(''));
        $this->assertFalse(logstore_xapi_is_valid_iri(42));
    }

    /**
     * Client delivery receives all LRS authentication settings.
     *
     * @return void
     */
    public function test_client_loader_config_includes_oauth2_settings(): void {
        $this->resetAfterTest();

        set_config('authmethod', 'oauth2', 'logstore_xapi');
        set_config('oauth2tokenendpoint', 'https://identity.example.test/token', 'logstore_xapi');
        set_config('oauth2scope', 'statements.write', 'logstore_xapi');

        $config = queue_processor::get_loader_config();

        $this->assertSame('oauth2', $config['lrs_auth_method']);
        $this->assertSame('https://identity.example.test/token', $config['lrs_oauth2_token_endpoint']);
        $this->assertSame('statements.write', $config['lrs_oauth2_scope']);
    }

    /**
     * The Send content setting controls client-side response text and preserves the default.
     *
     * @return void
     */
    public function test_is_sending_content_uses_configured_setting(): void {
        $this->resetAfterTest();

        $this->assertTrue(queue_processor::is_sending_content());
        set_config('sendcontent', 0, 'logstore_xapi');
        $this->assertFalse(queue_processor::is_sending_content());
        set_config('sendcontent', 1, 'logstore_xapi');
        $this->assertTrue(queue_processor::is_sending_content());
    }

    /**
     * Disabling Send content removes response text from statements and nested SubStatements.
     *
     * @return void
     */
    public function test_remove_response_content(): void {
        $statement = $this->statement();
        $statement['result'] = [
            'response' => 'A learner response',
            'success' => true,
            'score' => ['raw' => 1],
        ];
        $statement['object'] = [
            'objectType' => 'SubStatement',
            'actor' => ['account' => ['homePage' => 'https://example.test', 'name' => '1']],
            'verb' => ['id' => 'http://adlnet.gov/expapi/verbs/answered'],
            'object' => ['id' => 'https://example.test/question/1'],
            'result' => ['response' => 'Nested learner response', 'success' => false],
        ];

        queue_processor::remove_response_content($statement);

        $this->assertArrayNotHasKey('response', $statement['result']);
        $this->assertTrue($statement['result']['success']);
        $this->assertSame(['raw' => 1], $statement['result']['score']);
        $this->assertArrayNotHasKey('response', $statement['object']['result']);
        $this->assertFalse($statement['object']['result']['success']);
    }

    /**
     * Disabling Send content scrubs responses from failed statements already in the queue.
     *
     * @return void
     */
    public function test_process_scrubs_queued_response_content_when_disabled(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('sendcontent', 0, 'logstore_xapi');
        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $statement = $this->statement();
        $statement['result'] = ['response' => 'Private learner response', 'success' => true];
        $statement['id'] = 'client-statement-private-response';
        $result = logstore_xapi_queue_client_statement($statement, json_encode($statement), $user->id, $context->id);
        $DB->set_field('logstore_xapi_client_log', 'type', XAPI_IMPORT_TYPE_FAILED, ['id' => $result['id']]);
        $DB->set_field('logstore_xapi_client_log', 'attempts', XAPI_CLIENT_QUEUE_MAX_ATTEMPTS,
            ['id' => $result['id']]);
        $record = $DB->get_record('logstore_xapi_client_log', ['id' => $result['id']], '*', MUST_EXIST);

        $summary = queue_processor::process([$record]);

        $stored = $DB->get_field('logstore_xapi_client_log', 'statement', ['id' => $result['id']]);
        $this->assertArrayNotHasKey('response', json_decode($stored, true)['result']);
        $this->assertSame(1, $summary['exhausted']);
    }

    /**
     * The actor is derived from the authenticated user.
     *
     * @return void
     */
    public function test_get_actor_for_user(): void {
        $user = (object) [
            'id' => 42,
            'username' => 'learner',
            'email' => 'learner@example.test',
            'firstname' => 'Test',
            'lastname' => 'Learner',
        ];
        $config = [
            'send_mbox' => false,
            'send_name' => false,
            'send_username' => false,
            'account_homepage' => 'https://lms.example.test',
            'app_url' => 'https://lms.example.test',
        ];

        $actor = queue_processor::get_actor_for_user($user, $config);
        $this->assertSame('https://lms.example.test', $actor['account']['homePage']);
        $this->assertSame('42', $actor['account']['name']);

        $config['send_username'] = true;
        $actor = queue_processor::get_actor_for_user($user, $config);
        $this->assertSame('learner', $actor['account']['name']);

        $config['send_mbox'] = true;
        $actor = queue_processor::get_actor_for_user($user, $config);
        $this->assertSame('mailto:learner@example.test', $actor['mbox']);

        $config['send_name'] = true;
        $actor = queue_processor::get_actor_for_user($user, $config);
        $this->assertSame('Test Learner', $actor['name']);
    }

    /**
     * Statements are queued once and duplicate statement IDs are ignored.
     *
     * @return void
     */
    public function test_queue_client_statement_is_idempotent(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $statement = $this->statement();
        $json = json_encode($statement);

        $first = logstore_xapi_queue_client_statement($statement, $json, $user->id, $context->id);
        $second = logstore_xapi_queue_client_statement($statement, $json, $user->id, $context->id);

        $this->assertTrue($first['queued']);
        $this->assertFalse($first['duplicate']);
        $this->assertFalse($second['queued']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, $DB->count_records('logstore_xapi_client_log'));
    }

    /**
     * Failures retain a queue row and move it to the failed type.
     *
     * @return void
     */
    public function test_failed_client_statement_is_retained_for_retry(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $statement = $this->statement();
        $json = json_encode($statement);
        $result = logstore_xapi_queue_client_statement($statement, $json, $user->id, $context->id);
        $record = $DB->get_record('logstore_xapi_client_log', ['id' => $result['id']], '*', MUST_EXIST);
        $record->errortype = XAPI_REPORT_ERRORTYPE_LRS;
        $record->response = 'test failure';

        logstore_xapi_update_client_event_failure($record);
        $updated = $DB->get_record('logstore_xapi_client_log', ['id' => $record->id], '*', MUST_EXIST);

        $this->assertSame(XAPI_IMPORT_TYPE_FAILED, (int)$updated->type);
        $this->assertSame(1, (int)$updated->attempts);
        $this->assertSame('test failure', $updated->response);
    }

    /**
     * Verb map contains the H5P verbs and all are enabled by default.
     *
     * @return void
     */
    public function test_client_verb_map_defaults_to_all_enabled(): void {
        $this->resetAfterTest();

        $map = verb_policy::get_verb_map();
        $this->assertArrayHasKey('answered', $map);
        $this->assertArrayHasKey('interacted', $map);
        $this->assertSame(
            'http://adlnet.gov/expapi/verbs/answered',
            $map['answered']
        );
        $this->assertSame(
            'http://adlnet.gov/expapi/verbs/interacted',
            $map['interacted']
        );

        // Fresh installs (no saved config) allow every known verb.
        $this->assertTrue(
            verb_policy::is_enabled('http://adlnet.gov/expapi/verbs/answered')
        );
        $this->assertTrue(
            verb_policy::is_enabled('http://adlnet.gov/expapi/verbs/interacted')
        );
    }

    /**
     * Disabled verbs are rejected while other verbs still pass.
     *
     * @return void
     */
    public function test_disabled_client_verb_is_filtered(): void {
        $this->resetAfterTest();

        set_config('clientverbs', 'answered', 'logstore_xapi');
        set_config('clientverbs_allow_unknown', 1, 'logstore_xapi');

        $this->assertTrue(
            verb_policy::is_enabled('http://adlnet.gov/expapi/verbs/answered')
        );
        $this->assertFalse(
            verb_policy::is_enabled('http://adlnet.gov/expapi/verbs/interacted')
        );
        $this->assertSame(
            ['answered'],
            verb_policy::get_enabled_verbs()
        );
        $this->assertSame(
            ['http://adlnet.gov/expapi/verbs/answered'],
            verb_policy::get_enabled_verb_ids()
        );
    }

    /**
     * Unknown verbs follow their own setting.
     *
     * @return void
     */
    public function test_unknown_client_verb_follows_allow_unknown_setting(): void {
        $this->resetAfterTest();

        set_config('clientverbs', 'answered', 'logstore_xapi');

        set_config('clientverbs_allow_unknown', 1, 'logstore_xapi');
        $this->assertTrue(
            verb_policy::is_enabled('https://example.test/verbs/custom')
        );

        set_config('clientverbs_allow_unknown', 0, 'logstore_xapi');
        $this->assertFalse(
            verb_policy::is_enabled('https://example.test/verbs/custom')
        );
    }

    /**
     * Worker drops already-queued statements with a disabled verb.
     *
     * @return void
     */
    public function test_process_drops_disabled_verb(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('clientverbs', 'answered', 'logstore_xapi');
        set_config('clientverbs_allow_unknown', 1, 'logstore_xapi');

        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $statement = $this->statement();
        $statement['verb']['id'] = 'http://adlnet.gov/expapi/verbs/interacted';
        $statement['id'] = 'client-statement-filtered';
        $json = json_encode($statement);
        $result = logstore_xapi_queue_client_statement($statement, $json, $user->id, $context->id);
        $record = $DB->get_record('logstore_xapi_client_log', ['id' => $result['id']], '*', MUST_EXIST);

        $summary = queue_processor::process([$record]);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['filtered']);
        $this->assertSame(0, $summary['sent']);
        $this->assertSame(0, $summary['failed']);
        $this->assertFalse($DB->record_exists('logstore_xapi_client_log', ['id' => $record->id]));
    }

    /**
     * Statements containing JSON lists (grouping, choices) are accepted.
     *
     * Decoded lists carry integer keys, which are not field names and must
     * not trip the shape check.
     *
     * @return void
     */
    public function test_validate_client_statement_accepts_lists(): void {
        $error = null;
        $statement = $this->statement();
        $statement['context'] = ['contextActivities' => ['grouping' => [
            ['id' => 'https://example.test/course/view.php?id=7'],
        ]]];
        $statement['object']['definition'] = ['choices' => [
            ['id' => 'a'], ['id' => 'b'],
        ]];
        $this->assertTrue(logstore_xapi_validate_client_statement($statement, $error));
        $this->assertNull($error);
    }

    /**
     * Failed records past the attempt cap are skipped, not retried.
     *
     * @return void
     */
    public function test_process_skips_exhausted_failed_records(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $statement = $this->statement();
        $statement['id'] = 'client-statement-exhausted';
        $json = json_encode($statement);
        $result = logstore_xapi_queue_client_statement($statement, $json, $user->id, $context->id);
        $DB->set_field('logstore_xapi_client_log', 'type', XAPI_IMPORT_TYPE_FAILED, ['id' => $result['id']]);
        $DB->set_field('logstore_xapi_client_log', 'attempts', XAPI_CLIENT_QUEUE_MAX_ATTEMPTS,
            ['id' => $result['id']]);
        $record = $DB->get_record('logstore_xapi_client_log', ['id' => $result['id']], '*', MUST_EXIST);

        $summary = queue_processor::process([$record]);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['exhausted']);
        $this->assertSame(0, $summary['sent']);
        $this->assertSame(0, $summary['failed']);
        // The row is kept for inspection; no delivery was attempted.
        $this->assertTrue($DB->record_exists('logstore_xapi_client_log', ['id' => $record->id]));
    }

    /**
     * The scheduled task prunes aged sent rows even when capture is off.
     *
     * @return void
     */
    public function test_client_emit_task_prunes_old_sent_rows(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $context = \context_user::instance($user->id);
        $old = [
            'clientkey' => hash('sha256', 'old-row'),
            'statementid' => 'old-statement',
            'userid' => $user->id,
            'contextid' => $context->id,
            'timecreated' => time() - XAPI_CLIENT_SENT_RETENTION_SECS - 100,
        ];
        $oldid = $DB->insert_record('logstore_xapi_client_sent', (object)$old);
        $recentid = $DB->insert_record('logstore_xapi_client_sent', (object)array_merge($old, [
            'clientkey' => hash('sha256', 'recent-row'),
            'statementid' => 'recent-statement',
            'timecreated' => time(),
        ]));

        $task = new \logstore_xapi\task\client_emit_task();
        $task->execute();

        $this->assertFalse($DB->record_exists('logstore_xapi_client_sent', ['id' => $oldid]));
        $this->assertTrue($DB->record_exists('logstore_xapi_client_sent', ['id' => $recentid]));
    }

    /**
     * A grouping claim for an accessible course verifies and normalises.
     *
     * @return void
     */
    public function test_resolve_client_statement_course_returns_verified_course(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        $statement = $this->statement();
        $statement['context'] = ['contextActivities' => ['grouping' => [
            ['id' => 'https://evil.test/course/view.php?id=' . $course->id],
            ['id' => $CFG->wwwroot . '/course/view.php?id=' . $course->id],
        ]]];

        $courseid = logstore_xapi_resolve_client_statement_course($statement, $user);

        $this->assertSame((int)$course->id, $courseid);
        // Verified claims carry the standard course activity, identical to
        // server-side statements: cmi5 course type plus the localised name.
        $grouping = $statement['context']['contextActivities']['grouping'];
        $this->assertCount(1, $grouping);
        $this->assertSame($CFG->wwwroot . '/course/view.php?id=' . $course->id, $grouping[0]['id']);
        $this->assertSame(
            'https://w3id.org/xapi/cmi5/activitytype/course',
            $grouping[0]['definition']['type']
        );
        $this->assertSame($course->fullname, reset($grouping[0]['definition']['name']));
    }

    /**
     * Grouping claims for inaccessible or off-site courses are stripped.
     *
     * @return void
     */
    public function test_resolve_client_statement_course_rejects_unverifiable_claims(): void {
        global $CFG, $DB;
        $this->resetAfterTest();

        $owncourse = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($user->id, $owncourse->id, $studentrole->id);

        // Enrolled nowhere near the claimed course: forged claim.
        $statement = $this->statement();
        $statement['context'] = ['contextActivities' => ['grouping' => [
            ['id' => $CFG->wwwroot . '/course/view.php?id=' . $othercourse->id],
        ]]];
        $this->assertNull(logstore_xapi_resolve_client_statement_course($statement, $user));
        $this->assertArrayNotHasKey('context', $statement);

        // Off-site URL: not this LMS at all.
        $statement = $this->statement();
        $statement['context'] = ['contextActivities' => ['grouping' => [
            ['id' => 'https://evil.test/course/view.php?id=' . $owncourse->id],
        ]]];
        $this->assertNull(logstore_xapi_resolve_client_statement_course($statement, $user));
        $this->assertArrayNotHasKey('context', $statement);

        // No grouping at all: nothing to do, statement untouched.
        $statement = $this->statement();
        $this->assertNull(logstore_xapi_resolve_client_statement_course($statement, $user));
        $this->assertArrayNotHasKey('context', $statement);
    }
}
