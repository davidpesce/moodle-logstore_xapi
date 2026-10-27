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
 * Transformer fn for assignment feedback viewed event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_assign;

use src\transformer\utils;

/**
 * Transformer for assignment_feedback_viewed event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function feedback_viewed(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    $coursemodule = $repo->read_record_by_id('course_modules', $event->contextinstanceid);
    $module = $repo->read_record_by_id('modules', $coursemodule->module);
    $instance = $repo->read_record_by_id($module->name, $coursemodule->instance);
    $instancename = property_exists($instance, 'name') ? $instance->name : $module->name;

    // A grade that was not given in the assignment, such as one entered in the gradebook, has
    // grader -1. Core then shows the user who last changed the gradebook grade, if they can
    // grade, but that is a capability check the transformer cannot make, so no instructor is sent.
    $grade = $repo->read_record_by_id('assign_grades', $event->objectid);
    $grader = $grade->grader > 0 ? $repo->read_record_by_id('user', $grade->grader) : null;

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://id.tincanapi.com/verb/viewed',
            'display' => [
                'en' => 'Viewed',
            ],
        ],
        'object' => [
            'id' => $config['app_url']
                . '/mod/assign/view.php?id='
                . $event->contextinstanceid
                . '#feedback',
            'objectType' => 'Activity',
            'definition' => [
                'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/feedback',
                'name' => [
                    $lang => $instancename . ' Feedback',
                ],
            ],
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            ...(is_null($grader) ? [] : ['instructor' => utils\get_user($config, $grader)]),
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
