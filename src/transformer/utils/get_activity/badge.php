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
 * Utility for creating badge objects for badge events,
 *
 * @package   logstore_xapi
 * @copyright Daniel Bell <daniel@yetanalytics.com>
 *            Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

/**
 * Utility for creating badge objects for badge events.
 *
 * @param array $config The site config.
 * @param string $lang The language
 * @param array $badge The badge associative array.
 * @return array
 */

/**
 * Generate an xAPI activity object for a badge.
 *
 * @param array $config The site config.
 * @param string $lang The language.
 * @param array $badge The badge associative array.
 * @return array
 */
function badge($config, $lang, $badge) {
    $badgetype = [1 => "Global", 2 => "Course"][$badge->type];

    return [
        ...base(),
        'id' => $config['app_url'] . '/badges/overview.php?id=' . $badge->id,
        'definition' => [
            'name' => [$lang => $badge->name],
            ...(($badge->description ?? '') !== ''
                ? ['description' => [$lang => $badge->description]]
                : []),
            'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/badge',
            'extensions' => [
                'https://xapi.edlm/profiles/edlm-lms/v1/concepts/activity-extensions/badge-type' => $badgetype,
                'https://xapi.edlm/profiles/edlm-lms/v1/concepts/activity-extensions/badge-version' => $badge->version,
            ],
        ],
    ];
}
