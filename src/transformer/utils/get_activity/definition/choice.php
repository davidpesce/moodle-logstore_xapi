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
 * Transformer utilities for creating Moodle Choice xAPI Activity object definitions.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity\definition\choice;

use src\transformer\utils;

/**
 * The options of a Choice activity: the text of each option, keyed by option id.
 *
 * The definition and the response both take their choice ids from these, through
 * cmi\get_choice_ids().
 *
 * @param array $config The transformer config settings.
 * @param int $choiceid The Choice activity's id.
 * @return array
 */
function get_choice_options(array $config, int $choiceid): array {
    return array_map(function ($option) {
        return $option->text;
    }, $config['repo']->read_records('choice_options', ['choiceid' => $choiceid], 'id ASC'));
}

/**
 * Transformer util for creating choice definitions
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $choice The choice object.
 * @param string $lang The language.
 * @return array
 */
function get_choice_definition(
    array $config,
    \stdClass $choice,
    string $lang
) {
    return utils\get_activity\definition\cmi\choice(
        $config,
        $choice->name,
        utils\get_string_html_removed($choice->intro),
        get_choice_options($config, $choice->id),
        $lang
    );
}
