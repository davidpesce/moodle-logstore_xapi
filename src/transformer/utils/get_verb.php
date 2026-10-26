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
 * Transformer utility for retrieving the verb.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Return the requested verb with details.
 *
 * @param string $verb The requested verb.
 * @param array $config Predefined config elements.
 * @param string $lang Language string.
 * @return array
 * @throws \coding_exception
 */
function get_verb(string $verb, array $config, string $lang) {
    $verb = strtolower($verb);
    $output = [];
    switch ($verb) {
        case 'abandoned':
            $output = [
                'id' => 'https://w3id.org/xapi/adl/verbs/abandoned',
                'display' => [
                    'en' => 'Abandoned',
                ],
            ];
            break;

        case 'completed':
            $output = [
                'id' => 'http://adlnet.gov/expapi/verbs/completed',
                'display' => [
                    'en' => 'Completed',
                ],
            ];
            break;

        case 'loggedin':
            $output = [
                'id' => 'https://xapi.edlm/profiles/edlm-lms/concepts/verbs/login',
                'display' => [
                    'en' => 'Logged In',
                ],
            ];
            break;

        case 'loggedout':
            $output = [
                'id' => 'https://xapi.edlm/profiles/edlm-lms/concepts/verbs/logout',
                'display' => [
                    'en' => 'Logged Out',
                ],
            ];
            break;

        case 'received':
            $output = [
                'id' => 'http://activitystrea.ms/schema/1.0/receive',
                'display' => [
                    'en' => 'Received',
                ],
            ];
            break;

        case 'scored':
            $output = [
                'id' => 'https://w3id.org/xapi/tla/verbs/scored',
                'display' => [
                    'en' => 'Scored',
                ],
            ];
            break;

        case 'started':
            $output = [
                'id' => 'http://activitystrea.ms/schema/1.0/start',
                'display' => [
                    'en' => 'Started',
                ],
            ];
            break;

        case 'submitted':
            $output = [
                'id' => 'http://activitystrea.ms/schema/1.0/submit',
                'display' => [
                    'en' => 'Submitted',
                ],
            ];
            break;

        case 'viewed':
            $output = [
                'id' => 'http://id.tincanapi.com/verb/viewed',
                'display' => [
                    'en' => 'Viewed',
                ],
            ];
            break;

        default:
            break;
    }

    if (empty($output)) {
        throw new \coding_exception(get_string('unknownverb', 'logstore_xapi'));
    }

    return $output;
}
