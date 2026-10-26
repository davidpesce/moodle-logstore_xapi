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
 * Transformer utilities for creating CMI numeric xAPI Activity object definitions.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity\definition\cmi;

use src\transformer\utils;

/**
 * Format one correct numeric response: an exact value, or a range "min[:]max" in which either
 * bound may be open.
 *
 * @param ?string $min The minimum numeric value.
 * @param ?string $max The maximum numeric value.
 * @param ?string $exact An exact correct value, used instead of the range.
 * @return ?string The pattern, or null when there is no value and no bound.
 */
function numeric_pattern(?string $min, ?string $max, ?string $exact = null): ?string {
    if (!is_null($exact)) {
        return $exact;
    }
    // With no exact value and no bound there is no correct answer to state, rather than a
    // meaningless "[:]".
    if (is_null($min) && is_null($max)) {
        return null;
    }
    return $min . '[:]' . $max;
}

/**
 * Transformer util for creating numeric definitions
 *
 * @param array $config The transformer config settings.
 * @param string $name The activity name.
 * @param ?string $description The activity description.
 * @param string $lang The language.
 * @param array $correctresponses The correct responses, each from numeric_pattern(). Each is
 *                                correct on its own. Sent only with send_response_choices on.
 * @return array
 */
function numeric(
    array $config,
    string $name,
    ?string $description,
    string $lang,
    array $correctresponses = []
) {
    $correctresponses = array_values(array_filter($correctresponses, function ($pattern) {
        return !is_null($pattern);
    }));
    return [
        ...common($config, $name, $description, $lang),
        'interactionType' => 'numeric',
        // Correct answers are only sent when the admin has chosen to send them.
        ...($config['send_response_choices'] && !empty($correctresponses)
            ? ['correctResponsesPattern' => $correctresponses]
            : []),
    ];
}
