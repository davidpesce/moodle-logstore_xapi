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
 * The id of each choice in a choice interaction, keyed as the choices are.
 *
 * An id is made from the choice's text. Two choices can give the same id ("Yes" and "yes", or
 * two answers with the same text), and every choice needs its own, so a later choice gets a
 * number: "yes-2". Definitions and responses must both take their ids from here, with the same
 * choices in the same order, so that a response is always one of the defined choices.
 *
 * @param array $choices The choice texts.
 * @return array The ids, with the keys of $choices.
 */
function get_choice_ids(array $choices): array {
    $ids = [];
    foreach ($choices as $key => $choice) {
        $base = utils\slugify((string) $choice);
        $id = $base;
        for ($n = 2; in_array($id, $ids, true); $n++) {
            $id = $base . '-' . $n;
        }
        $ids[$key] = $id;
    }
    return $ids;
}

/**
 * The correct responses of a choice interaction, as patterns of choice ids.
 *
 * Each pattern keeps the order of its keys, which is the correct order in a sequencing
 * interaction. Keys that aren't choices are left out, and so are patterns left empty.
 *
 * @param array $ids The choice ids, from get_choice_ids().
 * @param array $correctresponses Lists of keys of the choices, as choice() takes them.
 * @return array
 */
function get_choice_patterns(array $ids, array $correctresponses): array {
    $patterns = [];
    foreach ($correctresponses as $rightkeys) {
        $rightids = [];
        foreach ($rightkeys as $key) {
            if (isset($ids[$key])) {
                $rightids[] = $ids[$key];
            }
        }
        if (!empty($rightids)) {
            $patterns[] = implode('[,]', $rightids);
        }
    }
    return $patterns;
}

/**
 * Transformer util for creating choice definitions
 *
 * @param array $config The transformer config settings.
 * @param string $name The activity name.
 * @param ?string $description The activity description.
 * @param array $choices The choice texts. Responses must use get_choice_ids() with the same array.
 * @param string $lang The language.
 * @param ?array $correctresponses The correct responses, each a list of keys of $choices that are
 *                                right together: [[a], [b]] when a or b is right on its own,
 *                                [[a, b]] when both must be chosen. Omit for questions with no
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
    $ids = get_choice_ids($choices);
    $cmichoices = $config['send_response_choices']
        ? array_map(
            function ($key) use ($choices, $ids, $lang) {
                return [
                    'id' => $ids[$key],
                    'description' => [
                        $lang => $choices[$key],
                    ],
                ];
            },
            // Callers often pass records from get_records(), which are keyed by id. Use the keys
            // in order, so that choices is a JSON array, not an object.
            array_keys($choices)
        )
        : null;

    $patterns = get_choice_patterns($ids, $correctresponses ?? []);

    return [
        ...common($config, $name, $description, $lang),
        'interactionType' => 'choice',
        // A correctResponsesPattern says which response is correct, so it is only sent when there
        // is a correct answer. Claiming that every choice together was correct made an LRS mark
        // survey and Choice activity answers as wrong.
        ...($config['send_response_choices'] && !empty($cmichoices) && !empty($patterns)
            ? ['correctResponsesPattern' => $patterns]
            : []),
        ...($config['send_response_choices'] && !empty($cmichoices)
            ? ['choices' => $cmichoices]
            : []),
    ];
}
