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
 * Transformer utility for retrieving (lesson question page) activities.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * Transformer utility for retrieving (lesson question page) activities.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course object.
 * @param \stdClass $lesson The lesson object.
 * @param \stdClass $page The lesson page object.
 * @param int $cmid course module id
 * @return array
 */
function lesson_question_page(array $config, \stdClass $course, \stdClass $lesson, \stdClass $page, int $cmid) {
    $repo = $config['repo'];
    $courselang = utils\get_course_lang($course);

    $entryurl = $config['app_url'] . '/mod/lesson/view.php?id=' . $cmid . '&pageid=' . $page->id;

    $activity = [
        ...base(),
        'id' => $entryurl,
    ];

    $answers = $repo->read_records('lesson_answers', ['pageid' => $page->id]);
    $correctanswers = array_filter($answers, function ($a) use ($config, $lesson, $page) {
        return utils\is_lesson_answer_correct($config, $lesson, $page, $a);
    });

    switch ($page->qtype) {
        case 1: // LESSON_PAGE_SHORTANSWER.
            // With qoption set the answers are regular expressions, not responses, so no correct
            // response is sent. Otherwise each correct answer is an alternative.
            $correctresponses = !empty($page->qoption) ? null : array_values(
                array_map(
                    function ($answer) {
                        return utils\get_string_html_removed($answer->answer);
                    },
                    $correctanswers
                )
            );
            $activity['definition'] = utils\get_activity\definition\cmi\fill_in(
                $config,
                $page->title,
                utils\get_string_html_removed($page->contents),
                $courselang,
                $correctresponses ?: null
            );
            break;
        case 10: // LESSON_PAGE_ESSAY.
            $activity['definition'] = utils\get_activity\definition\cmi\long_fill_in(
                $config,
                $page->title,
                utils\get_string_html_removed($page->contents),
                $courselang
            );
            break;
        case 2: // LESSON_PAGE_TRUEFALSE.
        case 3: // LESSON_PAGE_MULTICHOICE.
            $choices = array_values(
                array_map(
                    function ($answer) {
                        return utils\get_string_html_removed($answer->answer);
                    },
                    $answers
                )
            );
            $correctchoices = array_values(
                array_map(
                    function ($answer) {
                        return utils\get_string_html_removed($answer->answer);
                    },
                    $correctanswers
                )
            );
            // A multiple-answer page (multichoice with qoption set) needs all the correct answers
            // together; otherwise each correct answer is right on its own.
            $multianswer = $page->qtype == 3 && !empty($page->qoption);
            $activity['definition'] = utils\get_activity\definition\cmi\choice(
                $config,
                $page->title,
                utils\get_string_html_removed($page->contents),
                $choices,
                $courselang,
                $multianswer ? [$correctchoices] : array_map(function ($choice) {
                    return [$choice];
                }, $correctchoices)
            );
            break;
        case 5: // LESSON_PAGE_MATCHING.
            // A pair is an answer and its matching response. The page's correct and wrong answer
            // feedback is also stored as answers, with no response, so it is left out.
            $pairs = [];
            foreach ($answers as $a) {
                $answertext = utils\get_string_html_removed($a->answer);
                $responsetext = utils\get_string_html_removed($a->response);
                if ($answertext !== '' && $responsetext !== '') {
                    $pairs[] = [$answertext, $responsetext];
                }
            }
            $activity['definition'] = utils\get_activity\definition\cmi\matching(
                $config,
                $page->title,
                utils\get_string_html_removed($page->contents),
                array_column($pairs, 0),
                array_column($pairs, 1),
                $pairs,
                $courselang
            );
            break;
        case 8: // LESSON_PAGE_NUMERICAL.
            // Each correct answer is an alternative correct response. A lesson answer is a
            // number or a "min:max" range.
            $patterns = array_values(array_map(function ($answer) {
                $correct = trim(utils\get_string_html_removed($answer->answer));
                if (strpos($correct, ':') !== false) {
                    [$min, $max] = array_map('trim', explode(':', $correct, 2));
                    return utils\get_activity\definition\cmi\numeric_pattern($min, $max);
                }
                return utils\get_activity\definition\cmi\numeric_pattern(null, null, $correct);
            }, $correctanswers));
            $activity['definition'] = utils\get_activity\definition\cmi\numeric(
                $config,
                $page->title,
                utils\get_string_html_removed($page->contents),
                $courselang,
                $patterns
            );
            break;
    }
    return $activity;
}
