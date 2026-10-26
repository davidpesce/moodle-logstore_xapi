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
 * Transformer utility for converting course categories to xAPI activities.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 *
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * Generate an xAPI activity object for a course category.
 *
 * @param array $config The configuration object.
 * @param \stdClass $category The course category.
 * @return array
 */
function course_category(array $config, \stdClass $category) {
    $lang = $config['source_lang'];
    return [
        ...base(),
        'id' => $config['app_url'] . '/course/management.php?categoryid=' . $category->id,
        'definition' => [
            'name' => [
                $lang => $category->name,
            ],
            // The description is optional HTML.
            ...(($category->description ?? '') !== ''
                ? [
                    'description' => [
                        $lang => utils\get_string_html_removed($category->description),
                    ],
                ]
                : []),
            'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/course-category',
        ],
    ];
}
