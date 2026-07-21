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
 * Transformer fn for wiki discussion comment deleted event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_wiki;

use src\transformer\utils;

/**
 * Transformer fn for wiki discussion comment deleted event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */

/**
 * Transformer for wiki discussion comment deleted event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function comment_deleted(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $wikipage = $repo->read_record_by_id(
        'wiki_pages',
        (int) utils\decode_other($event->other)['itemid']
    );
    $lang = utils\get_course_lang($course);

    return[[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://activitystrea.ms/delete',
            'display' => [
                'en' => 'Deleted',
            ],
        ],
        'object' => [
            'id' => $config['app_url']
                . '/mod/wiki/instancecomments.php?commentid=' . $event->objectid
                . '&pageid=' . $wikipage->id,
            'objectType' => 'Activity',
            'definition' => [
                'type' => 'http://activitystrea.ms/comment',
                'name' => [
                    'en' => 'Comment',
                ],
            ],
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => [
                    utils\get_activity\wiki_discussion(
                        $config,
                        $course,
                        $wikipage
                    ),
                    utils\get_activity\wiki_page(
                        $config,
                        $course,
                        $wikipage
                    ),
                    ...utils\context_activities\get_parent(
                        $config,
                        $event->contextinstanceid,
                        true
                    ),
                ],
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
