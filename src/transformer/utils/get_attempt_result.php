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
 * Transformer utility to determine the attempt result.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility to determine the attempt result.
 *
 * The statement's object is the attempt, so the score is the attempt's own: its sum of marks
 * rescaled to the quiz's maximum grade, as quiz_rescale_grade() does. The gradebook grade is the
 * user's overall quiz grade after the grading method (highest, average, first or last attempt)
 * and can come from another attempt. An attempt that still needs manual grading has no total
 * yet, so it is sent without a score or success.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $attempt The quiz attempt.
 * @param \stdClass $quiz The quiz.
 * @param ?\stdClass $gradeitem The quiz's grade item, for the pass grade.
 * @return array
 */
function get_attempt_result(array $config, \stdClass $attempt, \stdClass $quiz, ?\stdClass $gradeitem) {
    $result = [
        'completion' => isset($attempt->state) && $attempt->state === 'finished',
    ];

    if (isset($attempt->sumgrades)) {
        $maxgrade = (float) ($quiz->grade ?? 0);
        $quizsumgrades = (float) ($quiz->sumgrades ?? 0);
        $grade = $quizsumgrades >= 0.000005 ? (float) $attempt->sumgrades * $maxgrade / $quizsumgrades : 0.0;

        $result['score'] = get_score($grade, 0.0, $maxgrade);
        $success = get_success($result['score']['raw'], $gradeitem);
        if (!is_null($success)) {
            $result['success'] = $success;
        }
    }

    $duration = get_attempt_duration($attempt);
    if ($duration != null) {
        $result['duration'] = $duration;
    }

    return $result;
}
