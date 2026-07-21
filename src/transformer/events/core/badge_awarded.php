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
 * Transform for badge awarded event.
 *
 * @package   logstore_xapi
 * @copyright Daniel Bell <daniel@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;
use src\transformer\utils;

/**
 * Transforms badge_awarded event to an "Achieved" xAPI event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function badge_awarded(array $config, \stdClass $event) {
    global $CFG;
    $repo = $config['repo'];

    if (isset($event->objecttable) && isset($event->objectid)) {
        $eventobject = $repo->read_record_by_id($event->objecttable, $event->objectid);
    } else {
        $eventobject = [];
    }

    $recipient = $repo->read_record_by_id('user', $event->relateduserid);
    $actor = utils\get_user($config, $recipient);

    $badge = $repo->read_record_by_id('badge', $event->objectid);
    $course = $badge->courseid ? $repo->read_record_by_id('course', $badge->courseid) : null;

    $lang = $badge->language ??
      ((!(is_null($course))) ?
       utils\get_course_lang($course) :
       $config['source_lang']);

    $other = utils\decode_other($event->other);
    $issuedid = $other['badgeissuedid'];

    $manual = $repo->read_record_by_id('badge_manual_award', $issuedid);
    $awarder = $manual ? (utils\get_user($config, $repo->read_record_by_id('user', $manual->issuerid))) : 'System';
    $statement = [
        [
            'actor' => $actor,
            'verb' => [
                'id' => 'https://w3id.org/xapi/tla/verbs/achieved',
                'display' => [
                    'en' => 'Achieved',
                ],
            ],
            'object' => utils\get_activity\badge($config, $lang, $badge),
            'result' => [
                'response' => $badge->message,
            ],
            'context' => [
                ...utils\get_context_base($config, $event, $lang, $course),
                'instructor' => $awarder,
                'contextActivities' => [
                    'category' => [
                        utils\get_activity\site($config),
                    ],
                ],
                'extensions' => array_merge(
                    utils\extensions\base($config, $event, $course),
                    [
                        'https://xapi.edlm/profiles/edlm-lms/v1/concepts/context-extensions/badge-assignment-method' =>
                            ($manual ? 'Manual' : 'Automatic'),
                    ]
                ),
            ],
        ],
    ];
    if ($course) {
        $statement[0]['context']['contextActivities']['parent'] = [[
            'id' => $config['app_url'] . '/course/view.php?id=' . $course->id,
            'objectType' => 'Activity',
            'definition' => [
                'name' => [$lang => $course->fullname],
                'description' => [$lang => $course->summary],
                'type' => 'https://w3id.org/xapi/cmi5/activitytype/course',
            ],
        ]];
    }

    return $statement;
}
