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
 * Transformer utility for retrieving the result/success state from a lesson
 * essay question after grading.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for retrieving the result/success state from a lesson
 * essay question after grading.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $lesson The lesson object.
 * @param \stdClass $answer The answer object for the question.
 * @param \stdClass $attempt The question attempt object.
 * @return object
 */
function get_lesson_essay_result(array $config, \stdClass $lesson, \stdClass $answer, \stdClass $attempt) {

    $attemptdetail = decode_other($attempt->useranswer);

    $min = floatval(0);
    $max = floatval($answer->score);
    $raw = cap_raw_score(floatval($attemptdetail->score ?: 0), $min, $max);

    $result = [
        'response' => get_string_html_removed($attemptdetail->response),
        'score' => [
            'min' => $min,
            'max' => $max,
            'raw' => $raw,
            'scaled' => get_scaled_score($raw, $min, $max),
        ],
    ];
    return $result;
}
