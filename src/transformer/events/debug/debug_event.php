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
 * Generic Debug Transform
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *            Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\debug;

use src\transformer\utils;

/**
 * Transformer for Any Event to emit debug xAPI statements.
 * Only used in development.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function debug_event(array $config, \stdClass $event) {
    // Debug.
    $repo = $config['repo'];
    if (isset($event->objecttable) && isset($event->objectid) && ($event->action !== 'deleted' || $event->crud !== 'd')) {
        $eventobject = $repo->read_record_by_id($event->objecttable, $event->objectid);
    } else {
        $eventobject = [];
    }

    return [[
        'object' => [
            'id' => 'http://www.yetanalytics.com/test_events/debug' . utils\reverseBackslashes($event->eventname),
        ],
        'actor' => [
            'mbox' => 'mailto:tester@example.com',
        ],
        'verb' => [
            'id' => 'http://www.yetanalytics.com/debug',
            'display' => [
                'en-US' => 'debug',
            ],
        ],
        'context' => [
            'extensions' => [
                'http://www.yetanalytics.com/debug_objects/event' => $event,
                'http://www.yetanalytics.com/debug_objects/event_other' => utils\decode_other($event->other),
                'http://www.yetanalytics.com/debug_objects/event_object' => $eventobject,
            ],
        ],
    ]];
}
