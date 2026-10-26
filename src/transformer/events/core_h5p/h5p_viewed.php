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
 * Transformer for the H5P viewed event.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core_h5p;

use src\transformer\events\all;
use src\transformer\utils;

/**
 * Transformer for the H5P viewed event.
 *
 * Core fires it in the context the H5P file belongs to. In an activity (the H5P activity, or
 * content embedded in another module) that is the course module, and the statement is a module
 * view. Elsewhere (the content bank, or content embedded in a course, section or user text)
 * contextinstanceid is not a course module, so the content is identified by its file URL.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function h5p_viewed(array $config, \stdClass $event) {
    if ((int) ($event->contextlevel ?? 0) === CONTEXT_MODULE) {
        return all\course_module_viewed($config, $event);
    }

    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $course = empty($event->courseid) ? null : $repo->read_record_by_id('course', $event->courseid);
    $lang = is_null($course) ? $config['source_lang'] : utils\get_course_lang($course);
    $other = (array) utils\decode_other($event->other);
    if (empty($other['url'])) {
        throw new \Exception('The H5P viewed event has no URL.');
    }

    $statement = [
        'actor' => utils\get_user($config, $user),
        'verb' => utils\get_verb('viewed', $config, $lang),
        'object' => utils\get_activity\h5p_content($other['url'], $lang),
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ];

    if ($course) {
        $statement = utils\add_parent($config, $statement, $course);
    }

    return [$statement];
}
