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

/*
 * @package    logstore_xapi
 * @copyright  2026 David Pesce <david.pesce@exputo.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var SELECTORS = {
        action: '.logstore-xapi-groupaction'
    };

    /**
     * Set every checkbox in a group to the given state.
     *
     * Each event renders its own wrapper carrying data-routegroup, so a group
     * is spread across many containers rather than held in a single one.
     *
     * @param {string} group   The group identifier, matching data-routegroup.
     * @param {boolean} checked Whether the checkboxes should be checked.
     */
    var setGroup = function(group, checked) {
        var boxes = document.querySelectorAll('[data-routegroup="' + group + '"] input[type="checkbox"]');
        Array.prototype.forEach.call(boxes, function(box) {
            box.checked = checked;
        });
    };

    /**
     * Handle a click on a select all / deselect all control.
     *
     * @param {Event} e The click event.
     */
    var handleClick = function(e) {
        var el = e.target.closest(SELECTORS.action);
        if (!el) {
            return;
        }
        e.preventDefault();
        setGroup(el.getAttribute('data-group-target'), el.getAttribute('data-group-action') === 'selectall');
    };

    return {
        /**
         * Attach the select all / deselect all behaviour.
         *
         * The handler is delegated from the document, so the controls do not
         * need to exist when this runs.
         */
        init: function() {
            document.addEventListener('click', handleClick);
        }
    };
});
