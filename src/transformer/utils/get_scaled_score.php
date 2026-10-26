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
 * Transformer utility for determining the scaled score.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 *            Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for determining the scaled score.
 *
 * xAPI's result.score.scaled comes from SCORM 2004: 0 means no score, positive values are the
 * fraction of the maximum, and the range down to -1 exists for negative scores. LRS reports read
 * it that way (0.5 is 50%), so:
 *
 * - when the minimum is 0 or more (every Moodle grade), it is (raw - min) / (max - min), from
 *   0 to 1;
 * - when the minimum is negative, a positive raw score is divided by the maximum and a negative
 *   one by the size of the minimum, so 0 still means no score.
 *
 * The result is kept within -1..1, as xAPI requires.
 *
 * @param float $rawscore The rawscore of the event.
 * @param float $minscore The minimum score of the event.
 * @param float $maxscore The maximum score of the event.
 * @return float
 */
function get_scaled_score($rawscore, $minscore, $maxscore) {
    $rawscore = (float) $rawscore;
    $minscore = (float) $minscore;
    $maxscore = (float) $maxscore;

    if ($minscore >= 0) {
        if ($maxscore <= $minscore) {
            return 0.0;
        }
        $scaled = ($rawscore - $minscore) / ($maxscore - $minscore);
    } else if ($rawscore >= 0) {
        $scaled = $maxscore > 0 ? $rawscore / $maxscore : 0.0;
    } else {
        $scaled = $rawscore / abs($minscore);
    }

    return max(-1.0, min(1.0, $scaled));
}
