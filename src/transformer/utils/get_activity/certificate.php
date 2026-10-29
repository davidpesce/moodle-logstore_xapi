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
 * Transformer utility for retrieving certificate activities.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * Transformer utility for retrieving certificate activities.
 *
 * @param array $config The transformer config settings.
 * @param string $code The certificate issue code.
 * @param string $lang The language.
 * @return array
 */
function certificate(
    array $config,
    string $code,
    string $lang
) {
    return [
        ...base(),
        'id' => $config['app_url'] . '/admin/tool/certificate/index.php?code=' . $code,
        'definition' => [
            'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/certificate',
            'name' => [
                $lang => 'Certificate ' . $code,
            ],
        ],
    ];
}
