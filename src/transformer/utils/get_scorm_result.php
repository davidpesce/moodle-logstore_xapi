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
 * Transformer utility for retrieving the SCORM result.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for retrieving the SCORM result.
 *
 * Reads the score bounds from SCORM 1.2 (cmi.core.score.*) or SCORM 2004 (cmi.score.*)
 * elements. xAPI requires the raw score to lie between min and max when they are given, and
 * an LRS rejects the statement (and its whole batch) otherwise, so bounds that the content's
 * own raw score falls outside are left out, along with the scaled score derived from them.
 *
 * @param array $scormscoestracks An array of the SCORM tracks
 * @param float $rawscore The score of the event.
 * @return array
 */
function get_scorm_result(array $scormscoestracks, float $rawscore) {
    $maxscore = null;
    $minscore = null;

    foreach ($scormscoestracks as $st) {
        if (in_array($st->element, ['cmi.core.score.min', 'cmi.score.min'], true) && is_numeric($st->value)) {
            $minscore = floatval($st->value);
        } else if (in_array($st->element, ['cmi.core.score.max', 'cmi.score.max'], true) && is_numeric($st->value)) {
            $maxscore = floatval($st->value);
        }
    }

    if ($minscore !== null && $rawscore < $minscore) {
        $minscore = null;
    }
    if ($maxscore !== null && $rawscore > $maxscore) {
        $maxscore = null;
    }
    // The spec requires max to be greater than min.
    if ($minscore !== null && $maxscore !== null && $maxscore <= $minscore) {
        $minscore = $maxscore = null;
    }

    $score = ['raw' => $rawscore];
    if ($minscore !== null) {
        $score['min'] = $minscore;
    }
    if ($maxscore !== null) {
        $score['max'] = $maxscore;
    }
    if ($minscore !== null && $maxscore !== null) {
        $score['scaled'] = get_scaled_score($rawscore, $minscore, $maxscore);
    }
    return ['score' => $score];
}
