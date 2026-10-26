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
 * Transform for the feedback item answered (multichoicerated) event.
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
 * @param \stdClass $feedbackitem The feedback item.
 * @param array $actor The xAPI Actor.
 * @return array
 */
function multichoicerated(
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
    // Parsed like a plain multichoice item (subtype, layout suffix, "|" separators); each option
    // is then "rating####name".
    $options = get_multichoice_choices($feedbackitem->presentation);
    $choices = array_map(function ($option, $id) {
        $split = explode('####', $option, 2);
        return (object) [
            'rating' => intval($split[0]),
            'name' => trim($split[1] ?? $split[0]),
            'id' => $id,
        ];
    }, $options, array_keys($options));
    // An index outside the list gives no selection rather than an error.
    $selectedchoice = $choices[intval($feedbackvalue->value) - 1] ?? null;

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
            'definition' => utils\get_activity\definition\cmi\choice(
                $config,
                $feedbackitem->name,
                null,
                array_map(
                    function ($choice) {
                        return $choice->name;
                    },
                    $choices
                ),
                $lang
            ),
        ],
        'result' => [
            // The response is the selected choice's id, as xAPI defines for choice interactions;
            // the readable text goes in the extension.
            'response' => $selectedchoice ? utils\slugify($selectedchoice->name) : '',
            'completion' => $feedbackvalue->value !== '',
            'extensions' => $selectedchoice ? [
                'http://learninglocker.net/xapi/moodle/feedback_item_rating' => $selectedchoice->rating,
                'http://learninglocker.net/xapi/cmi/choice/response' => $selectedchoice->name,
            ] : [
                'http://learninglocker.net/xapi/cmi/choice/response' => '',
            ],
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
