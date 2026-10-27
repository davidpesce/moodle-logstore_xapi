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
 * Transformer utilities for reading matching questions (match and randomsamatch).
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\quiz_question;

use src\transformer\utils;

/**
 * Read a matching question's stems, choices and right answers from its subquestions, as
 * qtype_match does: a subquestion with no stem is a distractor (a choice only), and choices
 * with the same text are one choice, keyed by the first subquestion that has it.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The match question.
 * @return array ['stems' => [key => text], 'choices' => [key => text], 'right' => [stem key => choice key]]
 */
function get_match_subquestions(array $config, \stdClass $question): array {
    $subquestions = $config['repo']->read_records('qtype_match_subquestions', ['questionid' => $question->id], 'id ASC');
    $stems = $choices = $right = [];
    foreach ($subquestions as $subquestion) {
        $key = array_search($subquestion->answertext, $choices, true);
        if ($key === false) {
            $key = $subquestion->id;
            $choices[$key] = $subquestion->answertext;
        }
        $stem = utils\get_string_html_removed($subquestion->questiontext);
        if ($stem !== '') {
            $stems[$subquestion->id] = $stem;
            $right[$subquestion->id] = $key;
        }
    }
    return ['stems' => $stems, 'choices' => $choices, 'right' => $right];
}

/**
 * Read a matching question attempt from its steps, as the question engine stores it.
 *
 * The first step records the order the stems and choices were shown in (_stemorder,
 * _choiceorder); for randomsamatch it also records the stems drawn for this attempt, their
 * right choices and the choice texts, which are in no other table. Each response step stores
 * the choice picked for each stem as sub0, sub1, …: the 1-based position of the choice in
 * _choiceorder for the stem at that position in _stemorder, or 0 for none.
 *
 * The text summary in question_attempts.responsesummary is not used: its "stem -> choice; …"
 * format cannot be split reliably when a stem or choice contains "; " or " -> ".
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $questionattempt The question attempt.
 * @param \stdClass $question The match or randomsamatch question.
 * @return array ['stems' => [key => text], 'choices' => [key => text], 'right' => [stem key => choice key],
 *               'response' => [stem key => choice key]], with stems and choices in the order they were shown.
 */
function get_matching_attempt(array $config, \stdClass $questionattempt, \stdClass $question): array {
    $steps = get_question_attempt_step_data($config, $questionattempt);
    $first = reset($steps) ?: [];

    ['stems' => $stems, 'choices' => $choices, 'right' => $right] = $question->qtype === 'randomsamatch'
        ? get_randomsamatch_items($first)
        : get_match_subquestions($config, $question);

    $stemorder = array_values(array_filter(explode(',', $first['_stemorder'] ?? '')));
    $choiceorder = array_values(array_filter(explode(',', $first['_choiceorder'] ?? '')));
    $stems = order_by_keys($stems, $stemorder);
    $choices = order_by_keys($choices, $choiceorder);
    $right = array_intersect_key($right, $stems);

    // The response is in the latest step that saved one.
    $response = [];
    foreach (array_reverse($steps) as $data) {
        if (!preg_grep('/^sub\d+$/', array_keys($data))) {
            continue;
        }
        foreach ($stemorder as $position => $stemid) {
            $picked = (int) ($data['sub' . $position] ?? 0);
            if ($picked > 0 && isset($choiceorder[$picked - 1])) {
                $response[$stemid] = $choiceorder[$picked - 1];
            }
        }
        break;
    }

    return ['stems' => $stems, 'choices' => $choices, 'right' => $right, 'response' => $response];
}

/**
 * Read a randomsamatch attempt's stems, choices and right answers from its first step. The stems
 * are the short-answer questions drawn for the attempt, which no other table records.
 *
 * @param array $first The first step's data.
 * @return array ['stems' => [key => text], 'choices' => [key => text], 'right' => [stem key => choice key]]
 */
function get_randomsamatch_items(array $first): array {
    $stems = $choices = $right = [];
    foreach (array_filter(explode(',', $first['_stemorder'] ?? '')) as $stemid) {
        $stems[$stemid] = utils\get_string_html_removed($first['_stem_' . $stemid] ?? '');
        $right[$stemid] = $first['_right_' . $stemid] ?? null;
    }
    foreach (array_filter(explode(',', $first['_choiceorder'] ?? '')) as $choicekey) {
        $choices[$choicekey] = $first['_choice_' . $choicekey] ?? '';
    }
    return ['stems' => $stems, 'choices' => $choices, 'right' => $right];
}

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
 * Keep the items the attempt showed, in the order it showed them. With no order recorded (no
 * steps), every item is kept, in its own order.
 *
 * @param array $items The items, keyed.
 * @param array $order The keys, in order.
 * @return array
 */
function order_by_keys(array $items, array $order): array {
    if (empty($order)) {
        return $items;
    }
    $ordered = [];
    foreach ($order as $key) {
        if (array_key_exists($key, $items)) {
            $ordered[$key] = $items[$key];
        }
    }
    return $ordered;
}
