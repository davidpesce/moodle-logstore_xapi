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
 * Transform for a mark entered in the gradebook.
 *
 * A mark typed only in the gradebook does not fire the assignment graded
 * event, so without this the hand-in stays "waiting" forever. The learner is
 * the actor unless the assignment uses blind marking. The grader is not on the
 * statement.
 *
 * @package   logstore_xapi
 * @copyright 2026 Flavio <flavio@ferreira-michell.org>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\core;

use src\transformer\utils;

/**
 * Transformer for the user graded event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function user_graded(array $config, \stdClass $event) {
    $repo = $config['repo'];

    // Statements are built in the background, after the grade may have been
    // changed again or cleared. The event keeps the grade it was fired with.
    $other = utils\decode_other($event->other);
    $other = is_array($other) ? $other : (array) $other;
    $grades = $repo->read_records('grade_grades', ['id' => $event->objectid]);
    $grade = empty($grades) ? null : reset($grades);
    if (array_key_exists('finalgrade', $other)) {
        $finalgrade = $other['finalgrade'];
    } else {
        $finalgrade = $grade ? $grade->finalgrade : null;
    }
    $itemid = $other['itemid'] ?? ($grade ? $grade->itemid : null);
    if ($finalgrade === null || $itemid === null) {
        return [];
    }

    // A deleted activity or grade item leaves nothing to report on.
    $items = $repo->read_records('grade_items', ['id' => $itemid]);
    $item = empty($items) ? null : reset($items);
    if (!$item || $item->itemtype !== 'mod') {
        return [];
    }
    $modules = $repo->read_records('modules', ['name' => $item->itemmodule]);
    $module = empty($modules) ? null : reset($modules);
    if (!$module) {
        return [];
    }
    $cms = $repo->read_records('course_modules', [
        'module' => $module->id,
        'instance' => $item->iteminstance,
        'course' => $event->courseid,
    ]);
    $cm = empty($cms) ? null : reset($cms);
    if (!$cm) {
        return [];
    }
    $learnerid = $event->relateduserid ?: ($grade ? $grade->userid : null);
    if (!$learnerid) {
        return [];
    }
    $user = $repo->read_record_by_id('user', $learnerid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);
    $isassign = $item->itemmodule === 'assign';
    $blindmarked = false;

    if ($isassign) {
        $assignment = $repo->read_record_by_id('assign', $item->iteminstance);
        $blindmarked = !empty($assignment->blindmarking);
        if ($blindmarked && ($config['anonymousevents'] ?? 'anonymise') === 'skip') {
            return [];
        }
        if ($blindmarked) {
            // The core user_graded event lacks the anonymous flag submission_graded has.
            $event->anonymous = 1;
        }
        $object = utils\get_activity\assign_submission($config, (int) $cm->id, $lang);
    } else {
        $object = utils\get_activity\course_module($config, $course, (int) $cm->id);
    }

    // Whether a comment exists, never its words. The assignment's own comment
    // counts, as on the assignment graded statement, and so does feedback
    // typed in the gradebook. Assignment marking fires this event just before
    // that one, so both must say the same.
    $hastext = function ($text) {
        return !is_null($text) && trim(strip_tags((string) $text)) !== '';
    };
    $feedbacktext = $grade->feedback ?? null;
    $feedbackpresent = $hastext($feedbacktext);
    if ($isassign && !$feedbackpresent) {
        $assigngrades = $repo->read_records('assign_grades', [
            'assignment' => $item->iteminstance,
            'userid' => $learnerid,
        ], 'attemptnumber DESC');
        $assigngrade = empty($assigngrades) ? null : reset($assigngrades);
        if ($assigngrade) {
            $comments = $repo->read_records('assignfeedback_comments', [
                'assignment' => $item->iteminstance,
                'grade' => $assigngrade->id,
            ]);
            $comment = empty($comments) ? null : reset($comments);
            $feedbacktext = $comment->commenttext ?? null;
            $feedbackpresent = $hastext($feedbacktext);
        }
    }

    $raw = (float) $finalgrade;
    $min = (float) ($item->grademin ?? 0);
    $max = (float) ($item->grademax ?? 0);
    $statement = [
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'https://w3id.org/xapi/tla/verbs/scored',
            'display' => [
                'en' => 'Scored',
            ],
        ],
        'object' => $object,
        'result' => [
            'extensions' => [
                'https://w3id.org/xapi/ocsw/extensions/feedback_present' => $feedbackpresent,
            ],
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'contextActivities' => [
                // The assignment is the parent of its submission. Any other
                // activity is the object itself, so it is not its own parent.
                'parent' => utils\context_activities\get_parent($config, (int) $cm->id, $isassign),
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ];
    if ($max > $min && $raw >= $min && $raw <= $max) {
        $statement['result']['score'] = utils\get_score($raw, $min, $max);
    }
    if ($feedbackpresent && utils\is_sending_content($config) && !empty($config['send_grading_feedback'])) {
        $statement['result']['response'] = utils\redact_free_text(
            $config,
            (string) $feedbacktext,
            (int) $event->courseid,
            [$learnerid]
        );
    }
    return [$statement];
}
