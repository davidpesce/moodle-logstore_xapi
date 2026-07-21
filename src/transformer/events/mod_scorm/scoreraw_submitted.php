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
 * Transform for the scorm scoreraw submitted event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_scorm;

use src\transformer\utils;

/**
 * Transformer for SCORM scoreraw submitted event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function scoreraw_submitted(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $scorm = $repo->read_record_by_id('scorm', $event->objectid);
    $lang = utils\get_course_lang($course);

    $unserializedcmi = utils\decode_other($event->other);
    $scormscoestracks = utils\get_scorm_tracks(
        $config,
        $user->id,
        $event->objectid,
        $event->contextinstanceid,
        $unserializedcmi['attemptid']
    );
    $rawscore = floatval($unserializedcmi['cmivalue']);

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => utils\get_scorm_verb($scormscoestracks, $lang),
        'object' => utils\get_activity\scorm_content_object(
            $config,
            $course,
            $event->contextinstanceid
        ),
        'result' => utils\get_scorm_result($scormscoestracks, $rawscore),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => utils\context_activities\get_parent(
                    $config,
                    $event->contextinstanceid,
                    true
                ),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
