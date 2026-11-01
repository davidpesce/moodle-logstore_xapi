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

namespace logstore_xapi\mod_assign\assignment_graded\existing_assignment_graded_comment;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/tests/xapi_test_case.php');

/**
 * Unit test for mod_assignment graded comment event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class existing_assignment_graded_comment_test extends \logstore_xapi\xapi_test_case {
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
        return "assign";
    }

    /**
     * Appease auto-detecting of test cases. xapi_test_case has default test cases.
     *
     * @covers ::assignment_graded
     * @return void
     */
    public function test_init(): void {
    }

    /**
     * Assignment grading comments are sent only when their setting is enabled.
     *
     * @covers \\src\\transformer\\events\\mod_assign\\assignment_graded
     * @return void
     */
    public function test_grading_feedback_is_sent_when_enabled(): void {
        $statements = $this->get_statement_results([
            'send_content' => true,
            'send_grading_feedback' => true,
            'redact_free_text' => false,
        ]);

        $this->assertSame('test_comment_text', $statements[0]['result']['response']);
    }

    /**
     * The master Send content setting still omits assignment grading comments.
     *
     * @covers \\src\\transformer\\events\\mod_assign\\assignment_graded
     * @return void
     */
    public function test_send_content_off_omits_grading_feedback(): void {
        $statements = $this->get_statement_results([
            'send_content' => false,
            'send_grading_feedback' => true,
            'redact_free_text' => false,
        ]);

        $this->assertArrayNotHasKey('response', $statements[0]['result']);
    }
}
