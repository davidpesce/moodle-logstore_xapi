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
 * Transformer utility for reading the answers a multiple-choice question attempt selected.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\quiz_question;

/**
 * The answers a multiple-choice question attempt selected, from its steps.
 *
 * The first step records the order the answers were shown in (_order, question_answers ids). A
 * single-answer question saves the position of the selected answer in that order as "answer", -1
 * when the choice was cleared. A multiple-answer question saves choice0, choice1, … for the
 * positions, 1 when selected.
 *
 * The text summary in question_attempts.responsesummary is not used: core makes it from the
 * formatted answer, which is not the text the choice ids are made from, and joins several answers
 * with "; ", which an answer can contain.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $questionattempt The question attempt.
 * @return array The selected question_answers ids, in the order they were shown.
 */
function get_multichoice_selection(array $config, \stdClass $questionattempt): array {
    $steps = get_question_attempt_step_data($config, $questionattempt);
    $first = reset($steps) ?: [];
    $order = array_values(array_filter(explode(',', $first['_order'] ?? '')));

    foreach (array_reverse($steps) as $data) {
        if (array_key_exists('answer', $data)) {
            $position = (int) $data['answer'];
            return ($data['answer'] !== '' && isset($order[$position])) ? [$order[$position]] : [];
        }
        if (preg_grep('/^choice\d+$/', array_keys($data))) {
            $selected = [];
            foreach ($order as $position => $answerid) {
                if (!empty($data['choice' . $position])) {
                    $selected[] = $answerid;
                }
            }
            return $selected;
        }
    }
    return [];
}
