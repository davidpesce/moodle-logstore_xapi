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
 * Map the Moodle events to transformers.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer;

use src\transformer\utils\event_functions as event_functions;

/**
 * Return a map of the Moodle events to their transformers.
 *
 * @return array
 */
function get_event_function_map(): array {

    $availableevents = event_functions\event_functions();

    $environmentevents = class_exists("report_eventlist_list_generator") ?
        array_keys(\report_eventlist_list_generator::get_all_events_list(false)) : array_keys($availableevents);

    return array_filter($availableevents, function($k) use ($environmentevents) {
        return in_array($k, $environmentevents);
    }, ARRAY_FILTER_USE_KEY);

}
