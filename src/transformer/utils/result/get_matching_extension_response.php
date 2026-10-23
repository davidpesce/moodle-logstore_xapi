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
 * Transformer utility for the matching response extension.
 *
 * @package   logstore_xapi
 * @copyright 2026 taki-0105a
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\result;

use src\transformer\utils;

/**
 * Build the value of the http://learninglocker.net/xapi/cmi/matching/response extension.
 *
 * The keys are the source ids used in the activity definition (object.definition.source[].id),
 * not the raw question text. Raw text can contain characters such as dots, which document stores
 * like Elasticsearch read as nested field paths, so statements were rejected when a stem
 * contained for example "..". The values are kept as the selected answer text.
 *
 * An object is returned so the extension always encodes as a JSON object. As an array, ids that
 * happen to be 0, 1, 2... (stems such as "0." or "1.") would encode as a JSON list, and an empty
 * response as [] rather than {}.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $questionattempt The question attempt object.
 * @param string $lang The language.
 * @return \stdClass The selections keyed by source id. Empty when there is no response.
 */
function get_matching_extension_response(
    array $config,
    \stdClass $questionattempt,
    string $lang
) {
    if (is_null($questionattempt->responsesummary) || $questionattempt->responsesummary === '') {
        return new \stdClass();
    }

    return (object) array_reduce(
        explode('; ', $questionattempt->responsesummary),
        function ($reduction, $selection) {
            $split = explode("\n -> ", $selection);
            $selectionkey = utils\slugify($split[0]);
            // Never emit an empty key (see #891).
            if ($selectionkey === '') {
                return $reduction;
            }
            $reduction[$selectionkey] = $split[1] ?? null;
            return $reduction;
        },
        []
    );
}
