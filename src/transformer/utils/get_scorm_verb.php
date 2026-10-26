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
 * Transformer utility for retrieving the SCORM verb.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for retrieving the SCORM verb.
 *
 * Only statuses that report progress have a verb: passed, failed, completed, and incomplete
 * (progressed). "not attempted", "unknown" and "browsed" say nothing about the learner's
 * progress, and a SCO writes them as soon as it is launched, so they have none.
 *
 * @param array $scormscoestracks An array of the SCORM tracks
 * @param string $lang The language of the event.
 * @return ?array The verb, or null when the status has none.
 */
function get_scorm_verb(array $scormscoestracks, string $lang) {
    // SCORM 1.2 reports one cmi.core.lesson_status. SCORM 2004 splits it into
    // cmi.success_status (passed/failed) and cmi.completion_status (completed); passed or failed
    // takes precedence, as it does in SCORM 1.2's lesson_status.
    $values = [];
    foreach ($scormscoestracks as $st) {
        $values[$st->element] = $st->value;
    }
    $scormstatus = $values['cmi.core.lesson_status'] ?? null;
    if (in_array($values['cmi.success_status'] ?? null, ['passed', 'failed'], true)) {
        $scormstatus = $values['cmi.success_status'];
    } else if ($scormstatus === null) {
        $scormstatus = $values['cmi.completion_status'] ?? null;
    }

    switch ($scormstatus) {
        case 'failed':
            return [
                'id' => 'http://adlnet.gov/expapi/verbs/failed',
                'display' => [
                    'en' => 'Failed',
                ],
            ];
        case 'passed':
            return [
                'id' => 'http://adlnet.gov/expapi/verbs/passed',
                'display' => [
                    'en' => 'Passed',
                ],
            ];
        case 'completed':
            return [
                'id' => 'http://adlnet.gov/expapi/verbs/completed',
                'display' => [
                    'en' => 'Completed',
                ],
            ];
        case 'incomplete':
            return [
                'id' => 'http://adlnet.gov/expapi/verbs/progressed',
                'display' => [
                    'en' => 'Progressed',
                ],
            ];
        default:
            return null;
    }
}
