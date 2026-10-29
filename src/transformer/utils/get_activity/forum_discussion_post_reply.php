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
 * Transformer utility for retrieving (forum discussion post reply) activities.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

use src\transformer\utils;

/**
 * Transformer utility for retrieving (forum discussion post reply) activities.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course object.
 * @param \stdClass $post The forum post object.
 * @return string
 */
function forum_discussion_post_reply(array $config, \stdClass $course, \stdClass $post) {
    $lang = utils\get_course_lang($course);

    $activity = [
        ...base(),
        'id' => $config['app_url'] . '/mod/forum/discuss.php?d=' . $post->discussion . "#p" . $post->id,
        'definition' => [
            'type' => 'http://id.tincanapi.com/activitytype/forum-reply',
        ],
    ];

    // Forum activities only have names when they aren't deleted. A post's subject and message are
    // content, which "Send content" leaves out.
    if (!utils\is_sending_content($config)) {
        return $activity;
    }
    if (isset($post->subject)) {
        $activity['definition']['name'] = [
            $lang => $post->subject,
        ];
    }

    if (isset($post->message)) {
        $activity['definition']['description'] = [
            $lang => utils\get_string_html_removed($post->message),
        ];
    }

    return $activity;
}
