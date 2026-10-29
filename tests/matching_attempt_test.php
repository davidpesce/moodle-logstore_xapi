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
 * @covers    ::\src\transformer\utils\quiz_question\get_match_stem_text
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

    /**
     * A stem that is an image alone is a stem, as in qtype_match: it stands for its alt text or
     * file name, and an attempt with every pair right matches the right answer.
     *
     * @return void
     */
    public function test_image_stems(): void {
        $config = [
            'send_response_choices' => true,
            'repo' => new \src\transformer\repos\TestRepository((object) [
                'qtype_match_subquestions' => [
                    (object) ['id' => 1, 'questionid' => 1, 'answertext' => 'Cat',
                        'questiontext' => '<p><img src="@@PLUGINFILE@@/cat.png" alt="A small cat"></p>'],
                    (object) ['id' => 2, 'questionid' => 1, 'answertext' => 'Dog',
                        'questiontext' => '<p><img src="@@PLUGINFILE@@/dog%20photo.jpg" alt=""></p>'],
                    (object) ['id' => 3, 'questionid' => 1, 'questiontext' => '', 'answertext' => 'Fish'],
                ],
                'question_attempt_steps' => [
                    (object) ['id' => 1, 'questionattemptid' => 1, 'sequencenumber' => 0],
                    (object) ['id' => 2, 'questionattemptid' => 1, 'sequencenumber' => 1],
                ],
                'question_attempt_step_data' => [
                    (object) ['id' => 1, 'attemptstepid' => 1, 'name' => '_stemorder', 'value' => '1,2'],
                    (object) ['id' => 2, 'attemptstepid' => 1, 'name' => '_choiceorder', 'value' => '1,2,3'],
                    (object) ['id' => 3, 'attemptstepid' => 2, 'name' => 'sub0', 'value' => '1'],
                    (object) ['id' => 4, 'attemptstepid' => 2, 'name' => 'sub1', 'value' => '2'],
                ],
            ]),
        ];
        $question = (object) ['id' => 1, 'qtype' => 'match'];

        $attempt = quiz_question\get_matching_attempt($config, (object) ['id' => 1], $question);

        $this->assertSame([1 => 'A small cat', 2 => 'dog photo.jpg'], $attempt['stems']);
        $this->assertEquals($attempt['right'], $attempt['response']);
    }

    /**
     * A stem's text without HTML, or for an image alone its alt text, file name or a hash.
     *
     * @return void
     */
    public function test_match_stem_text(): void {
        $this->assertSame('Red', quiz_question\get_match_stem_text('<p>Red</p>'));
        $this->assertSame('', quiz_question\get_match_stem_text(''));
        $this->assertSame('Tom & Jerry', quiz_question\get_match_stem_text('<img alt="Tom &amp; Jerry" src="x.png">'));
        $this->assertSame('x.png', quiz_question\get_match_stem_text('<img src="https://e.org/pluginfile.php/1/x.png?t=1">'));
        $this->assertMatchesRegularExpression('/^stem-[0-9a-f]{12}$/', quiz_question\get_match_stem_text('<p><br></p>'));
    }
}
