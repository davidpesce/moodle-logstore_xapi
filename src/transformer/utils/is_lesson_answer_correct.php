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
 * Transformer utility for deciding whether a lesson answer is a correct one.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Whether a lesson answer is a correct one, as core decides it.
 *
 * With custom scoring, an answer is correct when it scores more than 0. Without it, core ignores
 * scores (they are not saved) and an answer is correct when its jump moves the learner forward,
 * as lesson::jumpto_is_correct() does: to the next page, the end of the lesson, an unseen or
 * random branch page, a cluster jump, or any page after this one. "This page" and earlier pages
 * are wrong answers.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $lesson The lesson.
 * @param \stdClass $page The lesson page the answer belongs to.
 * @param \stdClass $answer The lesson answer.
 * @return bool
 */
function is_lesson_answer_correct(array $config, \stdClass $lesson, \stdClass $page, \stdClass $answer): bool {
    if (!empty($lesson->custom)) {
        return ($answer->score ?? 0) > 0;
    }

    $jumpto = (int) ($answer->jumpto ?? 0);
    // LESSON_NEXTPAGE, LESSON_EOL, LESSON_UNSEENBRANCHPAGE, LESSON_RANDOMPAGE, LESSON_CLUSTERJUMP.
    if (in_array($jumpto, [-1, -9, -50, -60, -80], true)) {
        return true;
    }
    // LESSON_THISPAGE (0), LESSON_PREVIOUSPAGE (-40) and any other special value.
    if ($jumpto <= 0) {
        return false;
    }

    // A page id: correct if it comes after this page.
    $nextpageids = [];
    foreach ($config['repo']->read_records('lesson_pages', ['lessonid' => $page->lessonid]) as $lessonpage) {
        $nextpageids[$lessonpage->id] = (int) ($lessonpage->nextpageid ?? 0);
    }
    $pageid = $nextpageids[$page->id] ?? 0;
    while ($pageid !== 0 && isset($nextpageids[$pageid])) {
        if ($pageid === $jumpto) {
            return true;
        }
        $next = $nextpageids[$pageid];
        // Guard against a broken chain that loops.
        unset($nextpageids[$pageid]);
        $pageid = $next;
    }
    return false;
}
