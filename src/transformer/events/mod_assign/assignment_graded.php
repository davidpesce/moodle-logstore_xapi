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
 * Transform for assignment graded event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 *            Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_assign;

use src\transformer\utils;

/**
 * Transformer for the assignment graded event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function assignment_graded(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $grade = $repo->read_record_by_id($event->objecttable, $event->objectid);
    $user = $repo->read_record_by_id('user', $grade->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $instructor = $repo->read_record_by_id('user', $event->userid);
    $assignment = $repo->read_record_by_id('assign', $grade->assignment);
    $lang = utils\get_course_lang($course);

    $gradecomment = null;
    try {
        $gradecomment = $repo->read_record('assignfeedback_comments', [
            'assignment' => $grade->assignment,
            'grade' => $grade->id,
        ])->commenttext;
    } catch (\Exception $e) {
        $gradecomment = null;
    }

    $gradeitems = $repo->read_record('grade_items', [
        'itemmodule' => 'assign',
        'iteminstance' => $grade->assignment,
    ]);

    $scoreraw = (float) ($grade->grade ?: 0);
    $scoremin = (float) ($gradeitems->grademin ?: 0);
    $scoremax = (float) ($gradeitems->grademax ?: 0);
    $validscore = ($scoremin <= $scoreraw && $scoreraw <= $scoremax) ? true : false;

    $scorepass = (float) ($gradeitems->gradepass ?: null);

    $success = false;

    if ($scoreraw >= $scorepass) {
        $success = true;
    }

    $statement = [
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'https://w3id.org/xapi/tla/verbs/scored',
            'display' => [
                'en' => 'Scored',
            ],
        ],
        'object' => utils\get_activity\assign_submission(
            $config,
            $event->contextinstanceid,
            $lang
        ),
        'result' => [
            'success' => $success,
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'instructor' => utils\get_user($config, $instructor),
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
    ];

    // The comment is free text written to the student and may name them, so it is left out
    // while their identity is hidden (blind marking).
    if (!is_null($gradecomment) && empty($event->anonymous)) {
        $statement['result']['response'] = $gradecomment;
    }

    // Only write a score if valid.
    if ($validscore) {
        $statement['result']['score'] = utils\get_score($scoreraw, $scoremin, $scoremax);
    }

    return [$statement];
}
