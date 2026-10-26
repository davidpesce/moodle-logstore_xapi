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
 * Transformer utilities for creating CMI fill-in xAPI Activity object definitions.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity\definition\cmi;

use src\transformer\utils;

/**
 * Transformer util for creating fill-in definitions.
 *
 * @param array $config The transformer config settings.
 * @param string $name The activity name.
 * @param ?string $description The activity description.
 * @param string $lang The language.
 * @param ?array $correctanswers Optional array of correct string answers, each correct on its
 *                               own. Sent only with send_response_choices on.
 */
function fill_in(
    array $config,
    string $name,
    ?string $description,
    string $lang,
    ?array $correctanswers = null
) {
    return [
        ...common($config, $name, $description, $lang),
        'interactionType' => 'fill-in',
        // Correct answers are only sent when the admin has chosen to send them.
        ...(
            $config['send_response_choices'] && !is_null($correctanswers)
                ? [
                    // Each pattern is a complete correct response, so alternatives are separate
                    // patterns; "[,]" would mean all of them together.
                    'correctResponsesPattern' => array_values($correctanswers),
                ]
                : []
        ),
    ];
}
