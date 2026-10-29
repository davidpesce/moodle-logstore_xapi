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
 * Transformer fn for group message sent event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer fn for group message sent event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function group_message_sent(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $message = $repo->read_record_by_id('messages', $event->objectid);
    $conversation = $repo->read_record_by_id(
        'message_conversations',
        $message->conversationid
    );
    $group = $repo->read_record_by_id('groups', $conversation->itemid);
    $course = $repo->read_record_by_id('course', $group->courseid);
    $lang = utils\get_course_lang($course);

    $statement = [
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://activitystrea.ms/send',
            'display' => [
                'en' => 'Sent',
            ],
        ],
        'object' => utils\get_activity\message($config, $lang, $message),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'grouping' => [
                    utils\get_activity\course_group($config, $course, $group),
                ],
                'parent' => [
                    utils\get_activity\course($config, $course),
                ],
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ];
    // The group is the grouping activity. Listing its members as recipients put every member in
    // each statement.
    return [
        $statement,
    ];
}
