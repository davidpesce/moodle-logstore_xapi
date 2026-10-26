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
 * Transformer utility for retrieving enrolment context object.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

use src\transformer\utils;

/**
 * Return a context object for a user enrolment.
 * @param array $config The transformer config array.
 * @param \stdClass $event The moodle event.
 * @param \stdClass $course The moodle course.
 * @param \stdClass $cuser The user attending the course.
 * @param \stdClass $user The user triggering the event.
 * @param string $lang The language to use.
 * @return array
 */
function get_enrolment_context(
    array $config,
    \stdClass $event,
    \stdClass $course,
    \stdClass $cuser,
    \stdClass $user,
    string $lang
) {
    $info = utils\decode_other($event->other);

    $ctx = [
        'language' => $lang,
        'extensions' => array_merge(
            utils\extensions\base($config, $event, $course),
            [
                'https://xapi.edlm/profiles/edlm-lms/concepts/context-extensions/enrolment-type' =>
                    $info['enrol'],
            ]
        ),
        'contextActivities' => [
            'category' => [
                utils\get_activity\site($config),
            ],
        ],
    ];

    // Add a possible instructor different from course user.
    if ($cuser->id !== $user->id) {
        $ctx['instructor'] = utils\get_user($config, $user);
    }
    return $ctx;
}
