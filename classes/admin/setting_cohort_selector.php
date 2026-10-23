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

namespace logstore_xapi\admin;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Admin setting: AJAX-powered cohort selector.
 *
 * Stores selected cohort IDs as a comma-separated string (e.g. "1,3,7").
 * Replaces the former admin_setting_configmulticheckbox which required loading
 * all cohorts upfront — unusable on sites with 100 000+ cohorts.
 *
 * @package   logstore_xapi
 * @copyright 2025 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_cohort_selector extends \admin_setting {
    /**
     * Return the current setting value.
     *
     * @return string|null Comma-separated cohort IDs, or null when never saved.
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Validate and persist a new value for this setting.
     *
     * Accepts a comma-separated string of cohort IDs.  Only IDs that exist
     * and are currently visible are retained; unknown / invisible IDs are
     * silently dropped.
     *
     * @param  string $data Comma-separated cohort IDs submitted by the form.
     * @return string Empty string on success; localised error message on failure.
     */
    public function write_setting($data) {
        global $DB;

        $data = trim((string)$data);

        if ($data === '') {
            return $this->config_write($this->name, '') ? '' : get_string('errorsetting', 'admin');
        }

        $rawids = explode(',', $data);
        $intids = [];
        foreach ($rawids as $rawid) {
            $id = (int)trim($rawid);
            if ($id > 0) {
                $intids[] = $id;
            }
        }

        if (empty($intids)) {
            return $this->config_write($this->name, '') ? '' : get_string('errorsetting', 'admin');
        }

        // Keep only IDs that exist and are visible.
        [$insql, $inparams] = $DB->get_in_or_equal($intids, SQL_PARAMS_NAMED);
        $inparams['visible'] = 1;
        $validrecords = $DB->get_records_select('cohort', "id $insql AND visible = :visible", $inparams, '', 'id');
        $validids = array_keys($validrecords);

        $value = implode(',', $validids);
        return $this->config_write($this->name, $value) ? '' : get_string('errorsetting', 'admin');
    }

    /**
     * Return the HTML for this admin setting.
     *
     * Renders a hidden input (which stores the comma-separated ID list), a row
     * of removable tags for currently-selected cohorts, a search text input,
     * and an initially-hidden results dropdown.  The AMD module
     * logstore_xapi/cohort_selector wires up the interactive behaviour.
     *
     * @param  string $data  Current setting value (comma-separated IDs).
     * @param  string $query Admin search query (used for highlighting).
     * @return string HTML fragment.
     */
    public function output_html($data, $query = '') {
        global $CFG, $DB, $PAGE;

        $inputid  = $this->get_id();
        $fullname = $this->get_full_name();
        $ajaxurl  = $CFG->wwwroot . '/admin/tool/log/store/xapi/ajax/search_cohorts.php';

        // Load names for currently-selected cohort IDs.
        $selectedcohorts = [];
        $data = trim((string)$data);
        if ($data !== '') {
            $ids = array_filter(array_map('intval', explode(',', $data)));
            if (!empty($ids)) {
                $selectedcohorts = $DB->get_records_list('cohort', 'id', $ids, 'name ASC', 'id,name');
            }
        }

        // Build the tag HTML for each pre-selected cohort.
        $taghtml = '';
        foreach ($selectedcohorts as $cohort) {
            $cohortname = s($cohort->name);
            $taghtml .= '<span class="cohort-tag badge badge-info bg-info mr-1 me-1" data-id="' . (int)$cohort->id . '">'
                . $cohortname
                . ' <a href="#" class="cohort-remove ml-1 ms-1" data-id="' . (int)$cohort->id . '"'
                . ' aria-label="' . get_string('remove') . '">&times;</a></span>';
        }

        // Current value for the hidden input.
        $hiddenvalue = s($data);

        $element = '<div class="admin-setting-cohort-selector">'
            . '<div id="' . $inputid . '_selected" class="cohort-selected mb-2">' . $taghtml . '</div>'
            . '<input type="text" id="' . $inputid . '_search" class="form-control cohort-search-input mb-1"'
            . ' placeholder="' . get_string('search') . '..." autocomplete="off">'
            . '<div id="' . $inputid . '_results" class="cohort-results list-group" style="display:none;"></div>'
            . '<input type="hidden" id="' . $inputid . '" name="' . $fullname . '" value="' . $hiddenvalue . '">'
            . '</div>';

        $PAGE->requires->js_call_amd('logstore_xapi/cohort_selector', 'init', [$inputid, $ajaxurl]);

        return format_admin_setting($this, $this->visiblename, $element, $this->description, false, '', null, $query);
    }
}
