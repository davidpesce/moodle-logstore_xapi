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
 * Transformer utilities for reading a quiz question attempt's steps.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\quiz_question;

/**
 * The data of each step of a question attempt, in order.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $questionattempt The question attempt.
 * @return array Step data, name => value, one array per step.
 */
function get_question_attempt_step_data(array $config, \stdClass $questionattempt): array {
    $repo = $config['repo'];
    $steps = $repo->read_records('question_attempt_steps', ['questionattemptid' => $questionattempt->id], 'sequencenumber ASC');
    return array_values(array_map(function ($step) use ($repo) {
        $data = [];
        foreach ($repo->read_records('question_attempt_step_data', ['attemptstepid' => $step->id]) as $row) {
            $data[$row->name] = $row->value;
        }
        return $data;
    }, $steps));
}

/**
 * The latest value a question attempt's steps saved for a variable, as the question engine's
 * get_last_qt_var() reads it.
 *
 * @param array $steps Step data, from get_question_attempt_step_data().
 * @param string $name The variable.
 * @return ?string The value, or null if no step saved one.
 */
function get_last_step_value(array $steps, string $name): ?string {
    foreach (array_reverse($steps) as $data) {
        if (array_key_exists($name, $data)) {
            return $data[$name];
        }
    }
    return null;
}

/**
 * Whether a question was answered correctly, from the state the question engine graded it with.
 *
 * The state is the one the question had when the event happened: the latest step at or before
 * the event. A teacher's later manual grade has its own event. An unanswered question (gave up)
 * scored nothing, so it was not answered correctly.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $questionattempt The question attempt.
 * @param \stdClass $event The event.
 * @return ?bool True when right; false when wrong, partly right or not answered; null when the
 *               question has no grade (it needs grading, or is not graded at all).
 */
function get_question_attempt_success(array $config, \stdClass $questionattempt, \stdClass $event): ?bool {
    $steps = $config['repo']->read_records(
        'question_attempt_steps',
        ['questionattemptid' => $questionattempt->id],
        'sequencenumber DESC'
    );
    $step = reset($steps);
    foreach ($steps as $candidate) {
        if ((int) $candidate->timecreated <= (int) $event->timecreated) {
            $step = $candidate;
            break;
        }
    }
    if (!$step) {
        return null;
    }
    switch ($step->state) {
        case 'gradedright':
        case 'mangrright':
            return true;
        case 'gradedpartial':
        case 'gradedwrong':
        case 'gaveup':
        case 'mangrpartial':
        case 'mangrwrong':
        case 'mangaveup':
            return false;
        default:
            return null;
    }
}
