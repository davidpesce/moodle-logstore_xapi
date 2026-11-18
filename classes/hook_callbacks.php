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
 * Hook callbacks for logstore_xapi.
 *
 * @package   logstore_xapi
 * @copyright 2026 Lachlan Keown <lachlankeown@gmail.com>, NZ ADL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi;

use core\hook\output\before_http_headers;
use logstore_xapi\client\verb_policy;

/**
 * Hook callback handlers.
 */
class hook_callbacks {
    /**
     * Conditionally include the client-side xAPI sender.
     *
     * H5P content renders inside courses and activity modules, so the
     * listener is only attached there — not on dashboards, admin or
     * profile pages where it could never fire.
     *
     * @param before_http_headers $hook Hook instance.
     * @return void
     */
    public static function before_http_headers(before_http_headers $hook): void {
        global $PAGE;

        if (!get_config('logstore_xapi', 'captureclientsideevents')) {
            return;
        }
        if (isguestuser()) {
            return;
        }

        $context = $PAGE->context ?? null;
        if (!$context || !in_array($context->contextlevel, [CONTEXT_COURSE, CONTEXT_MODULE], true)) {
            return;
        }

        $PAGE->requires->js('/admin/tool/log/store/xapi/sender.js');
        $filterconfig = [
            'enabledVerbs' => verb_policy::get_enabled_verb_ids(),
            'knownVerbs' => array_values(verb_policy::get_verb_map()),
            'allowUnknown' => verb_policy::allow_unknown(),
        ];
        $PAGE->requires->js_init_code(
            'window.logstoreXapiClientVerbs = ' . json_encode($filterconfig) . ';'
        );
    }
}
