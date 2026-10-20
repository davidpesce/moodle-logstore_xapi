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

use src\transformer\utils;

/**
 * Unit tests for the slugify utility (see issue #894).
 *
 * @package   logstore_xapi
 * @copyright 2026 taki-0105a
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\slugify
 */
final class slugify_test extends \basic_testcase {

    /**
     * ASCII input keeps producing the same slugs as before.
     *
     * @return array
     */
    public static function ascii_provider(): array {
        return [
            'simple words' => ['Hello World', 'hello-world'],
            'underscores and spaces' => ["foo_bar  baz", 'foo-bar-baz'],
            'punctuation is removed' => ['Answer: 42!', 'answer-42'],
            'leading and trailing dashes are trimmed' => ['  -edge- ', 'edge'],
            'empty string stays empty' => ['', ''],
        ];
    }

    /**
     * Test that ASCII slugs are unchanged.
     *
     * @dataProvider ascii_provider
     * @param string $input The input string.
     * @param string $expected The expected slug.
     */
    public function test_ascii_is_unchanged(string $input, string $expected): void {
        $this->assertSame($expected, utils\slugify($input));
    }

    /**
     * Non-ASCII letters and digits are kept.
     */
    public function test_non_ascii_letters_and_digits_are_kept(): void {
        $this->assertSame('ありをりはべり', utils\slugify('ありをりはべり'));
        $this->assertSame('１１２', utils\slugify('１＋１＝２'));
        $this->assertSame('１１３', utils\slugify('１＋１＝３'));
        $this->assertSame('café-crème', utils\slugify('Café Crème'));
    }

    /**
     * Answers made only of symbols get a non-empty, stable and distinct id.
     */
    public function test_symbol_only_answers_get_distinct_ids(): void {
        $circle = utils\slugify('○');
        $cross = utils\slugify('×');

        $this->assertNotSame('', $circle);
        $this->assertNotSame('', $cross);
        $this->assertNotSame($circle, $cross);
        // The same input always gives the same id, so definitions and responses match.
        $this->assertSame($circle, utils\slugify('○'));
    }
}
