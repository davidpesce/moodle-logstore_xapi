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
 * Transformer for tour ended event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\tool_usertours;

use src\transformer\utils;

/**
 * Transformer for tour ended event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function tour_ended(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $lang = $config['source_lang'];

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'https://xapi.edlm/profiles/edlm-lms/concepts/verbs/toured',
            'display' => [
                'en' => 'Toured',
            ],
        ],
        'object' => [
            'id' => utils\decode_other($event->other)['pageurl'],
            'objectType' => 'Activity',
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang),
            'contextActivities' => [
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
