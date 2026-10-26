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
 * Transform for the quiz question manually graded event.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_quiz;

use src\transformer\utils;

/**
 * Transform for the quiz question manually graded event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function question_manually_graded(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $instructor = $repo->read_record_by_id('user', $event->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $lang = utils\get_course_lang($course);
    [
        'attemptid' => $attemptid,
        'quizid' => $quizid,
        'slot' => $slot,
    ] = utils\decode_other($event->other);
    $attempt = $repo->read_record_by_id('quiz_attempts', (int) $attemptid);
    $quiz = $repo->read_record_by_id('quiz', (int) $quizid);
    $user = $repo->read_record_by_id('user', $attempt->userid);
    $questionattempts = $repo->read_records(
        'question_attempts',
        ['questionusageid' => (int) $attempt->uniqueid],
    );
    if (empty($questionattempts)) {
        return [];
    }
    $questionattempt = reset($questionattempts);
    $questionattemptsteps = $repo->read_records(
        'question_attempt_steps',
        ['questionattemptid' => $questionattempt->id],
        'sequencenumber DESC'
    );
    if (empty($questionattemptsteps)) {
        return [];
    }
    $step = reset($questionattemptsteps);
    $rawscore = (float) ($step->fraction ?? 0);
    $minscore = (float) ($questionattempt->minfraction ?? 0);
    $maxscore = (float) ($questionattempt->maxfraction ?? 0);

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => utils\get_verb('scored', $config, $lang),
        'object' => [
            ...utils\get_activity\base(),
            'id' => $config['app_url']
                . '/review.php?attempt=' . $attempt->id
                . '&slot=' . $slot,
            'definition' => [
                'type' => 'http://activitystrea.ms/schema/1.0/review#slot',
                'name' => [
                    $lang => $quiz->name . ' Review Slot ' . $slot,
                ],
            ],
        ],
        'result' => [
            'score' => [
                'min' => $minscore,
                'max' => $maxscore,
                'raw' => $rawscore,
                'scaled' => utils\get_scaled_score($rawscore, $minscore, $maxscore),
            ],
        ],
        'context' => [
            ...utils\get_context_base($config, $event, $lang, $course),
            'instructor' => utils\get_user($config, $instructor),
            'contextActivities' => [
                'parent' => [
                    utils\get_activity\quiz_review($config, $attemptid),
                    utils\get_activity\quiz_attempt($config, $attemptid, $event->contextinstanceid),
                    ...utils\context_activities\get_parent(
                        $config,
                        $event->contextinstanceid,
                        true
                    ),
                ],
                'category' => [
                    utils\get_activity\site($config),
                ],
            ],
        ],
    ]];
}
