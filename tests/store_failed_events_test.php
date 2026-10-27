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
 * Unit tests for logstore_xapi_store_failed_events().
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_store_failed_events
 */
final class store_failed_events_test extends \advanced_testcase {
    /**
     * Build a loaded event as returned by the handler.
     *
     * @param array $extra Fields the pipeline added to the event (response, errortype).
     * @param bool $loaded Whether the event was sent.
     * @return array
     */
    private function loaded_event(array $extra, bool $loaded): array {
        $event = (object) array_merge([
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
        ], $extra);
        return ['event' => $event, 'statements' => [], 'transformed' => true, 'loaded' => $loaded];
    }

    /**
     * A transform failure (response only) and a load failure (response and errortype) in the
     * same batch are both stored. insert_records() would throw on the mixed structure.
     *
     * @return void
     */
    public function test_mixed_failures_are_all_stored(): void {
        global $DB;
        $this->resetAfterTest();

        $events = [
            $this->loaded_event(['response' => '{"transfromerror":"boom"}'], false),
            $this->loaded_event(['response' => 'Bad Request', 'errortype' => 400], false),
            $this->loaded_event([], true),
        ];

        logstore_xapi_store_failed_events($events, false);

        $this->assertEquals(2, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log', ['errortype' => 400]));
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log', ['responsehash' => sha1('Bad Request')]));
    }

    /**
     * Nothing is printed when tracing is off, as it is during a web request.
     *
     * @return void
     */
    public function test_no_output_without_trace(): void {
        $this->resetAfterTest();

        $this->expectOutputString('');
        logstore_xapi_store_failed_events([$this->loaded_event(['response' => 'x'], false)], false);
    }
}
