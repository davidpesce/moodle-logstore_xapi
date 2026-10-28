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
 * Security check for an LRS endpoint over plain HTTP.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\check;

use core\check\check;
use core\check\result;

/**
 * Reports whether the LRS endpoint uses HTTPS.
 *
 * Plain HTTP is allowed, as some sites run an LRS on a private network, but it sends the LRS
 * credentials and learner data unencrypted, so the report warns about it.
 */
class endpoint_https extends check {
    /**
     * Get the short check name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_endpointhttps_name', 'logstore_xapi');
    }

    /**
     * A link to the plugin settings page to action this.
     *
     * @return \action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/settings.php', ['section' => 'logsettingxapi']),
            get_string('settings', 'logstore_xapi')
        );
    }

    /**
     * Return the result of the check.
     *
     * @return result
     */
    public function get_result(): result {
        // No statements leave the site while the store is disabled.
        $enabledstores = get_config('tool_log', 'enabled_stores');
        if (empty($enabledstores) || !in_array('logstore_xapi', explode(',', $enabledstores))) {
            return new result(
                result::NA,
                get_string('check_sslverification_na', 'logstore_xapi'),
                get_string('check_endpointhttps_details', 'logstore_xapi')
            );
        }

        $endpoint = (string) get_config('logstore_xapi', 'endpoint');
        if (stripos($endpoint, 'http://') === 0) {
            return new result(
                result::WARNING,
                get_string('check_endpointhttps_warning', 'logstore_xapi'),
                get_string('check_endpointhttps_details', 'logstore_xapi')
            );
        }

        return new result(
            result::OK,
            get_string('check_endpointhttps_ok', 'logstore_xapi'),
            get_string('check_endpointhttps_details', 'logstore_xapi')
        );
    }
}
