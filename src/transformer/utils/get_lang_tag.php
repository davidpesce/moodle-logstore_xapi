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
 * Transformer utility to turn a Moodle language code into a language tag.
 *
 * @package   logstore_xapi
 * @copyright David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Transformer utility to turn a Moodle language code into an RFC 5646 language tag.
 *
 * @param ?string $lang A Moodle language code, such as en or pt_br.
 * @param string $fallback The tag to use when $lang is empty or not a valid tag.
 * @return string
 */
function get_lang_tag(?string $lang, string $fallback = 'en') {
    if (is_null($lang) || $lang === '') {
        return $fallback;
    }

    // Ensure en_US and the like get corrected to the standard en-US.
    $preppedlang = mb_ereg_replace('_', '-', $lang);

    // Ensure valid language format.
    return mb_ereg_match('^[a-zA-Z]{2}(-[a-zA-Z]{2})?$', $preppedlang) ? $preppedlang : $fallback;
}
