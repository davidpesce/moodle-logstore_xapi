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
 * Admin setting for event routes, grouped by component with friendly labels.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi;

/**
 * Grouped multi-checkbox setting for event routes.
 *
 * Extends the standard configmulticheckbox to render checkboxes grouped by
 * Moodle component (core, mod_quiz, mod_forum, etc.) with friendly labels
 * and select all/none toggles per group. Storage format is unchanged.
 */
class admin_setting_configroutes extends \admin_setting_configmulticheckbox {
    /**
     * Returns the HTML output for this setting.
     *
     * @param mixed $data Current setting data.
     * @param string $query Search query for highlighting.
     * @return string HTML output.
     */
    public function output_html($data, $query = '') {
        if (!$this->load_choices() || empty($this->choices)) {
            return '';
        }

        $fullname = $this->get_full_name();
        $groups = $this->group_events();

        $html = \html_writer::start_div('logstore-xapi-routes');

        foreach ($groups as $groupkey => $group) {
            $groupid = 'logstore_xapi_group_' . preg_replace('/[^a-z0-9]/', '_', strtolower($groupkey));

            // Group heading with select all / deselect all links.
            $html .= \html_writer::start_div('mt-3 mb-1');
            $html .= \html_writer::tag('strong', s($group['name']));
            $html .= ' ';
            $selectall = \html_writer::link('#', get_string('selectall'), [
                'class' => 'logstore-xapi-groupaction',
                'data-group-action' => 'selectall',
                'data-group-target' => $groupid,
            ]);
            $deselectall = \html_writer::link('#', get_string('deselectall'), [
                'class' => 'logstore-xapi-groupaction',
                'data-group-action' => 'deselectall',
                'data-group-target' => $groupid,
            ]);
            $html .= \html_writer::tag('small', $selectall . ' / ' . $deselectall, ['class' => 'text-muted']);
            $html .= \html_writer::end_div();

            // Checkboxes for this group.
            foreach ($group['events'] as $eventclass => $unused) {
                $checked = !empty($data[$eventclass]);
                $cbid = $this->get_id() . '_' . self::sanitize_id($eventclass);
                $friendlylabel = self::get_friendly_label($eventclass);

                $html .= \html_writer::start_div('form-check', ['data-routegroup' => $groupid]);
                $html .= \html_writer::empty_tag('input', [
                    'type' => 'hidden',
                    'name' => $fullname . '[' . s($eventclass) . ']',
                    'value' => '0',
                ]);

                $attrs = [
                    'type' => 'checkbox',
                    'id' => $cbid,
                    'name' => $fullname . '[' . s($eventclass) . ']',
                    'value' => '1',
                    'class' => 'form-check-input',
                ];
                if ($checked) {
                    $attrs['checked'] = 'checked';
                }
                $html .= \html_writer::empty_tag('input', $attrs);

                $label = s($friendlylabel) . ' '
                    . \html_writer::tag('small', s($eventclass), ['class' => 'text-muted']);
                $html .= \html_writer::tag('label', $label, [
                    'for' => $cbid,
                    'class' => 'form-check-label',
                ]);
                $html .= \html_writer::end_div();
            }
        }

        $html .= \html_writer::end_div();

        // Select all / deselect all toggles. Delivered as an AMD module rather
        // than an inline <script> so the page stays compatible with a Content
        // Security Policy that forbids inline script.
        global $PAGE;
        $PAGE->requires->js_call_amd('logstore_xapi/route_groups', 'init');

        return format_admin_setting($this, $this->visiblename, $html, $this->description, true, '', '', $query);
    }

    /**
     * Group events by their Moodle component.
     *
     * @return array Associative array keyed by component, each containing 'name' and 'events'.
     */
    private function group_events() {
        $groups = [];
        foreach ($this->choices as $eventclass => $label) {
            $component = self::get_component($eventclass);
            if (!isset($groups[$component])) {
                $groups[$component] = [
                    'name' => self::get_component_display_name($component),
                    'events' => [],
                ];
            }
            $groups[$component]['events'][$eventclass] = $label;
        }
        return $groups;
    }

    /**
     * Extract the component name from an event class.
     *
     * @param string $eventclass e.g. '\mod_quiz\event\attempt_submitted'
     * @return string e.g. 'mod_quiz'
     */
    private static function get_component($eventclass) {
        $parts = explode('\\', ltrim($eventclass, '\\'));
        return $parts[0] ?? 'unknown';
    }

    /**
     * Get a human-readable name for a Moodle component.
     *
     * @param string $component e.g. 'mod_quiz', 'core', 'tool_certificate'
     * @return string e.g. 'Quiz', 'Core', 'Certificate'
     */
    private static function get_component_display_name($component) {
        if ($component === 'core') {
            return 'Core';
        }
        if ($component === 'core_h5p') {
            return 'H5P';
        }

        $sm = get_string_manager();
        if ($sm->string_exists('pluginname', $component)) {
            return get_string('pluginname', $component);
        }

        // Fallback: clean up the frankenstyle name.
        return ucfirst(str_replace('_', ' ', $component));
    }

    /**
     * Get a friendly label from an event class name.
     *
     * @param string $eventclass e.g. '\mod_quiz\event\attempt_submitted'
     * @return string e.g. 'Attempt submitted'
     */
    private static function get_friendly_label($eventclass) {
        $parts = explode('\\', ltrim($eventclass, '\\'));
        $eventname = end($parts);
        return ucfirst(str_replace('_', ' ', $eventname));
    }

    /**
     * Sanitize a string for use as an HTML ID attribute.
     *
     * @param string $str Raw string.
     * @return string Sanitized string containing only alphanumerics and underscores.
     */
    private static function sanitize_id($str) {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $str);
    }
}
