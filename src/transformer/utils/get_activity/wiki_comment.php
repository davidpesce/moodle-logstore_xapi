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
 * Transformer utility for retrieving wiki comment activities.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * A comment on a wiki page, as glossary comments are sent.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course.
 * @param \stdClass $wikipage The page the comment is on.
 * @param int $commentid The comment's id.
 * @param ?\stdClass $comment The comment, while it exists, for its text.
 * @return array
 */
function wiki_comment(
    array $config,
    \stdClass $course,
    \stdClass $wikipage,
    int $commentid,
    ?\stdClass $comment = null
): array {
    $lang = utils\get_course_lang($course);
    return [
        ...base(),
        'id' => $config['app_url'] . '/mod/wiki/instancecomments.php?commentid=' . $commentid
            . '&pageid=' . $wikipage->id,
        'definition' => [
            'type' => 'http://activitystrea.ms/comment',
            'name' => [
                $lang => 'RE: ' . $wikipage->title,
            ],
            ...((is_null($comment) || !utils\is_sending_content($config)) ? [] : [
                'description' => [
                    $lang => utils\get_string_html_removed($comment->content),
                ],
            ]),
        ],
    ];
}
