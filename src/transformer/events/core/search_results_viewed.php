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
 * Transformer fn for search results viewed event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer fn for search results viewed event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function search_results_viewed(array $config, \stdClass $event): array {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $lang = $config['source_lang'];
    $info = utils\decode_other($event->other);

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'https://w3id.org/xapi/acrossx/verbs/searched',
            'display' => [
                'en' => 'Searched',
            ],
        ],
        'object' => [
            ...utils\get_activity\base(),
            'id' => $config['app_url'] . '/search/index.php',
            'definition' => [
                'type' => 'https://w3id.org/xapi/acrossx/activities/webpage',
                'name' => [
                    $lang => 'Global Search',
                ],
            ],
        ],
        'result' => [
            'response' => $info['q'],
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
