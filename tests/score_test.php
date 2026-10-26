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
 * Unit tests for the result.score built from a raw score and its bounds.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\get_score
 * @covers    ::\src\transformer\utils\get_scorm_result
 */
final class score_test extends \basic_testcase {
    /**
     * A score with a range has its bounds and scaled score, and the raw score is capped.
     *
     * @return void
     */
    public function test_range_gives_bounds_and_scaled(): void {
        $this->assertEquals(['raw' => 5.0, 'min' => 0.0, 'max' => 10.0, 'scaled' => 0.5], utils\get_score(5, 0, 10));
        $this->assertEquals(['raw' => 10.0, 'min' => 0.0, 'max' => 10.0, 'scaled' => 1.0], utils\get_score(12, 0, 10));
    }

    /**
     * xAPI requires max > min, so a score without a range is sent as the raw score alone.
     *
     * @return void
     */
    public function test_no_range_gives_raw_only(): void {
        $this->assertSame(['raw' => 0.0], utils\get_score(0, 0, 0));
        $this->assertSame(['raw' => 3.0], utils\get_score(3, 5, 5));
        $this->assertSame(['raw' => 3.0], utils\get_score(3, 10, 0));
    }

    /**
     * SCORM bounds that are equal are both left out, with the scaled score.
     *
     * @return void
     */
    public function test_scorm_equal_bounds_are_left_out(): void {
        $tracks = [
            (object) ['element' => 'cmi.core.score.min', 'value' => '0'],
            (object) ['element' => 'cmi.core.score.max', 'value' => '0'],
        ];
        $this->assertSame(['score' => ['raw' => 0.0]], utils\get_scorm_result($tracks, 0));
    }
}
