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
 * Transformer fn for question created event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;
use src\transformer\utils\get_activity\definition\question as qdef;

/**
 * Transformer for question_created event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function question_created(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    // On Moodle 4.5, question banks in the system or a course category context have no course.
    $course = empty($event->courseid) ? null : $repo->read_record_by_id('course', $event->courseid);
    $question = $repo->read_record_by_id('question', $event->objectid);
    $lang = is_null($course) ? $config['source_lang'] : utils\get_course_lang($course);

    $definition = qdef\get_definition($config, $question, $lang);

    $definition['extensions']['https://xapi.edlm/profiles/edlm-lms/concepts/activity-extensions/moodle-question-type'] =
        $question->qtype;

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://activitystrea.ms/create',
            'display' => [
                'en' => 'Created',
            ],
        ],
        'object' => [
            ...utils\get_activity\base(),
            'id' => $config['app_url'] . '/question?id=' . $question->id,
            'definition' => $definition,
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, null),
            'contextActivities' => [
                ...(is_null($course) ? [] : [
                    'parent' => [
                        utils\get_activity\course($config, $course),
                    ],
                ]),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
