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

namespace logstore_xapi\check;

use advanced_testcase;
use core\check\result;

/**
 * Unit tests for the LRS certificate verification security check.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\check\ssl_verification
 */
final class ssl_verification_test extends advanced_testcase {
    /**
     * Enable the xAPI logstore, since the check reports NA while it is off.
     *
     * @return void
     */
    private function enable_store(): void {
        set_config('enabled_stores', 'logstore_standard,logstore_xapi', 'tool_log');
    }

    /**
     * An unconfigured site is reported as OK rather than at risk.
     *
     * @return void
     */
    public function test_unset_setting_reports_ok(): void {
        $this->resetAfterTest();
        $this->enable_store();

        $result = (new ssl_verification())->get_result();

        $this->assertSame(result::OK, $result->get_status());
    }

    /**
     * Verification enabled reports OK.
     *
     * @return void
     */
    public function test_enabled_reports_ok(): void {
        $this->resetAfterTest();
        $this->enable_store();
        set_config('sslverification', 1, 'logstore_xapi');

        $result = (new ssl_verification())->get_result();

        $this->assertSame(result::OK, $result->get_status());
    }

    /**
     * Verification disabled surfaces a warning in the security report.
     *
     * @return void
     */
    public function test_disabled_reports_warning(): void {
        $this->resetAfterTest();
        $this->enable_store();
        set_config('sslverification', 0, 'logstore_xapi');

        $result = (new ssl_verification())->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
    }

    /**
     * A disabled store sends nothing, so the check must not raise a warning
     * about a connection that never happens.
     *
     * @return void
     */
    public function test_disabled_store_reports_na(): void {
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('sslverification', 0, 'logstore_xapi');

        $result = (new ssl_verification())->get_result();

        $this->assertSame(result::NA, $result->get_status());
    }

    /**
     * Re-enabling the store surfaces the pre-existing insecure setting, rather
     * than leaving it hidden because it was configured while disabled.
     *
     * @return void
     */
    public function test_warning_returns_when_store_re_enabled(): void {
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        set_config('sslverification', 0, 'logstore_xapi');

        $this->assertSame(result::NA, (new ssl_verification())->get_result()->get_status());

        $this->enable_store();

        $this->assertSame(result::WARNING, (new ssl_verification())->get_result()->get_status());
    }

    /**
     * The check is registered with core so that it actually appears in the
     * security report, not just when instantiated directly.
     *
     * @return void
     */
    public function test_check_is_registered_with_core(): void {
        $this->resetAfterTest();

        $checks = \core\check\manager::get_security_checks();

        $found = false;
        foreach ($checks as $check) {
            if ($check instanceof ssl_verification) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'ssl_verification check is not registered via logstore_xapi_security_checks()');
    }
}
