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
 * Transformer utility for creating calendar event objects.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

/**
 * Utility for creating calendar event objects.
 *
 * @param array $config The transformer config.
 * @param string $lang The language.
 * @param int $eventid The id of the event.
 * @param string $name The name of the event.
 * @return array
 */

/**
 * Generate an xAPI activity object for a calendar event.
 *
 * @param array $config The transformer config.
 * @param string $lang The language.
 * @param int $eventid The id of the event.
 * @param ?string $name The name of the event, or null to send none.
 * @return array
 */
function calendar_event(
    array $config,
    string $lang,
    int $eventid,
    ?string $name
) {
    return [
        ...base(),
        'id' => $config['app_url'] . '/calendar/view.php?id=' . $eventid,
        'definition' => [
            ...(is_null($name) ? [] : [
                'name' => [
                    $lang => $name,
                ],
            ]),
            'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/calendar-event',
        ],
    ];
}
