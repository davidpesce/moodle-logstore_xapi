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
 * Transformer utility for reading an activity's grade item.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Read the grade item for an activity's grade.
 *
 * An activity can have several grade items: outcomes, and modules with more than one grade
 * (forum ratings and whole-forum grading, for example). The activity's own grade is the "mod"
 * item with itemnumber 0.
 *
 * @param array $config The transformer config settings.
 * @param string $module The module name, such as "quiz".
 * @param int $instance The activity instance id.
 * @return ?\stdClass The grade item, or null if the activity has none.
 */
function get_module_grade_item(array $config, string $module, int $instance): ?\stdClass {
    $gradeitems = $config['repo']->read_records('grade_items', [
        'itemtype' => 'mod',
        'itemmodule' => $module,
        'iteminstance' => $instance,
        'itemnumber' => 0,
    ]);
    return empty($gradeitems) ? null : reset($gradeitems);
}
