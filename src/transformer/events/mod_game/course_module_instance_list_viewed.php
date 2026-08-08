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
 * Transformer for mod_game course module instance list viewed event.
 *
 * @package   logstore_xapi
 * @copyright David Herney - cirano. https://bambuco.co
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_game;

use src\transformer\utils;

/**
 * Transformer for mod_game course module instance list viewed event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function course_module_instance_list_viewed(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://id.tincanapi.com/verb/viewed',
            'display' => [
                'en' => 'Viewed',
            ],
        ],
        'object' => [
            'id' => $config['app_url'] . '/mod/game/index.php?id=' . $event->courseid,
            'objectType' => 'Activity',
            'definition' => [
                'type' => 'http://id.tincanapi.com/activitytype/collection-simple',
                'name' => [
                    $lang => 'Game instances list',
                ],
            ],
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
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
