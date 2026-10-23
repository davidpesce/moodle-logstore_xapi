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
 * Unit tests for the error report's filter options.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_get_distinct_options_from_failed_table
 */
final class report_filter_options_test extends \advanced_testcase {
    /**
     * Store a failed event with the given LRS response.
     *
     * @param string $response The raw LRS response body.
     * @return void
     */
    private function add_failed_event(string $response): void {
        global $DB;
        $DB->insert_record('logstore_xapi_failed_log', (object) [
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
            'type' => 2,
            'errortype' => 400,
            'response' => $response,
        ]);
    }

    /**
     * LRS responses are untrusted. Core's select template prints option labels unescaped, so
     * the labels must be escaped, while the values stay raw so the filter still matches.
     *
     * @return void
     */
    public function test_response_labels_are_escaped(): void {
        $this->resetAfterTest();
        $payload = '</select><img src=x onerror=alert(1)>';
        $this->add_failed_event($payload);

        $options = logstore_xapi_get_distinct_options_from_failed_table('response');

        $this->assertArrayHasKey($payload, $options);
        $this->assertStringNotContainsString('<img', $options[$payload]);
        $this->assertSame(s($payload), $options[$payload]);
    }

    /**
     * Long responses are shortened in the label only.
     *
     * @return void
     */
    public function test_long_response_labels_are_shortened(): void {
        $this->resetAfterTest();
        $long = str_repeat('x', 300);
        $this->add_failed_event($long);

        $options = logstore_xapi_get_distinct_options_from_failed_table('response');

        $this->assertArrayHasKey($long, $options);
        $this->assertSame(str_repeat('x', 100) . '…', $options[$long]);
    }
}
