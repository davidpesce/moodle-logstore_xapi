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
 * Transformer utility for retrieving (glossary entry) activities.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * Transformer utility for retrieving (glossary comment) activities.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course object.
 * @param \stdClass $comment The glossary entry comment object.
 * @return array
 */
function glossary_comment(array $config, \stdClass $course, \stdClass $comment) {
    $repo = $config['repo'];
    $entry = $repo->read_record_by_id('glossary_entries', $comment->itemid);

    $courselang = utils\get_course_lang($course);
    $commenturl = $config['app_url'] . '/mod/glossary/showentry.php?eid=' . $entry->id . '#c' . $comment->id;

    $activity = [
        ...base(),
        'id' => $commenturl,
        'definition' => [
            'type' => 'http://activitystrea.ms/comment',
        ],
    ];

    if (isset($entry->concept)) {
        $activity['definition']['name'] = [
            $courselang => 'RE: ' . $entry->concept,
        ];
    }

    // Comments only have descriptions when they aren't deleted.
    if (isset($comment->content) && utils\is_sending_content($config)) {
        $activity['definition']['description'] = [
            $courselang => utils\get_string_html_removed($comment->content),
        ];
    }

    return $activity;
}
