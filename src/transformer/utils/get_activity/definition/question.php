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
 * Transformer util for creating multichoice definitions.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 * @param ?string $rightanswer The correct answer, not always available.
 * @return array
 */
function get_multichoice_definition(
    array $config,
    \stdClass $question,
    string $lang,
    ?string $rightanswer = null
) {
    $repo = $config['repo'];
    $answers = $repo->read_records('question_answers', [
        'question' => $question->id,
    ]);

    $choices = array_values(
        array_map(
            function ($answer) {
                return utils\get_string_html_removed($answer->answer);
            },
            $answers
        )
    );

    return cmi\choice(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $choices,
        $lang,
        // The right answer lists every option that must be chosen, together.
        (!is_null($rightanswer))
            ? [explode('; ', utils\get_string_html_removed($rightanswer))]
            : null
    );
}


/**
 * Transformer util for creating match definitions
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 */
function get_match_definition(array $config, \stdClass $question, string $lang) {
    $repo = $config['repo'];
    $subqs = $repo->read_records('qtype_match_subquestions', [
        'questionid' => $question->id,
    ]);

    // A subquestion with no stem is a distractor: its answer is offered but matches nothing.
    $source = [];
    $target = [];
    $pairs = [];
    foreach ($subqs as $subq) {
        $stem = utils\get_string_html_removed($subq->questiontext);
        $target[] = $subq->answertext;
        if ($stem !== '') {
            $source[] = $stem;
            $pairs[] = [$stem, $subq->answertext];
        }
    }

    return cmi\matching(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $source,
        $target,
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
 * Transformer util for creating numerical definitions
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $question The question object.
 * @param string $lang The language.
 */
function get_numerical_definition(array $config, \stdClass $question, string $lang) {
    [
        'min' => $min,
        'max' => $max,
        'target' => $target
    ] = utils\quiz_question\get_numerical_answer($config, $question->id);

    return cmi\numeric(
        $config,
        $question->name,
        utils\get_string_html_removed($question->questiontext),
        $lang,
        [
            cmi\numeric_pattern(
                $min,
                $max,
                // If we have an exact match, send that.
                ($min === $target && $max === $target) ? $target : null
            ),
        ]
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
