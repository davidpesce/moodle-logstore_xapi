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
 * Unit tests for the scaled score sent as xAPI result.score.scaled.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\get_scaled_score
 */
final class scaled_score_test extends \basic_testcase {
    /**
     * Grades (minimum 0 or more) scale to the fraction of the range, so LRS reports read them as
     * percentages: 50 out of 100 is 0.5.
     *
     * @return void
     */
    public function test_grades_scale_from_zero_to_one(): void {
        $cases = [
            'nothing' => [0, 0, 100, 0.0],
            'half' => [50, 0, 100, 0.5],
            'three quarters' => [7.5, 0, 10, 0.75],
            'full marks' => [100, 0, 100, 1.0],
            'non-zero minimum' => [6, 2, 10, 0.5],
        ];
        foreach ($cases as $name => [$raw, $min, $max, $expected]) {
            $this->assertEqualsWithDelta($expected, utils\get_scaled_score($raw, $min, $max), 1e-9, $name);
        }
    }

    /**
     * With a negative minimum, 0 still means no score and negative scores scale towards -1.
     *
     * @return void
     */
    public function test_negative_scores_scale_towards_minus_one(): void {
        $cases = [
            'no score' => [0, -10, 10, 0.0],
            'positive' => [5, -10, 10, 0.5],
            'negative' => [-5, -10, 10, -0.5],
            'lowest' => [-10, -10, 10, -1.0],
        ];
        foreach ($cases as $name => [$raw, $min, $max, $expected]) {
            $this->assertEqualsWithDelta($expected, utils\get_scaled_score($raw, $min, $max), 1e-9, $name);
        }
    }

    /**
     * The result always stays within xAPI's -1..1, and an empty range does not divide by zero.
     *
     * @return void
     */
    public function test_result_is_within_range(): void {
        $this->assertSame(0.0, utils\get_scaled_score(5, 5, 5));
        $this->assertSame(1.0, utils\get_scaled_score(120, 0, 100));
        $this->assertSame(-1.0, utils\get_scaled_score(-500, 0, 100));
        $this->assertSame(-1.0, utils\get_scaled_score(-20, -10, 10));
    }
}
