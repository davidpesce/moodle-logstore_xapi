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
 * Unit tests for the LRS endpoint encryption security check.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\check\endpoint_https
 */
final class endpoint_https_test extends advanced_testcase {
    /**
     * The check's status for an endpoint, with the given log stores enabled.
     *
     * @param string $endpoint The LRS endpoint.
     * @param string $stores The enabled log stores.
     * @return string
     */
    private function get_status(string $endpoint, string $stores = 'logstore_standard,logstore_xapi'): string {
        $this->resetAfterTest();
        set_config('enabled_stores', $stores, 'tool_log');
        set_config('endpoint', $endpoint, 'logstore_xapi');
        return (new endpoint_https())->get_result()->get_status();
    }

    /**
     * An https:// endpoint is OK, and a plain http:// one, in any case, is a warning.
     *
     * @return void
     */
    public function test_endpoint_scheme(): void {
        $this->assertSame(result::OK, $this->get_status('https://lrs.example.org/xapi/'));
        $this->assertSame(result::WARNING, $this->get_status('http://lrs.example.org/xapi/'));
        $this->assertSame(result::WARNING, $this->get_status('HTTP://lrs.example.org/xapi/'));
    }

    /**
     * With OAuth2, a plain http:// token endpoint is a warning too.
     *
     * @return void
     */
    public function test_token_endpoint_scheme(): void {
        $this->resetAfterTest();
        set_config('oauth2tokenendpoint', 'http://idp.example.org/token', 'logstore_xapi');

        set_config('authmethod', 'basic', 'logstore_xapi');
        $this->assertSame(result::OK, $this->get_status('https://lrs.example.org/xapi/'));

        set_config('authmethod', 'oauth2', 'logstore_xapi');
        $this->assertSame(result::WARNING, $this->get_status('https://lrs.example.org/xapi/'));

        set_config('oauth2tokenendpoint', 'https://idp.example.org/token', 'logstore_xapi');
        $this->assertSame(result::OK, $this->get_status('https://lrs.example.org/xapi/'));
    }

    /**
     * Nothing is sent while the store is disabled, so the check doesn't apply.
     *
     * @return void
     */
    public function test_disabled_store_is_na(): void {
        $this->assertSame(result::NA, $this->get_status('http://lrs.example.org/xapi/', 'logstore_standard'));
    }

    /**
     * The check is registered with core, so it appears in the security report.
     *
     * @return void
     */
    public function test_check_is_registered_with_core(): void {
        $this->resetAfterTest();

        $registered = array_filter(\core\check\manager::get_security_checks(), function ($check) {
            return $check instanceof endpoint_https;
        });

        $this->assertCount(1, $registered);
    }
}
