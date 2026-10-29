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
 * A generic statement factory.
 *
 * @package     logstore_xapi
 * @copyright   Paul Walter (https://github.com/paulito-bandito)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_bigbluebuttonbn;
use src\transformer\utils;

/**
 * Create a statement about a BigBlueButton activity, or about one of its recordings.
 *
 * Moodle passes a recording event's recording id as the event's "other". The recording is then
 * the object, with the activity as its parent.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @param string $evtid The verb id.
 * @param string $evtdispname The verb's display name.
 * @param bool $isrecording Whether the event is about a recording.
 * @return array
 */
function create_statement(array $config, \stdClass $event, $evtid, $evtdispname, bool $isrecording = false) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);
    $recordingid = $isrecording ? utils\decode_other($event->other) : null;
    $isrecording = is_numeric($recordingid);

    $object = $isrecording
        ? utils\get_activity\bigbluebuttonbn_recording($config, $course, $event->contextinstanceid, (int) $recordingid)
        : utils\get_activity\course_module($config, $course, $event->contextinstanceid);
    $parents = utils\context_activities\get_parent($config, $event->contextinstanceid, $isrecording);

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => $evtid,
            'display' => [
                'en' => $evtdispname,
            ],
        ],
        'object' => $object,
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => $parents,
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
