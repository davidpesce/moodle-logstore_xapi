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
 * Tests for leaving out of the historic report the events the store would skip.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_get_historic_skip_conditions
 */
final class historic_report_test extends \advanced_testcase {
    /** @var int[] Standard log ids by label. */
    private $ids = [];

    /**
     * Log events by a user, the guest, a logged-out visitor on the web and in a CLI script, and
     * an anonymous one.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG, $DB;
        parent::setUp();
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $rows = [
            'user' => [$user->id, 'web', 0],
            'guest' => [$CFG->siteguest, 'web', 0],
            'loggedout' => [0, 'web', 0],
            'cli' => [0, 'cli', 0],
            'anonymous' => [$user->id, 'web', 1],
        ];
        foreach ($rows as $label => [$userid, $origin, $anonymous]) {
            $this->ids[$label] = (int) $DB->insert_record('logstore_standard_log', (object) [
                'eventname' => '\core\event\course_viewed', 'component' => 'core', 'action' => 'viewed',
                'target' => 'course', 'crud' => 'r', 'edulevel' => 2, 'contextid' => 1, 'contextlevel' => CONTEXT_SYSTEM,
                'contextinstanceid' => 0, 'userid' => $userid, 'courseid' => SITEID, 'anonymous' => $anonymous,
                'origin' => $origin, 'timecreated' => time(),
            ]);
        }
    }

    /**
     * The labels of the events the historic report lists.
     *
     * @return string[]
     */
    private function listed(): array {
        global $DB;
        [$where, $params] = logstore_xapi_get_historic_skip_conditions('x');
        [$insql, $inparams] = $DB->get_in_or_equal(array_values($this->ids), SQL_PARAMS_NAMED);
        $sql = "SELECT x.id FROM {logstore_standard_log} x WHERE x.id $insql" .
            ($where ? ' AND ' . implode(' AND ', $where) : '');
        $listed = array_map('intval', $DB->get_fieldset_sql($sql, $params + $inparams));
        return array_keys(array_intersect($this->ids, $listed));
    }

    /**
     * With guests logged and anonymous events sent, every event is listed.
     *
     * @return void
     */
    public function test_nothing_skipped(): void {
        set_config('logguests', 1, 'logstore_xapi');
        set_config('anonymousevents', XAPI_ANONYMOUS_EVENTS_ANONYMISE, 'logstore_xapi');

        $this->assertSame(['user', 'guest', 'loggedout', 'cli', 'anonymous'], $this->listed());
    }

    /**
     * Without "Log guest actions", guest and logged-out events aren't listed, except from CLI
     * scripts; set not to be sent, anonymous events aren't listed.
     *
     * @return void
     */
    public function test_skipped_events_are_not_listed(): void {
        set_config('logguests', 0, 'logstore_xapi');
        set_config('anonymousevents', XAPI_ANONYMOUS_EVENTS_SKIP, 'logstore_xapi');

        $this->assertSame(['user', 'cli'], $this->listed());
    }
}
