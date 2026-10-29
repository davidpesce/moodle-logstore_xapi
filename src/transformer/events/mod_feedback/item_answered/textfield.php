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
 * Transform for the feedback item answered (textfield) event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 *            Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_feedback\item_answered;

use src\transformer\utils;

/**
 * Transformer for the mod_feedback item answered event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @param \stdClass $feedbackvalue The value of the feedback type.
 * @param \stdClass $feedbackitem The id of the feedback item.
 * @param array $actor The xAPI Actor.
 * @return array
 */
function textfield(
    array $config,
    \stdClass $event,
    \stdClass $feedbackvalue,
    \stdClass $feedbackitem,
    array $actor
) {
    $repo = $config['repo'];
    $course = $repo->read_record_by_id('course', $event->courseid);
    $feedback = $repo->read_record_by_id('feedback', $feedbackitem->feedback);
    $lang = utils\get_course_lang($course);

    return [[
        'actor' => $actor,
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/answered',
            'display' => [
                'en' => 'Answered',
            ],
        ],
        'object' => [
            ...utils\get_activity\base(),
            'id' => $config['app_url'] . '/mod/feedback/edit_item.php?id=' . $feedbackitem->id,
            'definition' => utils\get_activity\definition\cmi\fill_in(
                $config,
                $feedbackitem->name,
                null,
                $lang
            ),
        ],
        'result' => [
            // Core stores the answer HTML-escaped, through s(). The text is content, and is never
            // sent from anonymous feedback, where it can say who wrote it.
            ...((utils\is_sending_content($config) && empty($event->anonymous))
                ? ['response' => htmlspecialchars_decode($feedbackvalue->value, ENT_QUOTES | ENT_HTML401)]
                : []),
            'completion' => $feedbackvalue->value !== '',
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'parent' => utils\context_activities\get_parent(
                    $config,
                    $event->contextinstanceid,
                    true
                ),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
