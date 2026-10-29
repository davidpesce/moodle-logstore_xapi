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
 * Transform for the quiz attempt reviewed event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_quiz;

use src\transformer\utils;

/**
 * Transformer for quiz attempt reviewed event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function attempt_reviewed(array $config, \stdClass $event) {
    $repo = $config['repo'];
    // Core fires this event each time anyone loads the review page: userid is the viewer, and
    // relateduserid the student whose attempt it is.
    $viewer = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);
    $for = ((int) $event->relateduserid !== (int) $event->userid)
        ? utils\get_user($config, $repo->read_record_by_id('user', $event->relateduserid))
        : null;

    return [[
        'actor' => utils\get_user($config, $viewer),
        'verb' => utils\get_verb('viewed', $config, $lang),
        'object' => utils\get_activity\quiz_review($config, $event->objectid),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            ...(is_null($for) ? [] : [
                'extensions' => [
                    'https://yetanalytics.com/profiles/prepositions/concepts/context-extensions/for' => $for,
                    ...utils\extensions\base($config, $event, $course),
                ],
            ]),
            'contextActivities' => [
                'parent' => [
                    utils\get_activity\quiz_attempt(
                        $config,
                        $event->objectid,
                        $event->contextinstanceid
                    ),
                    ...utils\context_activities\get_parent(
                        $config,
                        $event->contextinstanceid,
                        true
                    ),
                ],
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
