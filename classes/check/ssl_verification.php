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
 * Security check for LRS TLS certificate verification.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\check;

use core\check\check;
use core\check\result;

/**
 * Reports whether the plugin verifies the LRS TLS certificate.
 *
 * Disabling verification is a supported escape hatch, but it must stay visible
 * afterwards rather than being silently forgotten in the plugin settings.
 */
class ssl_verification extends check {
    /**
     * Get the short check name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_sslverification_name', 'logstore_xapi');
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
        // No statements leave the site while the store is disabled, so there is
        // no connection to protect and nothing worth reporting. The check flips
        // to a warning as soon as the store is enabled.
        $enabledstores = get_config('tool_log', 'enabled_stores');
        $enabled = !empty($enabledstores) && in_array('logstore_xapi', explode(',', $enabledstores));

        if (!$enabled) {
            return new result(
                result::NA,
                get_string('check_sslverification_na', 'logstore_xapi'),
                get_string('check_sslverification_details', 'logstore_xapi')
            );
        }

        $verify = get_config('logstore_xapi', 'sslverification');

        // An unset value means the site predates this setting; treat it the
        // same as enabled so that upgrading sites are not reported as at risk
        // before the admin has had a chance to see the new setting.
        if ($verify === false || (bool)$verify) {
            return new result(
                result::OK,
                get_string('check_sslverification_ok', 'logstore_xapi'),
                get_string('check_sslverification_details', 'logstore_xapi')
            );
        }

        return new result(
            result::WARNING,
            get_string('check_sslverification_warning', 'logstore_xapi'),
            get_string('check_sslverification_details', 'logstore_xapi')
        );
    }
}
