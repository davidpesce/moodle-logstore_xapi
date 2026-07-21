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
 * Transform for course module completion updated event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer for course module completion updated event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function course_module_completion_updated(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->relateduserid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);
    $completionstate = utils\decode_other($event->other)['completionstate'];

    $result = [];

    if ($completionstate) {
        $verb = [
            'id' => 'http://adlnet.gov/expapi/verbs/completed',
            'display' => [
                'en' => 'Completed',
            ],
        ];

        // Completionstate values are 1=completion, 2=pass, 3=fail.
        $result['completion'] = true;
        if ($completionstate > 1) {
            $result['success'] = ($completionstate == 2);
        }
    } else {
        $verb = [
            'id' => 'https://xapi.edlm/profiles/edlm-lms/concepts/verbs/uncompleted',
            'display' => [
                'en' => 'Uncompleted',
            ],
        ];
    }

    $statement = [
        'actor' => utils\get_user($config, $user),
        'verb' => $verb,
        'object' => utils\get_activity\course_module(
            $config,
            $course,
            $event->contextinstanceid
        ),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => utils\context_activities\get_parent(
                    $config,
                    $event->contextinstanceid
                ),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ];

    if (!empty($result)) {
        $statement['result'] = $result;
    }

    return [$statement];
}
