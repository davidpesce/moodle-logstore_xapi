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
 * Transform for badge revoked event.
 *
 * @package   logstore_xapi
 * @copyright Daniel Bell <daniel@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer for Badge Revoked Event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function badge_revoked(array $config, \stdClass $event) {

    $repo = $config['repo'];
    $recipient = $repo->read_record_by_id('user', $event->relateduserid);
    $badge = $repo->read_record_by_id('badge', $event->objectid);
    $revoker = utils\get_user($config, $repo->read_record_by_id('user', $event->userid));
    $course = $badge->courseid ? $repo->read_record_by_id('course', $badge->courseid) : null;

    // The badge's language is a Moodle code such as pt_br, and may be empty.
    $lang = utils\get_lang_tag(
        $badge->language ?? null,
        !is_null($course) ? utils\get_course_lang($course) : $config['source_lang']
    );

    $statement = [[
        'actor' => utils\get_user($config, $recipient),
        'verb' => [
            'id' => 'https://w3id.org/xapi/tla/verbs/forfeited',
            'display' => [
                'en' => 'Forfeited',
            ],
        ],
        'object' => utils\get_activity\badge($config, $lang, $badge),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'instructor' => $revoker,
            'contextActivities' => [
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
            'extensions' => array_merge(
                utils\extensions\base($config, $event, $course),
                [
                    'https://xapi.edlm/profiles/edlm-lms/v1/concepts/context-extensions/badge-assignment-method' => 'Manual',
                ]
            ),
        ],
    ]];
    if ($course) {
        $statement[0]['context']['contextActivities']['parent'] = [[
            'id' => $config['app_url'] . '/course/view.php?id=' . $course->id,
            'objectType' => 'Activity',
            'definition' => [
                'name' => [$lang => $course->fullname],
                'description' => [$lang => utils\get_string_html_removed($course->summary)],
                'type' => 'https://w3id.org/xapi/cmi5/activitytype/course',
            ],
        ]];
    }
    return $statement;
}
