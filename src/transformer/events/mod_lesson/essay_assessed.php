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
 * Transformer for lesson essay assessed event.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_lesson;

use src\transformer\utils;

/**
 * Transformer for lesson essay assessed event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function essay_assessed(array $config, \stdClass $event) {

    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    $other = utils\decode_other($event->other);
    $lesson = $repo->read_record_by_id('lesson', (int)$other['lessonid']);
    $attempt = $repo->read_record_by_id('lesson_attempts', $other['attemptid']);
    $page = $repo->read_record_by_id('lesson_pages', $attempt->pageid);
    $learner = $repo->read_record_by_id('user', $attempt->userid);
    $answer = $repo->read_record('lesson_answers', [
        'pageid' => $page->id,
    ]);

    return[[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'https://w3id.org/xapi/dod-isd/verbs/assessed',
            'display' => [
                'en' => 'Assessed',
            ],
        ],
        'result' => utils\get_lesson_essay_result(
            $config,
            $lesson,
            $answer,
            $attempt,
        ),
        'object' => utils\get_activity\lesson_question_page(
            $config,
            $course,
            $lesson,
            $page,
            $event->contextinstanceid
        ),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'extensions' => array_merge(
                [
                    'https://yetanalytics.com/profiles/prepositions/concepts/context-extensions/for' =>
                        utils\get_user($config, $learner),
                ],
                utils\extensions\base($config, $event, $course)
            ),
            'contextActivities' => [
                'parent' => utils\context_activities\get_parent(
                    $config,
                    $event->contextinstanceid,
                    true
                ),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
