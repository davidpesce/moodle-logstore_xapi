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
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

use src\transformer\utils\quiz_question;
use src\transformer\utils\get_activity\definition\question;

/**
 * Unit tests for reading matching question attempts from their steps.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\quiz_question\get_matching_attempt
 * @covers    ::\src\transformer\utils\quiz_question\get_match_subquestions
 * @covers    ::\src\transformer\utils\get_activity\definition\question\get_match_definition
 */
final class matching_attempt_test extends \basic_testcase {
    /**
     * A config with a match question (Red → Green, Blue → Orange) and the given attempt steps.
     *
     * @param array $steps Step data, one array of name => value per step.
     * @return array
     */
    private function config(array $steps): array {
        $steprows = [];
        $datarows = [];
        foreach ($steps as $i => $data) {
            $steprows[] = (object) ['id' => $i + 1, 'questionattemptid' => 1, 'sequencenumber' => $i];
            foreach ($data as $name => $value) {
                $datarows[] = (object) [
                    'id' => count($datarows) + 1,
                    'attemptstepid' => $i + 1,
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }
        return [
            'send_response_choices' => true,
            'repo' => new \src\transformer\repos\TestRepository((object) [
                'qtype_match_subquestions' => [
                    (object) ['id' => 1, 'questionid' => 1, 'questiontext' => '<p>Red</p>', 'answertext' => 'Green'],
                    (object) ['id' => 2, 'questionid' => 1, 'questiontext' => '<p>Blue</p>', 'answertext' => 'Orange'],
                    // Added after the attempt started, so not shown in it.
                    (object) ['id' => 3, 'questionid' => 1, 'questiontext' => '<p>Black</p>', 'answertext' => 'White'],
                ],
                'question_attempt_steps' => $steprows,
                'question_attempt_step_data' => $datarows,
            ]),
        ];
    }

    /**
     * The response is the latest one saved, mapped through the choice order shown.
     *
     * @return void
     */
    public function test_latest_response_is_used(): void {
        $config = $this->config([
            ['_stemorder' => '2,1', '_choiceorder' => '2,1'],
            ['sub0' => '2', 'sub1' => '2'],
            ['sub0' => '1', 'sub1' => '2'],
            ['-finish' => '1'],
        ]);
        $question = (object) ['id' => 1, 'qtype' => 'match'];

        $attempt = quiz_question\get_matching_attempt($config, (object) ['id' => 1], $question);

        // Blue was shown first and got choice 1 (Orange); Red got choice 2 (Green).
        $this->assertEquals([2, 1], array_keys($attempt['stems']));
        $this->assertEquals([2 => 2, 1 => 1], $attempt['response']);
        // Black was not shown, so it is not part of the right answer.
        $this->assertEquals([1 => 1, 2 => 2], $attempt['right']);
        $this->assertArrayNotHasKey(3, $attempt['stems']);
    }

    /**
     * Without an attempt, a randomsamatch question has no stems or choices to list.
     *
     * @return void
     */
    public function test_randomsamatch_without_attempt(): void {
        $config = $this->config([]);
        $question = (object) ['id' => 1, 'qtype' => 'randomsamatch', 'name' => 'Q', 'questiontext' => 'Match'];

        $definition = question\get_match_definition($config, $question, 'en');

        $this->assertSame('matching', $definition['interactionType']);
        $this->assertArrayNotHasKey('source', $definition);
        $this->assertArrayNotHasKey('target', $definition);
        $this->assertArrayNotHasKey('correctResponsesPattern', $definition);
    }
}
