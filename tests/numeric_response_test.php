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
 * Unit tests for reading the number out of a numerical answer.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\get_numeric_response
 */
final class numeric_response_test extends \basic_testcase {
    /**
     * Units are dropped and decimal commas become points.
     *
     * @return void
     */
    public function test_numbers_are_read(): void {
        $cases = [
            'plain' => ['11', '11'],
            'negative decimal' => ['-2.5', '-2.5'],
            'unit after' => ['5 m', '5'],
            'unit after, no space' => ['5m', '5'],
            'unit before' => ['$5', '5'],
            'decimal comma' => ['12,5 m', '12.5'],
            'thousands comma' => ['1,000', '1000'],
            'thousands comma and decimal point' => ['1,234.5', '1234.5'],
            'thousands point and decimal comma' => ['1.234,5', '1234.5'],
            'thousands points' => ['1.234.567', '1234567'],
            'exponent' => ['1.5e3 kg', '1.5e3'],
            'leading point' => ['.5', '.5'],
        ];
        foreach ($cases as $name => [$answer, $expected]) {
            $this->assertSame($expected, utils\get_numeric_response($answer), $name);
        }
    }

    /**
     * An answer with no number gives an empty response.
     *
     * @return void
     */
    public function test_no_number_gives_empty(): void {
        $this->assertSame('', utils\get_numeric_response(null));
        $this->assertSame('', utils\get_numeric_response(''));
        $this->assertSame('', utils\get_numeric_response('ten'));
    }
}
