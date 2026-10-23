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
 * Apply default values to statements.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Given the config, source event and statements, apply default values.
 *
 * @param array $config configuration array.
 * @param \stdClass $event original event
 * @param array $statements generated xAPI statements.
 * @return array
 */
function apply_statement_defaults(array $config, \stdClass $event, array $statements) {
    return array_map(function ($statement) use ($config, $event) {
        $defaultstatement = [
            'context' => [
                'platform' => $config['context_platform'] ?? 'Moodle',
                'registration' => stringToUuidV5($config['session_id']),
            ],
            'timestamp' => get_event_timestamp($event),
        ];
        // The registration is derived from the session, so in real-time mode it is shared by every
        // statement from that session. Leave it off anonymised statements so it cannot link them
        // to the same user's identified statements.
        if (($statement['actor']['account']['name'] ?? null) === 'anonymous') {
            unset($defaultstatement['context']['registration']);
        }
        // Merge event output into defaults.
        return deep_merge_arrays($defaultstatement, $statement);
    }, $statements);
}
