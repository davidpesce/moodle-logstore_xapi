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
 * Transformer utility for retrieving (lesson content page) activities.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils as utils;

/**
 * Transformer utility for retrieving (lesson content page) activities.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course object.
 * @param \stdClass $lesson The lesson object.
 * @param \stdClass $page The lesson page object.
 * @param int $cmid course module id
 * @return array
 */
function lesson_content_page(array $config, \stdClass $course, \stdClass $lesson, \stdClass $page, int $cmid) {
    $repo = $config['repo'];
    $courselang = utils\get_course_lang($course);
    $entryurl = $config['app_url'].'/mod/lesson/view.php?id='.$cmid.'&pageid='.$page->id;

    $activity = [
        ...base(),
        'id' => $entryurl,
        'definition' => [
            'type' => 'http://adlnet.gov/expapi/activities/lesson-content-page'
        ],
    ];

    if (isset($page->title)) {
        $activity['definition']['name'] = [
            $courselang => $page->title
        ];
    }

    return $activity;
}
