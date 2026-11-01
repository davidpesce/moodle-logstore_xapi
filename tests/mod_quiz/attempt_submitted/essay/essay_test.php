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

namespace logstore_xapi\mod_quiz\attempt_submitted\essay;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/tests/xapi_test_case.php');

/**
 * Unit test for mod_quiz attempt submitted (essay) event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class essay_test extends \logstore_xapi\xapi_test_case {
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
        return "quiz";
    }

    /**
     * Appease auto-detecting of test cases. xapi_test_case has default test cases.
     *
     * @covers ::attempt_submitted
     * @return void
     */
    public function test_init(): void {
    }

    /**
     * Essay answer text is included only by its setting and is redacted by default.
     *
     * @covers \\src\\transformer\\events\\mod_quiz\\question_answered\\essay
     * @return void
     */
    public function test_enabled_essay_text_is_redacted(): void {
        $statement = $this->get_essay_statement([
            'send_content' => true,
            'send_essay_text' => true,
            'redact_free_text' => true,
        ]);

        $this->assertSame('[name]_answer', $statement['result']['response']);
    }

    /**
     * Turning redaction off sends the essay answer as authored.
     *
     * @covers \\src\\transformer\\events\\mod_quiz\\question_answered\\essay
     * @return void
     */
    public function test_essay_text_can_be_sent_unredacted(): void {
        $statement = $this->get_essay_statement([
            'send_content' => true,
            'send_essay_text' => true,
            'redact_free_text' => false,
        ]);

        $this->assertSame('test_answer', $statement['result']['response']);
    }

    /**
     * The master Send content setting still omits essay text.
     *
     * @covers \\src\\transformer\\events\\mod_quiz\\question_answered\\essay
     * @return void
     */
    public function test_send_content_off_omits_essay_text(): void {
        $statement = $this->get_essay_statement([
            'send_content' => false,
            'send_essay_text' => true,
            'redact_free_text' => false,
        ]);

        $this->assertArrayNotHasKey('response', $statement['result']);
    }

    /**
     * Return the essay statement from the fixture event.
     *
     * @param array $overrides Transformer setting overrides.
     * @return array
     */
    private function get_essay_statement(array $overrides): array {
        foreach ($this->get_statement_results($overrides) as $statement) {
            if (!empty($statement['result']['extensions']['https://w3id.org/xapi/ocsw/extensions/essay'])) {
                return $statement;
            }
        }
        $this->fail('The fixture event did not produce an essay statement.');
    }
}
