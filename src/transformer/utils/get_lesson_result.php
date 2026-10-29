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
 * Transformer utility for retrieving the result of a lesson retake.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for the result of the lesson retake a lesson_ended event ended.
 *
 * The score is that retake's grade, from lesson_grades, not the gradebook grade, which follows the
 * lesson's handling of retakes (mean or highest). Core saves a retake's grade just after its
 * lesson_ended event, so it is the user's first grade completed at or after the event. A retake
 * with no questions answered has no grade.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $lesson The lesson object.
 * @param \stdClass $event The lesson ended event.
 * @return array
 */
function get_lesson_result(array $config, \stdClass $lesson, \stdClass $event) {
    $repo = $config['repo'];
    $result = [
        'completion' => true,
    ];

    // Score and success if exists. The lesson's grade is the maximum grade in points.
    $gradeitem = get_module_grade_item($config, 'lesson', $lesson->id);
    $maxgrade = (float) ($lesson->grade ?? 0);
    if (empty($gradeitem) || $maxgrade <= 0) {
        return $result;
    }

    $grades = $repo->read_records('lesson_grades', [
        'lessonid' => $lesson->id,
        'userid' => $event->userid,
    ], 'completed ASC, id ASC');
    foreach ($grades as $grade) {
        if ((int) $grade->completed >= (int) $event->timecreated) {
            // A lesson grade is a percentage.
            $result['score'] = get_score((float) $grade->grade * $maxgrade / 100, 0, $maxgrade);
            $success = get_success($result['score']['raw'], $gradeitem);
            if (!is_null($success)) {
                $result['success'] = $success;
            }
            break;
        }
    }

    return $result;
}
