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
 * Utility to make human-readable but id-safe strings.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Convert a string into an id-safe "slug".
 *
 * Letters and digits from any script are kept, so non-English answers (for example Japanese)
 * still produce meaningful, distinct ids. ASCII input produces the same slug as before.
 * If nothing is left after cleaning (for example answers made only of symbols such as "○" or "×"),
 * a short hash of the original string is used, so the id is never empty and is stable for the
 * same input. This keeps the ids in activity definitions and in result responses consistent,
 * since both are derived from the answer text.
 *
 * @param string $string The input string to be converted into a slug.
 * @return string The slug created from the input string.
 */
function slugify($string) {
    $original = (string) $string;

    // Convert the string to lowercase.
    $string = \core_text::strtolower($original);

    // Replace spaces, underscores and consecutive whitespace with a single dash.
    $string = preg_replace('/[\s_]+/u', '-', $string);

    // Remove anything that is not a letter, a number (in any script) or a dash.
    $string = preg_replace('/[^\p{L}\p{M}\p{N}-]+/u', '', $string ?? '');

    // Trim any trailing or leading dashes.
    $string = trim($string ?? '', '-');

    // Never return an empty id for a non-empty input.
    if ($string === '' && trim($original) !== '') {
        $string = 'h' . substr(sha1($original), 0, 12);
    }

    return $string;
}
