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
 * Transformer utility for retrieving the right answers to a numerical question.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\quiz_question;

/**
 * The answers that get full marks in a numerical question, as ranges.
 *
 * Any of them is right on its own. An answer of * accepts every number, which a range cannot
 * say, so a question with one has no ranges to send.
 *
 * @param array $config The transformer config settings.
 * @param int $questionid The question id.
 * @return array A ['min' => float, 'max' => float, 'target' => float] range for each answer.
 */
function get_numerical_answers(array $config, int $questionid): array {
    $repo = $config['repo'];
    $ranges = [];
    foreach ($repo->read_records('question_answers', ['question' => $questionid], 'id ASC') as $answer) {
        if ((float) $answer->fraction < 1.0) {
            continue;
        }
        if (!is_numeric($answer->answer)) {
            return [];
        }
        $options = $repo->read_records('question_numerical', ['answer' => $answer->id]);
        $option = reset($options);
        $target = (float) $answer->answer;
        $tolerance = $option ? (float) $option->tolerance : 0.0;
        $ranges[] = [
            'min' => $target - $tolerance,
            'max' => $target + $tolerance,
            'target' => $target,
        ];
    }
    return $ranges;
}
