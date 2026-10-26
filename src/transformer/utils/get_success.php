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

/**
 * Transformer utility for deciding result.success from a grade.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Decide result.success: whether a grade reaches the grade item's pass grade.
 *
 * With no pass grade set (0, or not above the minimum grade) every grade would pass, so there
 * is no pass or fail to report and null is returned. Leave success out of the result then.
 *
 * @param float $grade The grade, on the grade item's scale.
 * @param ?\stdClass $gradeitem The grade item.
 * @return ?bool Whether the grade passes, or null when there is no pass grade.
 */
function get_success(float $grade, ?\stdClass $gradeitem): ?bool {
    if (is_null($gradeitem)) {
        return null;
    }
    $pass = (float) ($gradeitem->gradepass ?? 0);
    $min = (float) ($gradeitem->grademin ?? 0);
    if ($pass <= $min) {
        return null;
    }
    return $grade >= $pass;
}
