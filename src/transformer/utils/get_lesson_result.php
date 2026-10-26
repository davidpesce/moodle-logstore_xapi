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
 * Transformer utility for retrieving the result/success state from a module completion object.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility for retrieving the result/success state from a module completion object.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $lesson The lesson object.
 * @param int $userid User ID who completed the lesson
 * @return object
 */
function get_lesson_result(array $config, \stdClass $lesson, int $userid) {
    $repo = $config['repo'];
    $result = [
        'completion' => true,
    ];

    // Score and success if exists.
    $gradeitem = get_module_grade_item($config, 'lesson', $lesson->id);

    if (!empty($gradeitem)) {
        $grades = $repo->read_records('grade_grades', [
            'itemid' => $gradeitem->id,
            'userid' => $userid,
        ], 'timemodified DESC');
        if (!empty($grades)) {
            $grade = reset($grades);
            $min = floatval($grade->rawgrademin ?: 0);
            $max = floatval($grade->rawgrademax ?: 0);
            $result['score'] = get_score(floatval($grade->rawgrade ?: 0), $min, $max);

            $success = get_success($result['score']['raw'], $gradeitem);
            if (!is_null($success)) {
                $result['success'] = $success;
            }
        }
    }

    return $result;
}
