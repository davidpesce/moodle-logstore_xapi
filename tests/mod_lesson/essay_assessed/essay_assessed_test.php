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

namespace logstore_xapi\mod_lesson\essay_assessed;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/tests/xapi_test_case.php');

/**
 * Unit test for mod_lesson lesson essay graded.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class essay_assessed_test extends \logstore_xapi\xapi_test_case {
    /**
     * Retrieve the directory of the unit test.
     *
     * @return string
     */
    protected function get_test_dir() {
        return __DIR__;
    }

    /**
     * Retrieve the plugin type being tested.
     *
     * @return string
     */
    protected function get_plugin_type() {
        return "mod";
    }

    /**
     * Retrieve the plugin name being tested.
     *
     * @return string
     */
    protected function get_plugin_name() {
        return "lesson";
    }

    /**
     * Appease auto-detecting of test cases. xapi_test_case has default test cases.
     *
     * @covers ::essay_assessed
     * @return void
     */
    public function test_init(): void {
    }

    /**
     * Lesson grading feedback text is optional and redacted when enabled.
     *
     * @covers \\src\\transformer\\utils\\get_lesson_essay_result
     * @return void
     */
    public function test_grading_feedback_is_sent_when_enabled(): void {
        $statements = $this->get_statement_results([
            'send_content' => true,
            'send_grading_feedback' => true,
            'redact_free_text' => true,
        ]);

        $this->assertSame('essay_grade_response', $statements[0]['result']['response']);
        $this->assertTrue($statements[0]['result']['extensions']['https://w3id.org/xapi/ocsw/extensions/feedback_present']);
    }

    /**
     * The master Send content setting still omits lesson grading feedback.
     *
     * @covers \\src\\transformer\\utils\\get_lesson_essay_result
     * @return void
     */
    public function test_send_content_off_omits_grading_feedback(): void {
        $statements = $this->get_statement_results([
            'send_content' => false,
            'send_grading_feedback' => true,
        ]);

        $this->assertArrayNotHasKey('response', $statements[0]['result']);
        $this->assertTrue($statements[0]['result']['extensions']['https://w3id.org/xapi/ocsw/extensions/feedback_present']);
    }
}
