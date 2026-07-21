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
 * Transformer fn for questions imported event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer for questions_imported event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function questions_imported(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    $info = utils\decode_other($event->other);

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/imported',
            'display' => [
                'en' => 'Imported',
            ],
        ],
        'object' => [
            ...utils\get_activity\base(),
            'id' => $config['app_url'] . '/question/bank/importquestions/import.php',
            'definition' => [
                'type' => 'http://adlnet.gov/expapi/activities/file',
                'name' => [
                    $lang => 'Questions in ' . $info['format'] . ' format',
                ],
            ],
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang),
            'contextActivities' => [
                'parent' => [
                    utils\get_activity\course($config, $course),
                ],
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
