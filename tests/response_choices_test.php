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

use src\transformer\utils\get_activity\definition\cmi;

/**
 * Unit tests for "Send response choices": every interaction type leaves out its possible and
 * correct answers when the setting is off, and sends them when it is on.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\get_activity\definition\cmi\choice
 * @covers    ::\src\transformer\utils\get_activity\definition\cmi\sequencing
 * @covers    ::\src\transformer\utils\get_activity\definition\cmi\fill_in
 * @covers    ::\src\transformer\utils\get_activity\definition\cmi\true_false
 * @covers    ::\src\transformer\utils\get_activity\definition\cmi\numeric
 * @covers    ::\src\transformer\utils\get_activity\definition\cmi\matching
 */
final class response_choices_test extends \basic_testcase {
    /** @var string[] The definition properties that hold possible or correct answers. */
    const ANSWER_PROPERTIES = ['choices', 'correctResponsesPattern', 'source', 'target'];

    /**
     * Build one definition of each interaction type, each with a correct answer.
     *
     * @param bool $sendresponsechoices The setting.
     * @return array Definitions keyed by interaction type.
     */
    private function get_definitions(bool $sendresponsechoices): array {
        $config = ['send_response_choices' => $sendresponsechoices];
        return [
            'choice' => cmi\choice($config, 'Q', 'Pick one', ['Red', 'Blue'], 'en', [['Red']]),
            'sequencing' => cmi\sequencing($config, 'Q', 'Order', ['One', 'Two'], 'en', ['One', 'Two']),
            'fill-in' => cmi\fill_in($config, 'Q', 'Capital of France', 'en', ['Paris']),
            'true-false' => cmi\true_false($config, 'Q', 'The sky is blue', 'en', true),
            'numeric exact' => cmi\numeric($config, 'Q', '2 + 2', 'en', [cmi\numeric_pattern(null, null, '4')]),
            'numeric range' => cmi\numeric($config, 'Q', 'About 3', 'en', [cmi\numeric_pattern('2.5', '3.5')]),
            'matching' => cmi\matching(
                $config,
                'Q',
                'Match',
                ['Cat', 'Dog'],
                ['Meow', 'Woof'],
                [['Cat', 'Meow'], ['Dog', 'Woof']],
                'en'
            ),
        ];
    }

    /**
     * With the setting off, no interaction type sends its possible or correct answers.
     *
     * @return void
     */
    public function test_off_sends_no_answers(): void {
        foreach ($this->get_definitions(false) as $type => $definition) {
            foreach (self::ANSWER_PROPERTIES as $property) {
                $this->assertArrayNotHasKey($property, $definition, "$type sends $property");
            }
            $this->assertArrayHasKey('interactionType', $definition, $type);
        }
    }

    /**
     * With the setting on, every interaction type sends its correct answer.
     *
     * @return void
     */
    public function test_on_sends_correct_answers(): void {
        $expected = [
            'choice' => ['red'],
            'sequencing' => ['one[,]two'],
            'fill-in' => ['Paris'],
            'true-false' => ['true'],
            'numeric exact' => ['4'],
            'numeric range' => ['2.5[:]3.5'],
            'matching' => ['cat[.]meow[,]dog[.]woof'],
        ];
        $definitions = $this->get_definitions(true);
        foreach ($expected as $type => $pattern) {
            $this->assertSame($pattern, $definitions[$type]['correctResponsesPattern'], $type);
        }
        $this->assertCount(2, $definitions['choice']['choices']);
        $this->assertCount(2, $definitions['matching']['source']);
        $this->assertCount(2, $definitions['matching']['target']);
    }
}
