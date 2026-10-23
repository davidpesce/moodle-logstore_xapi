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
 * question completion object.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for retrieving the result/success state from a lesson
 * question completion object.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $lesson The lesson object.
 * @param \stdClass $page The lesson question page object.
 * @param int $userid User ID who completed the lesson
 * @return object
 */
function get_lesson_question_result(array $config, \stdClass $lesson, \stdClass $page, int $userid) {
    $repo = $config['repo'];
    $result = [];
    // Response and success if true.
    $attempts = $repo->read_records('lesson_attempts', [
        'lessonid' => $lesson->id,
        'pageid' => $page->id,
        'userid' => $userid,
    ], 'timeseen DESC');
    if (!empty($attempts)) {
        $attempt = reset($attempts);
        if ($page->qtype == 10) { // LESSON_PAGE_ESSAY.
            // Essay is graded later, and is also serialized into an object.
            $essay = decode_other($attempt->useranswer);
            $result['response'] = get_string_html_removed($essay->answer);
        } else if ($page->qtype == 5) { // LESSON_PAGE_MATCHING.
            // Matching is the tricky one because the stored response is
            // nothing like the xapi expectation. We need to merge the answers
            // with the responses.
            $answers = $repo->read_records('lesson_answers', [
                'pageid' => $page->id,
            ], 'id ASC');

            $useranswers = explode(",", $attempt->useranswer);

            $responses = [];
            foreach ($answers as $ans) {
                if (!is_null($ans->response)) {
                    array_push(
                        $responses,
                        slugify(get_string_html_removed($ans->answer)) .
                        "[.]" .
                        slugify(array_shift($useranswers)),
                    );
                }
            }
            $result['success'] = ($attempt->correct == 1);
            $result['response'] = implode("[,]", $responses);
        } else if ($page->qtype == 2 || $page->qtype == 3) { // LESSON_PAGE_TRUEFALSE, LESSON_PAGE_MULTICHOICE.
            // A choice response is the ids of the selected choices, built from the answer text as
            // the definition's choice ids are. True/false and single-answer pages store the answer
            // text; a multiple-answer page (qoption set) stores comma-separated lesson_answers ids.
            $result['success'] = ($attempt->correct == 1);
            if ($page->qtype == 3 && !empty($page->qoption)) {
                $answertexts = [];
                foreach ($repo->read_records('lesson_answers', ['pageid' => $page->id]) as $ans) {
                    $answertexts[$ans->id] = get_string_html_removed($ans->answer);
                }
                $selected = [];
                foreach (explode(',', (string) $attempt->useranswer) as $answerid) {
                    if (isset($answertexts[(int) $answerid])) {
                        $selected[] = slugify($answertexts[(int) $answerid]);
                    }
                }
                $result['response'] = implode('[,]', $selected);
            } else {
                $result['response'] = slugify(get_string_html_removed($attempt->useranswer));
            }
        } else {
            // Other questions know if they are correct or not immediately.
            $result['success'] = ($attempt->correct == 1);
            $result['response'] = get_string_html_removed($attempt->useranswer);
        }
    }
    return $result;
}
