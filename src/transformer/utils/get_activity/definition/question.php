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
 * Transformer utilities for creating Question xAPI Activity object definitions.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 *            Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity\definition\question;

use src\transformer\utils;
use src\transformer\utils\get_activity\definition\cmi;

/**
 * Transformer util for creating essay definitions
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 */
function get_essay_definition(array $config, \stdClass $question, string $lang) {
    return cmi\long_fill_in(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $lang
    );
}

/**
 * The choices of a multiple-choice question: the text of each answer, keyed by answer id.
 *
 * Responses take their ids from these, through cmi\get_choice_ids(), as the definition does.
 *
 * @param array $answers The question's answers, from question_answers in id order.
 * @return array
 */
function get_multichoice_choices(array $answers): array {
    return array_map(function ($answer) {
        return utils\get_string_html_removed($answer->answer);
    }, $answers);
}

/**
 * Transformer util for creating multichoice definitions.
 *
 * The correct responses come from the answers' grades, as the question engine's own correct
 * response does. In a single-answer question each answer with full marks is right on its own; in
 * a multiple-answer question (and qtype_multichoiceset) every answer with a positive grade must
 * be chosen, together.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 * @return array
 */
function get_multichoice_definition(array $config, \stdClass $question, string $lang) {
    $repo = $config['repo'];
    $answers = $repo->read_records('question_answers', ['question' => $question->id], 'id ASC');

    $single = false;
    if ($question->qtype === 'multichoice') {
        $options = $repo->read_records('qtype_multichoice_options', ['questionid' => $question->id]);
        $option = reset($options);
        $single = !$option || !empty($option->single);
    }

    if ($single) {
        $correctresponses = array_map(function ($answerid) {
            return [$answerid];
        }, array_keys(array_filter($answers, function ($answer) {
            return (float) $answer->fraction >= 1.0;
        })));
    } else {
        $correctresponses = [array_keys(array_filter($answers, function ($answer) {
            return (float) $answer->fraction > 0;
        }))];
    }

    return cmi\choice(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        get_multichoice_choices($answers),
        $lang,
        $correctresponses
    );
}

/**
 * Transformer util for creating matching (match and randomsamatch) definitions.
 *
 * With an attempt, the stems and choices are the ones it showed, in the order it showed them;
 * randomsamatch draws its stems per attempt, so only the attempt has them. Without one (a
 * question created in the question bank), match questions use their subquestions and
 * randomsamatch questions have no stems to list.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 * @param ?array $attempt The attempt, from quiz_question\get_matching_attempt(), if there is one.
 * @return array
 */
function get_match_definition(array $config, \stdClass $question, string $lang, ?array $attempt = null) {
    if (is_null($attempt)) {
        $attempt = $question->qtype === 'randomsamatch'
            ? ['stems' => [], 'choices' => [], 'right' => []]
            : utils\quiz_question\get_match_subquestions($config, $question);
    }

    $pairs = [];
    foreach ($attempt['right'] as $stemkey => $choicekey) {
        if (isset($attempt['stems'][$stemkey], $attempt['choices'][$choicekey])) {
            $pairs[] = [$attempt['stems'][$stemkey], $attempt['choices'][$choicekey]];
        }
    }

    return cmi\matching(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        array_values($attempt['stems']),
        array_values($attempt['choices']),
        $pairs,
        $lang
    );
}

/**
 * Transformer util for creating select missing words (gapselect) definitions.
 *
 * Each gap is a source and each choice a target. A choice can be right for several gaps and a
 * group can have distractors, so this is a matching interaction rather than a sequence. In the
 * question text, [[n]] is a gap whose right answer is the question's nth answer.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 * @return array
 */
function get_gapselect_definition(array $config, \stdClass $question, string $lang) {
    $answers = array_values($config['repo']->read_records('question_answers', [
        'question' => $question->id,
    ], 'id ASC'));
    $choices = array_map(function ($answer) {
        return utils\get_string_html_removed($answer->answer);
    }, $answers);

    preg_match_all('/\[\[(\d+)\]\]/', $question->questiontext, $matches);
    $gaps = [];
    $pairs = [];
    foreach ($matches[1] as $i => $choicenumber) {
        $gap = get_gap_name($i + 1);
        $gaps[] = $gap;
        $pairs[] = [$gap, $choices[$choicenumber - 1] ?? ''];
    }

    return cmi\matching(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $gaps,
        $choices,
        $pairs,
        $lang
    );
}

/**
 * The name of a gap in a select missing words question, which is also its source id.
 *
 * @param int $number The gap's position in the question text, from 1.
 * @return string
 */
function get_gap_name(int $number): string {
    return 'Gap ' . $number;
}

/**
 * Transformer util for creating numerical definitions. Each answer with full marks is a correct
 * response.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 */
function get_numerical_definition(array $config, \stdClass $question, string $lang) {
    return cmi\numeric(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $lang,
        array_map(function ($range) {
            return cmi\numeric_pattern(
                $range['min'],
                $range['max'],
                // If we have an exact match, send that.
                ($range['min'] === $range['target'] && $range['max'] === $range['target']) ? $range['target'] : null
            );
        }, utils\quiz_question\get_numerical_answers($config, $question->id))
    );
}

/**
 * Transformer util for creating shortanswer definitions
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 */
function get_shortanswer_definition(array $config, \stdClass $question, string $lang) {
    return cmi\fill_in(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $lang
    );
}

/**
 * Whether "true" is the correct answer to a true/false question.
 *
 * The answer texts are stored in the language the question was created in ("Vrai", "正しい"),
 * so they cannot be compared with 'True'. question_truefalse records which answer is the
 * true choice; the answer with full marks is the correct one.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @return bool
 */
function get_true_false_correct_answer(array $config, \stdClass $question): bool {
    $repo = $config['repo'];
    $answers = $repo->read_records('question_answers', [
        'question' => $question->id,
    ]);
    $correctanswers = array_filter($answers, function ($answer) {
        return $answer->fraction == 1.0;
    });
    $correctanswer = reset($correctanswers);
    $options = $repo->read_record('question_truefalse', ['question' => $question->id]);
    return $correctanswer && $correctanswer->id == $options->trueanswer;
}

/**
 * Transformer util for creating true/false definitions
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 * @return array
 */
function get_true_false_definition(array $config, \stdClass $question, string $lang) {
    $correctanswer = get_true_false_correct_answer($config, $question);

    return cmi\true_false(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $lang,
        $correctanswer
    );
}

/**
 * Generic handler for question definitions.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question.
 * @param string $lang The language to use.
 * @return array
 */
function get_definition(array $config, \stdClass $question, string $lang) {
    switch ($question->qtype) {
        case 'essay':
            return get_essay_definition($config, $question, $lang);
        case 'gapselect':
            return get_gapselect_definition($config, $question, $lang);
        case 'truefalse':
            return get_true_false_definition($config, $question, $lang);
        case 'randomsamatch':
        case 'match':
            return get_match_definition($config, $question, $lang);
        case 'shortanswer':
            return get_shortanswer_definition($config, $question, $lang);
        case 'multichoice':
        case 'multichoiceset':
            return get_multichoice_definition($config, $question, $lang);
        case 'numerical':
            return get_numerical_definition($config, $question, $lang);
        default:
            return [];
    }
}
