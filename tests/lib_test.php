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

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

/**
 * Unit tests for lib.php helper functions.
 *
 * @package   logstore_xapi
 * @copyright 2025 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_get_selected_cohorts
 * @covers    ::logstore_xapi_get_distinct_options_from_failed_table
 * @covers    ::logstore_xapi_get_cohort_members
 * @covers    ::logstore_xapi_get_users_for_notifications
 * @covers    ::logstore_xapi_get_default_routes
 * @covers    ::logstore_xapi_clean_report_filters
 */
final class lib_test extends advanced_testcase {
    /**
     * Create a cohort record and return its id.
     *
     * @param  string $name    Cohort name.
     * @param  int    $visible 1 = visible, 0 = hidden.
     * @return int Cohort id.
     */
    private function create_cohort(string $name, int $visible = 1): int {
        global $DB;
        return (int)$DB->insert_record('cohort', (object)[
            'name'              => $name,
            'idnumber'          => '',
            'description'       => '',
            'descriptionformat' => FORMAT_HTML,
            'contextid'         => \context_system::instance()->id,
            'visible'           => $visible,
            'timecreated'       => time(),
            'timemodified'      => time(),
            'component'         => '',
        ]);
    }

    /**
     * Returns visible selected cohorts when config contains valid visible IDs.
     *
     * @return void
     */
    public function test_returns_visible_selected_cohorts(): void {
        $this->resetAfterTest();

        $id1 = $this->create_cohort('Cohort One');
        $id2 = $this->create_cohort('Cohort Two');
        set_config('cohorts', "$id1,$id2", 'logstore_xapi');

        $result = logstore_xapi_get_selected_cohorts();
        sort($result);

        $this->assertSame([(string)$id1, (string)$id2], $result);
    }

    /**
     * Invisible cohort IDs are excluded from the result.
     *
     * @return void
     */
    public function test_invisible_cohort_is_excluded(): void {
        $this->resetAfterTest();

        $visibleid   = $this->create_cohort('Visible', 1);
        $invisibleid = $this->create_cohort('Invisible', 0);
        set_config('cohorts', "$visibleid,$invisibleid", 'logstore_xapi');

        $result = logstore_xapi_get_selected_cohorts();

        $this->assertContains((string)$visibleid, $result);
        $this->assertNotContains((string)$invisibleid, $result);
    }

    /**
     * A cohort ID that no longer exists in the DB is excluded.
     *
     * @return void
     */
    public function test_deleted_cohort_is_excluded(): void {
        $this->resetAfterTest();

        $realid   = $this->create_cohort('Real');
        $fakeid   = 99999;
        set_config('cohorts', "$realid,$fakeid", 'logstore_xapi');

        $result = logstore_xapi_get_selected_cohorts();

        $this->assertContains((string)$realid, $result);
        $this->assertNotContains((string)$fakeid, $result);
    }

    /**
     * Returns an empty array when the config value is empty.
     *
     * @return void
     */
    public function test_returns_empty_array_when_config_empty(): void {
        $this->resetAfterTest();

        set_config('cohorts', '', 'logstore_xapi');

        $this->assertSame([], logstore_xapi_get_selected_cohorts());
    }

    /**
     * Returns an empty array when the config key has never been set.
     *
     * @return void
     */
    public function test_returns_empty_array_when_config_unset(): void {
        $this->resetAfterTest();

        $this->assertSame([], logstore_xapi_get_selected_cohorts());
    }

    /**
     * The columns the report actually filters on are accepted.
     *
     * @return void
     */
    public function test_distinct_options_accepts_allowed_columns(): void {
        $this->resetAfterTest();

        foreach (XAPI_REPORT_FILTER_COLUMNS as $column) {
            $options = logstore_xapi_get_distinct_options_from_failed_table($column);
            $this->assertSame(get_string('any'), $options[0]);
        }
    }

    /**
     * Distinct values from the failed log become filter options.
     *
     * @return void
     */
    public function test_distinct_options_returns_column_values(): void {
        global $DB;
        $this->resetAfterTest();

        foreach ([XAPI_REPORT_ERRORTYPE_NETWORK, XAPI_REPORT_ERRORTYPE_NETWORK, XAPI_REPORT_ERRORTYPE_AUTH] as $type) {
            $DB->insert_record('logstore_xapi_failed_log', (object) [
                'eventname' => '\core\event\course_viewed',
                'component' => 'core',
                'action' => 'viewed',
                'target' => 'course',
                'crud' => 'r',
                'edulevel' => 0,
                'contextid' => \context_system::instance()->id,
                'contextlevel' => CONTEXT_SYSTEM,
                'contextinstanceid' => 0,
                'userid' => 2,
                'courseid' => 0,
                'relateduserid' => 0,
                'anonymous' => 0,
                'other' => 'N;',
                'timecreated' => time(),
                'origin' => 'web',
                'ip' => '198.51.100.1',
                'realuserid' => 0,
                'errortype' => $type,
            ]);
        }

        $options = logstore_xapi_get_distinct_options_from_failed_table('errortype');

        // Duplicates collapse, and the "any" option is retained.
        $this->assertArrayHasKey(XAPI_REPORT_ERRORTYPE_NETWORK, $options);
        $this->assertArrayHasKey(XAPI_REPORT_ERRORTYPE_AUTH, $options);
        $this->assertCount(3, $options);
    }

    /**
     * A column outside the allow-list is rejected rather than interpolated
     * into the query, since identifiers cannot be bound as parameters.
     *
     * @return void
     */
    public function test_distinct_options_rejects_unlisted_column(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        logstore_xapi_get_distinct_options_from_failed_table('userid');
    }

