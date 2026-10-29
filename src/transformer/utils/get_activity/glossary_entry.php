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
 * Transformer utility for retrieving (glossary entry) activities.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $course The course object.
 * @param \stdClass $entry The glossary entry object.
 * @return array
 */
function glossary_entry(array $config, \stdClass $course, \stdClass $entry) {
    $courselang = utils\get_course_lang($course);
    $entryurl = $config['app_url'] . '/mod/glossary/showentry.php?eid=' . $entry->id;

    $activity = [
        ...base(),
        'id' => $entryurl,
        'definition' => [
            'type' => 'https://xapi.edlm/profiles/edlm-lms/concepts/activity-types/glossary-entry',
        ],
    ];

    // Entries only have names when they aren't deleted.
    if (isset($entry->concept)) {
        $activity['definition']['name'] = [
            $courselang => $entry->concept,
        ];
    }
    if (isset($entry->definition) && utils\is_sending_content($config)) {
        $activity['definition']['description'] = [
            $courselang => utils\get_string_html_removed($entry->definition),
        ];
    }

    return $activity;
}
