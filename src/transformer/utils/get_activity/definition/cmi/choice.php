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
 * Transformer utilities for creating CMI choice xAPI Activity object definitions.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity\definition\cmi;

use src\transformer\utils;

/**
 * Transformer util for creating choice definitions
 *
 * @param array $config The transformer config settings.
 * @param string $name The activity name.
 * @param ?string $description The activity description.
 * @param array $choices The choices available.
 * @param string $lang The language.
 * @param ?array $correctresponses The correct responses, each a list of the options that are
 *                                right together: [['A'], ['B']] when A or B is right on its own,
 *                                [['A', 'B']] when both must be chosen. Omit for questions with no
 *                                correct answer (surveys, Choice activities), and no
 *                                correctResponsesPattern is sent.
 * @return array
 */
function choice(
    array $config,
    string $name,
    ?string $description,
    array $choices,
    string $lang,
    ?array $correctresponses = null
) {
    $cmichoices = ($config['send_response_choices'] && !is_null($choices))
        ? array_map(
            function ($choice) use ($lang) {
                return [
                    'id' => utils\slugify($choice),
                    'description' => [
                        $lang => $choice,
                    ],
                ];
            },
            // Callers often pass records from get_records(), which are keyed by id. Renumber them
            // so that choices is a JSON array, not an object.
            array_values($choices)
        )
        : null;

    $correctresponses = array_filter($correctresponses ?? [], function ($rightchoices) {
        return !empty($rightchoices);
    });

    return [
        ...common($config, $name, $description, $lang),
        'interactionType' => 'choice',
        // A correctResponsesPattern says which response is correct, so it is only sent when there
        // is a correct answer. Claiming that every choice together was correct made an LRS mark
        // survey and Choice activity answers as wrong.
        ...($config['send_response_choices'] && !empty($cmichoices) && !empty($correctresponses)
            ? [
                'correctResponsesPattern' => array_values(array_map(function ($rightchoices) {
                    return implode('[,]', array_map(function ($rightchoice) {
                        return utils\slugify($rightchoice);
                    }, $rightchoices));
                }, $correctresponses)),
            ]
            : []),
        ...($config['send_response_choices'] && !empty($cmichoices)
            ? ['choices' => $cmichoices]
            : []),
    ];
}
