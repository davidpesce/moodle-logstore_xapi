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
 * Transformer fn for assignment submission locked/unlocked event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_assign;

use src\transformer\utils;

/**
 * Transformer for assignment_submission_locked/unlocked event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function submission_locked(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $instructor = $repo->read_record_by_id('user', $event->userid);
    $learner = $repo->read_record_by_id('user', $event->relateduserid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    return [[
        'actor' => utils\get_user($config, $instructor),
        'verb' => ($event->action === 'locked')
            ? [
                'id' => 'https://xapi.edlm/profiles/edlm-lms/concepts/verbs/locked',
                'display' => [
                    'en' => 'Locked',
                ],
            ]
            : [
                'id' => 'https://xapi.edlm/profiles/edlm-lms/concepts/verbs/unlocked',
                'display' => [
                    'en' => 'Unlocked',
                ],
            ],
        'object' => utils\get_activity\assign_submission(
            $config,
            $event->contextinstanceid,
            $lang
        ),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'extensions' => [
                'https://yetanalytics.com/profiles/prepositions/concepts/context-extensions/for'
                    => utils\get_user($config, $learner),
                ...utils\extensions\base($config, $event, $course),
            ],
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
