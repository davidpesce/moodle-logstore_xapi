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
 * Build an absolute IRI for the page the tour ended on.
 *
 * The page URL comes from the user's browser through the complete_tour web service, which
 * cleans it only as PARAM_LOCALURL, so it can be relative or empty. xAPI requires an absolute
 * IRI, and an LRS rejecting one statement rejects its whole batch, so relative paths are made
 * absolute and anything still invalid falls back to the tour itself.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return string
 */
function get_tour_page_iri(array $config, \stdClass $event): string {
    $pageurl = (string) (utils\decode_other($event->other)['pageurl'] ?? '');
    if ($pageurl !== '' && !preg_match('#^[a-z][a-z0-9+.-]*://#i', $pageurl)) {
        $pageurl = $config['app_url'] . '/' . ltrim($pageurl, '/');
    }
    if (filter_var($pageurl, FILTER_VALIDATE_URL)) {
        return $pageurl;
    }
    return $config['app_url'] . '/admin/tool/usertours/configure.php?action=viewtour&id=' . ($event->objectid ?? 0);
}

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
            'id' => get_tour_page_iri($config, $event),
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
