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
 * Transformer utility for reading the choices of a lesson question page.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * The choices of a true/false or multiple-choice lesson page: the text of each answer, keyed by
 * answer id.
 *
 * The definition and the response both take their choice ids from these, through
 * cmi\get_choice_ids().
 *
 * @param array $answers The page's lesson_answers, in id order.
 * @return array
 */
function get_lesson_choices(array $answers): array {
    return array_map(function ($answer) {
        return get_string_html_removed($answer->answer);
    }, $answers);
}
