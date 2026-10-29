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
 * Transformer utility for retrieving wiki page activity objects.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * Transformer utility for retrieving wiki page activity objects.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course object.
 * @param \stdClass $wikipage The wiki page object.
 * @return array
 */
function wiki_page(
    array $config,
    \stdClass $course,
    \stdClass $wikipage
) {
    $lang = utils\get_course_lang($course);

    return [
        ...base(),
        'id' => $config['app_url'] . '/mod/wiki/view.php?pageid=' . $wikipage->id,
        'definition' => [
            'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/wiki-page',
            'name' => [
                $lang => $wikipage->title,
            ],
            ...(utils\is_sending_content($config) ? [
                'description' => [
                    $lang => utils\get_string_html_removed($wikipage->cachedcontent),
                ],
            ] : []),
        ],
    ];
}
