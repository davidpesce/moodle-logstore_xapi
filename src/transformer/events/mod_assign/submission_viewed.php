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
 * Transformer fn for assignment submission viewed event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_assign;

use src\transformer\utils;

/**
 * Transformer for assignment_submission_viewed event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function submission_viewed(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $instructor = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    // A group submission belongs to a group, not a user: relateduserid is 0. Submissions from
    // users in no group belong to the default group, groupid 0, and have no "for" at all.
    if (!empty($event->relateduserid)) {
        $for = utils\get_user($config, $repo->read_record_by_id('user', $event->relateduserid));
    } else {
        $submission = $repo->read_record_by_id('assign_submission', $event->objectid);
        $for = empty($submission->groupid)
            ? null
            : utils\get_group($config, $repo->read_record_by_id('groups', $submission->groupid));
    }

    return [[
        'actor' => utils\get_user($config, $instructor),
        'verb' => [
            'id' => 'http://id.tincanapi.com/verb/viewed',
            'display' => [
                'en' => 'Viewed',
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
                ...(is_null($for) ? [] : [
                    'https://yetanalytics.com/profiles/prepositions/concepts/context-extensions/for' => $for,
                ]),
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