    /**
     * An injection attempt is rejected before reaching the database.
     *
     * @return void
     */
    public function test_distinct_options_rejects_injection_attempt(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        logstore_xapi_get_distinct_options_from_failed_table('id FROM {user} WHERE 1=1 --');
    }

    /**
     * A user in several selected cohorts is returned once.
     *
     * @return void
     */
    public function test_cohort_members_are_unique(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $id1 = $this->create_cohort('Cohort One');
        $id2 = $this->create_cohort('Cohort Two');
        cohort_add_member($id1, $user->id);
        cohort_add_member($id2, $user->id);

        $members = logstore_xapi_get_cohort_members([$id1, $id2]);

        $this->assertSame([(int)$user->id], array_map('intval', array_keys($members)));
    }

    /**
     * Suspended and deleted users are not returned as cohort members.
     *
     * @return void
     */
    public function test_cohort_members_exclude_inactive_users(): void {
        $this->resetAfterTest();

        $active = $this->getDataGenerator()->create_user();
        $suspended = $this->getDataGenerator()->create_user(['suspended' => 1]);
        $deleted = $this->getDataGenerator()->create_user();
        $id = $this->create_cohort('Cohort One');
        foreach ([$active, $suspended, $deleted] as $user) {
            cohort_add_member($id, $user->id);
        }
        delete_user($deleted);

        $members = logstore_xapi_get_cohort_members([$id]);

        $this->assertSame([(int)$active->id], array_map('intval', array_keys($members)));
        // Only the fields needed to send email are loaded.
        $this->assertSame($active->email, $members[$active->id]->email);
        $this->assertFalse(isset($members[$active->id]->password));
    }

    /**
     * Each address is notified once, even when it is both a cohort member and an additional address.
     *
     * @return void
     */
    public function test_notification_recipients_are_unique(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user(['email' => 'member@example.com']);
        $id1 = $this->create_cohort('Cohort One');
        $id2 = $this->create_cohort('Cohort Two');
        cohort_add_member($id1, $user->id);
        cohort_add_member($id2, $user->id);
        set_config('cohorts', "$id1,$id2", 'logstore_xapi');
        $additional = 'Member@Example.com, other@example.com,other@example.com,not-an-email';
        set_config('send_additional_email_addresses', $additional, 'logstore_xapi');

        $recipients = logstore_xapi_get_users_for_notifications();

        $this->assertCount(2, $recipients);
        $this->assertEquals($user->id, $recipients[0]->id);
        $this->assertSame('other@example.com', $recipients[1]->email);
        $this->assertFalse(isset($recipients[1]->id));
    }

    /**
     * New installs enable every route except private messages, notes and search queries.
     *
     * @return void
     */
    public function test_default_routes_leave_out_private_text(): void {
        $eventnames = array_keys(\src\transformer\get_event_function_map());

        $defaults = logstore_xapi_get_default_routes($eventnames);

        // Every route that is off by default must be a real event, so a typo cannot silently enable one.
        foreach (XAPI_ROUTES_OFF_BY_DEFAULT as $eventname) {
            $this->assertContains($eventname, $eventnames);
            $this->assertArrayNotHasKey($eventname, $defaults);
        }
        $this->assertCount(count($eventnames) - count(XAPI_ROUTES_OFF_BY_DEFAULT), $defaults);
        $this->assertArrayNotHasKey('\\core\\event\\search_results_viewed', $defaults);
        $this->assertArrayHasKey('\\mod_quiz\\event\\attempt_submitted', $defaults);
    }

    /**
     * Report filters from the URL keep only known filters, with the expected types.
     *
     * @return void
     */
    public function test_clean_report_filters_keeps_valid_values(): void {
        $eventnames = ['\\core\\event\\course_viewed' => '\\core\\event\\course_viewed'];
        $onpage = json_encode([
            'userfullname' => 'Ann',
            'response' => '{"error":"x"}',
            'errortype' => '400',
            'eventcontext' => 12,
            'datefrom' => 1700000000,
            'dateto' => 'tomorrow',
            'eventnames' => ['\\core\\event\\course_viewed', '\\fake\\event\\x', ['nested']],
            'unknown' => 'dropped',
        ]);

        $filters = logstore_xapi_clean_report_filters($onpage, $eventnames);

        $this->assertEquals((object) [
            'userfullname' => 'Ann',
            'response' => '{"error":"x"}',
            'errortype' => 400,
            'eventcontext' => 12,
            'datefrom' => 1700000000,
            'eventnames' => ['\\core\\event\\course_viewed'],
        ], $filters);
    }

    /**
     * Malformed or wrongly typed report filters are ignored.
     *
     * @return void
     */
    public function test_clean_report_filters_ignores_bad_input(): void {
        $this->assertEquals(new \stdClass(), logstore_xapi_clean_report_filters('not json', []));
        $this->assertEquals(new \stdClass(), logstore_xapi_clean_report_filters('"a string"', []));
        $this->assertEquals(new \stdClass(), logstore_xapi_clean_report_filters(
            json_encode(['userfullname' => ['x'], 'errortype' => -1, 'eventnames' => 'core']),
            []
        ));
    }

    /**
     * Stored responses are shown as pretty-printed JSON, or as they are when not JSON.
     *
     * @return void
     */
    public function test_response_display(): void {
        $this->assertSame("{\n    \"error\": \"x\"\n}", logstore_xapi_get_response_display('{"error":"x"}'));
        $curlerror = 'cURL error: Could not resolve host';
        $this->assertSame($curlerror, logstore_xapi_get_response_display($curlerror));
        $this->assertSame('<h1>Bad Gateway</h1>', logstore_xapi_get_response_display('<h1>Bad Gateway</h1>'));
    }
}
