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
 * Transformer utility for building an xAPI result score.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Build result.score from a raw score and its bounds.
 *
 * xAPI requires max to be greater than min. A grade item with no range (a maximum grade of 0,
 * or min equal to max) has no meaningful bounds or scaled score, so only the raw score is sent.
 * Otherwise the raw score is capped to the bounds and the scaled score is added.
 *
 * @param float $raw The raw score.
 * @param float $min The minimum score.
 * @param float $max The maximum score.
 * @return array The score: raw, plus min, max and scaled when max > min.
 */
function get_score(float $raw, float $min, float $max): array {
    if ($max <= $min) {
        return ['raw' => $raw];
    }
    $raw = cap_raw_score($raw, $min, $max);
    return [
        'raw' => $raw,
        'min' => $min,
        'max' => $max,
        'scaled' => get_scaled_score($raw, $min, $max),
    ];
}
