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

/**
 * Unit tests for lib.php helper functions.
 *
 * @package   logstore_xapi
 * @copyright 2025 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_get_selected_cohorts
 * @covers    ::logstore_xapi_get_distinct_options_from_failed_table
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
}
