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

namespace logstore_xapi\core\user_graded\gradebook_mark;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/tests/xapi_test_case.php');

/**
 * Unit tests for a grade entered in the gradebook.
 *
 * @package   logstore_xapi
 * @copyright 2026 Flavio <flavio@ferreira-michell.org>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \src\transformer\events\core\user_graded
 */
final class gradebook_mark_test extends \logstore_xapi\xapi_test_case {
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
        return 'core';
    }

    /**
     * Retrieve the plugin name being tested.
     *
     * @return string
     */
    protected function get_plugin_name() {
        return 'core';
    }

    /**
     * The statement uses the grade captured by the event, not the later database value.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_init(): void {
    }

    /**
     * A deleted grade item is ignored instead of failing the event batch.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_deleted_grade_item_is_skipped(): void {
        $data = $this->get_test_data();
        $data->grade_items = [];
        $config = $this->get_transformer_config();
        $config['repo'] = new \src\transformer\repos\TestRepository($data);

        $this->assertSame([], \src\transformer\events\core\user_graded($config, $this->get_event()));
    }

    /**
     * Assignment feedback from the feedback plugin is counted when grade feedback is empty.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_assignment_comment_marks_feedback_present(): void {
        $data = $this->get_test_data();
        $data->grade_grades[0]->feedback = '';
        $data->assign_grades = [(object) [
            'id' => 11,
            'assignment' => 1,
            'userid' => 2,
            'attemptnumber' => 0,
        ]];
        $data->assignfeedback_comments = [(object) [
            'id' => 1,
            'assignment' => 1,
            'grade' => 11,
            'commenttext' => '<p>Private feedback text</p>',
        ]];
        $config = $this->get_transformer_config();
        $config['event_function'] = '\\src\\transformer\\events\\core\\user_graded';
        $config['repo'] = new \src\transformer\repos\TestRepository($data);

        $statements = \src\transformer\events\core\user_graded($config, $this->get_event());

        $this->assertTrue($statements[0]['result']['extensions']['https://w3id.org/xapi/ocsw/extensions/feedback_present']);
        $this->assertArrayNotHasKey('response', $statements[0]['result']);
    }

    /**
     * A blind-marked assignment uses an anonymous actor for the gradebook event.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_blindmarked_assignment_hides_learner_actor(): void {
        $statements = $this->get_blindmarked_statements(['anonymousevents' => 'anonymise']);

        $this->assertSame('Anonymous Course Participant', $statements[0]['actor']['name']);
        $this->assertSame('anonymous', $statements[0]['actor']['account']['name']);
    }

    /**
     * A blind-marked assignment is omitted when anonymous events are configured to be skipped.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_blindmarked_assignment_is_skipped_when_configured(): void {
        $this->assertSame([], $this->get_blindmarked_statements(['anonymousevents' => 'skip']));
    }

    /**
     * Grading feedback text is sent only when enabled and is redacted by default.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_enabled_feedback_text_is_redacted(): void {
        $statement = $this->get_assignment_feedback_statement([
            'send_content' => true,
            'send_grading_feedback' => true,
            'redact_free_text' => true,
        ]);

        $this->assertSame('[name] [name] [email]', $statement['result']['response']);
        $this->assertTrue($statement['result']['extensions']['https://w3id.org/xapi/ocsw/extensions/feedback_present']);
    }

    /**
     * The master Send content setting still omits feedback text.
     *
     * @covers ::user_graded
     * @return void
     */
    public function test_send_content_off_omits_enabled_feedback_text(): void {
        $statement = $this->get_assignment_feedback_statement([
            'send_content' => false,
            'send_grading_feedback' => true,
            'redact_free_text' => true,
        ]);

        $this->assertArrayNotHasKey('response', $statement['result']);
        $this->assertTrue($statement['result']['extensions']['https://w3id.org/xapi/ocsw/extensions/feedback_present']);
    }

    /**
     * Transform an assignment feedback fixture with the requested settings.
     *
     * @param array $overrides Transformer setting overrides.
     * @return array
     */
    private function get_assignment_feedback_statement(array $overrides): array {
        $data = $this->get_test_data();
        $data->grade_grades[0]->feedback = '';
        $data->assign_grades = [(object) [
            'id' => 11,
            'assignment' => 1,
            'userid' => 2,
            'attemptnumber' => 0,
        ]];
        $data->assignfeedback_comments = [(object) [
            'id' => 1,
            'assignment' => 1,
            'grade' => 11,
            'commenttext' => '<p>Aya Uchiha a.b@example.org</p>',
        ]];
        $overrides['repo'] = new \src\transformer\repos\TestRepository($data);
        $statements = $this->get_statement_results($overrides);
        return reset($statements);
    }

    /**
     * Run the gradebook fixture with blind marking enabled on its assignment.
     *
     * @param array $overrides Transformer setting overrides.
     * @return array
     */
    private function get_blindmarked_statements(array $overrides): array {
        $data = $this->get_test_data();
        $data->assign[0]->blindmarking = true;
        $overrides['repo'] = new \src\transformer\repos\TestRepository($data);
        return $this->get_statement_results($overrides);
    }
}
