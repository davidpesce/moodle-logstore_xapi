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
 * Transformer for glossary entry comment deleted event.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_glossary;

use src\transformer\utils;

/**
 * Transformer for glossary entry comment deleted event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function comment_deleted(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);
    $other = utils\decode_other($event->other);

    $comment = new \stdClass();
    $comment->id = $event->objectid;
    $comment->itemid = $other['itemid'];

    $entry = $repo->read_record_by_id('glossary_entries', $comment->itemid);

    return[[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://activitystrea.ms/delete',
            'display' => [
                'en' => 'Deleted',
            ],
        ],
        'object' => utils\get_activity\glossary_comment($config, $course, $comment),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => array_merge(
                    [utils\get_activity\glossary_entry($config, $course, $entry)],
                    utils\context_activities\get_parent($config, $event->contextinstanceid, true)
                ),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
