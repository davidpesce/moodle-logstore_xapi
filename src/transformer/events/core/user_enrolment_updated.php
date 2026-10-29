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
 * Transform for user enrolment updated event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer for the user enrolment updated event.
 *
 * Moodle fires this event when an enrolment's status, start date or end date changes, and
 * records only the enrolment method, so the statement says the enrolment was updated, not that it
 * was suspended or resumed. The enrolment's status when the queue is sent may not be its status
 * at the time, or the change the event was about.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function user_enrolment_updated(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $cuser = $repo->read_record_by_id('user', $event->relateduserid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);

    return [[
        'actor' => utils\get_user($config, $cuser),
        'verb' => [
            'id' => 'http://activitystrea.ms/update',
            'display' => [
                'en' => 'Updated',
            ],
        ],
        'object' => utils\get_activity\course($config, $course),
        'context' => utils\get_enrolment_context(
            $config,
            $event,
            $course,
            $cuser,
            $user,
            $lang
        ),
    ]];
}
