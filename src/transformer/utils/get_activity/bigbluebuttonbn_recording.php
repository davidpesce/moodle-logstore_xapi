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
 * Transformer utility for retrieving BigBlueButton recording activities.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * A recording of a BigBlueButton activity's meeting.
 *
 * The id is the address Moodle plays the recording from, without the playback type. The
 * recording's name is kept on the BigBlueButton server, so the activity's name and the
 * recording id stand for it.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course.
 * @param int $cmid The activity's course module id.
 * @param int $recordingid The recording's id (bigbluebuttonbn_recordings.id).
 * @return array
 */
function bigbluebuttonbn_recording(array $config, \stdClass $course, int $cmid, int $recordingid): array {
    $repo = $config['repo'];
    $coursemodule = $repo->read_record_by_id('course_modules', $cmid);
    $instance = $repo->read_record_by_id('bigbluebuttonbn', $coursemodule->instance);
    $lang = utils\get_course_lang($course);

    return [
        ...base(),
        'id' => $config['app_url'] . '/mod/bigbluebuttonbn/bbb_view.php?action=play&bn=' . $instance->id
            . '&rid=' . $recordingid,
        'definition' => [
            'type' => 'https://w3id.org/xapi/video/activity-type/video',
            'name' => [
                $lang => $instance->name . ' Recording ' . $recordingid,
            ],
        ],
    ];
}
