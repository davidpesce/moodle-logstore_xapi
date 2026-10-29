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
 * Core saves one lesson_attempts row and fires one question_answered event for each answer, and
 * a page can take several answers in a retake, so the answer is the user's latest for the page at
 * the time of the event, not the latest when the queue is sent.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $lesson The lesson object.
 * @param \stdClass $page The lesson question page object.
 * @param \stdClass $event The question answered event.
 * @return array
 */
function get_lesson_question_result(array $config, \stdClass $lesson, \stdClass $page, \stdClass $event) {
    $repo = $config['repo'];
    $result = [];
    // Response and success if true.
    $attempts = $repo->read_records('lesson_attempts', [
        'lessonid' => $lesson->id,
        'pageid' => $page->id,
        'userid' => $event->userid,
    ], 'timeseen DESC, id DESC');
    $attempt = reset($attempts);
    foreach ($attempts as $candidate) {
        if ((int) $candidate->timeseen <= (int) $event->timecreated) {
            $attempt = $candidate;
            break;
        }
    }
    if ($attempt) {
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
            // A choice response is the ids of the selected choices, made from the same answers as
            // the definition's choice ids. True/false and single-answer pages store the selected
            // answer's id; a multiple-answer page (qoption set) stores comma-separated
            // lesson_answers ids in useranswer.
            $result['success'] = ($attempt->correct == 1);
            $ids = get_activity\definition\cmi\get_choice_ids(get_lesson_choices(
                $repo->read_records('lesson_answers', ['pageid' => $page->id], 'id ASC')
            ));
            $selected = ($page->qtype == 3 && !empty($page->qoption))
                ? explode(',', (string) $attempt->useranswer)
                : [$attempt->answerid];
            $result['response'] = implode('[,]', array_values(array_filter(array_map(function ($answerid) use ($ids) {
                return $ids[(int) $answerid] ?? null;
            }, $selected))));
        } else {
            // Other questions know if they are correct or not immediately.
            $result['success'] = ($attempt->correct == 1);
            $result['response'] = get_string_html_removed($attempt->useranswer);
        }
    }
    return $result;
}
