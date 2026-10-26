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
 * Transform for the quiz question (gapselect) answered event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_quiz\question_answered;

use src\transformer\utils;
use src\transformer\utils\get_activity\definition\question;

/**
 * Transformer for quiz question (gapselect) answered event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @param \stdClass $questionattempt The questionattempt object.
 * @param \stdClass $question The question object.
 * @return array
 */
function gapselect(array $config, \stdClass $event, \stdClass $questionattempt, \stdClass $question) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->relateduserid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $attempt = $repo->read_record('quiz_attempts', ['uniqueid' => $questionattempt->questionusageid]);
    $quiz = $repo->read_record_by_id('quiz', $attempt->quiz);
    $coursemodule = $repo->read_record_by_id('course_modules', $event->contextinstanceid);
    $lang = utils\get_course_lang($course);

    $result = [
        'response' => '',
        'completion' => false,
        'success' => false,
    ];

    if (!is_null($questionattempt->responsesummary) && $questionattempt->responsesummary !== '') {
        // The summary has one {choice} per gap, in order, and {} for a gap left empty. Each
        // filled gap is a gap-choice pair, with the same ids as the definition's sources and
        // targets.
        $selections = explode('} {', rtrim(ltrim($questionattempt->responsesummary, '{'), '}'));
        $pairs = [];
        $extension = [];
        foreach ($selections as $i => $selection) {
            if ($selection === '') {
                continue;
            }
            $gap = question\get_gap_name($i + 1);
            $pairs[] = utils\slugify($gap) . '[.]' . utils\slugify($selection);
            $extension[utils\slugify($gap)] = $selection;
        }

        $result = [
            'response' => implode('[,]', $pairs),
            'completion' => true,
            'success' => $questionattempt->rightanswer === $questionattempt->responsesummary,
            'extensions' => [
                'http://learninglocker.net/xapi/cmi/matching/response' => (object) $extension,
            ],
        ];
    }

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/answered',
            'display' => [
                'en' => 'Answered',
            ],
        ],
        'object' => [
            ...utils\get_activity\base(),
            'id' => utils\get_quiz_question_id($config, $coursemodule->id, $question->id),
            'definition' => question\get_gapselect_definition($config, $question, $lang),
        ],
        'result' => $result,
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => array_merge(
                    [
                        utils\get_activity\quiz_attempt($config, $attempt->id, $coursemodule->id),
                    ],
                    utils\context_activities\get_parent(
                        $config,
                        $event->contextinstanceid,
                        true
                    ),
                ),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
