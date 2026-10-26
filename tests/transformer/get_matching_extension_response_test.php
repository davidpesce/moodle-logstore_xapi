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

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

/**
 * Unit tests for the matching response extension (see issue #892).
 *
 * @package   logstore_xapi
 * @copyright 2026 taki-0105a
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \src\transformer\utils\result\get_matching_extension_response
 */
final class get_matching_extension_response_test extends advanced_testcase {
    /**
     * Call the utility with the given response summary.
     *
     * @param ?string $responsesummary The question attempt response summary.
     * @return \stdClass
     */
    private function build_object(?string $responsesummary): \stdClass {
        $questionattempt = (object) ['responsesummary' => $responsesummary];
        return \src\transformer\utils\result\get_matching_extension_response([], $questionattempt, 'en');
    }

    /**
     * Call the utility and return the selections as an array.
     *
     * @param ?string $responsesummary The question attempt response summary.
     * @return array
     */
    private function build(?string $responsesummary): array {
        return (array) $this->build_object($responsesummary);
    }

    /**
     * Keys are the source ids of the definition, values are the selected answers.
     *
     * @return void
     */
    public function test_keys_are_source_ids(): void {
        $this->assertSame(
            ['red' => 'Green', 'blue' => 'Orange'],
            $this->build("Red\n -> Green; Blue\n -> Orange")
        );
    }

    /**
     * Dots in the question text never reach the keys, so Elasticsearch-backed
     * LRSs do not read them as nested field paths.
     *
     * @return void
     */
    public function test_keys_have_no_dots(): void {
        $selections = $this->build("Red...\n -> Green; Blue.sky\n -> Orange; Black.. and white\n -> Wait...");

        $this->assertSame(
            ['red' => 'Green', 'bluesky' => 'Orange', 'black-and-white' => 'Wait...'],
            $selections
        );
        foreach (array_keys($selections) as $key) {
            $this->assertStringNotContainsString('.', $key);
        }
    }

    /**
     * Keys are the same ids as object.definition.source[].id.
     *
     * @return void
     */
    public function test_keys_match_definition_source_ids(): void {
        $stems = ['Red...', 'Blue.sky'];
        $definition = \src\transformer\utils\get_activity\definition\cmi\matching(
            ['send_response_choices' => true],
            'name',
            null,
            $stems,
            ['Green', 'Orange'],
            'en'
        );
        $selections = $this->build("Red...\n -> Green; Blue.sky\n -> Orange");

        $this->assertSame(array_column($definition['source'], 'id'), array_keys($selections));
    }

    /**
     * No response gives no selections.
     *
     * @return void
     */
    public function test_no_response(): void {
        $this->assertSame([], $this->build(null));
        $this->assertSame([], $this->build(''));
    }

    /**
     * The extension always encodes as a JSON object, even when the ids are 0, 1, 2...
     * or there is no response.
     *
     * @return void
     */
    public function test_encodes_as_json_object(): void {
        $this->assertSame('{"0":"Zero","1":"One"}', json_encode($this->build_object("0.\n -> Zero; 1.\n -> One")));
        $this->assertSame('{}', json_encode($this->build_object(null)));
    }
}
