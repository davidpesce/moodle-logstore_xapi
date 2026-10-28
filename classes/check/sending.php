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
 * Status check for sending statements to the LRS.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\check;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

use core\check\check;
use core\check\result;

/**
 * Reports whether events are being sent to the LRS, or waiting in the queue.
 *
 * When the LRS can't take statements (it's down, or refuses the credentials), events stay in the
 * queue instead of going to the failed log, so they don't show in the error report. This check
 * makes that visible: it warns when a live event has been waiting more than an hour, and shows
 * the last error from the LRS.
 */
class sending extends check {
    /** @var int How long, in seconds, a live event may wait in the queue before the check warns. */
    const MAX_WAIT = HOURSECS;

    /**
     * Get the short check name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_sending_name', 'logstore_xapi');
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
        global $DB;

        $enabledstores = get_config('tool_log', 'enabled_stores');
        if (empty($enabledstores) || !in_array('logstore_xapi', explode(',', $enabledstores))) {
            return new result(result::NA, get_string('check_sslverification_na', 'logstore_xapi'), '');
        }

        // Live events only: historic and resent events keep the time of the original event.
        $oldest = (int) $DB->get_field('logstore_xapi_log', 'MIN(timecreated)', ['type' => XAPI_IMPORT_TYPE_LIVE]);
        $error = \cache::make('logstore_xapi', 'senderror')->get('last');

        $details = get_string('check_sending_details', 'logstore_xapi');
        if ($error !== false) {
            $details .= ' ' . get_string('check_sending_lasterror', 'logstore_xapi', (object) [
                'time' => userdate($error->time),
                'error' => s(logstore_xapi_get_send_error_string($error)),
            ]);
        }

        if ($oldest && time() - $oldest > self::MAX_WAIT) {
            return new result(result::WARNING, get_string('check_sending_warning', 'logstore_xapi', (object) [
                'count' => $DB->count_records('logstore_xapi_log', ['type' => XAPI_IMPORT_TYPE_LIVE]),
                'age' => format_time(time() - $oldest),
            ]), $details);
        }
        return new result(result::OK, get_string('check_sending_ok', 'logstore_xapi'), $details);
    }
}
