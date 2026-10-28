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

namespace logstore_xapi;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

/**
 * Unit tests for the \core\http_client settings used to send statements to the LRS.
 *
 * These guard the TLS posture of the LRS connection. A silent regression here
 * would not fail any other test but would put credentials and learner data on
 * the wire unprotected.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\loader\moodle_curl_lrs\get_client_settings
 */
final class client_settings_test extends advanced_testcase {
    /**
     * The client settings for a loader config.
     *
     * @param array $config The loader config.
     * @return array
     */
    private function settings(array $config): array {
        return \src\loader\moodle_curl_lrs\get_client_settings($config);
    }

    /**
     * Verification is on when the setting is unset, on, or "1".
     *
     * @return void
     */
    public function test_verification_on_by_default(): void {
        $this->assertTrue($this->settings([])['verify']);
        $this->assertTrue($this->settings(['lrs_ssl_verification' => true])['verify']);
        $this->assertTrue($this->settings(['lrs_ssl_verification' => '1'])['verify']);
    }

    /**
     * Verification is off when an administrator turns it off, however the value is stored.
     *
     * @return void
     */
    public function test_verification_off_when_disabled(): void {
        $this->assertFalse($this->settings(['lrs_ssl_verification' => false])['verify']);
        $this->assertFalse($this->settings(['lrs_ssl_verification' => '0'])['verify']);
    }

    /**
     * A CA bundle is used to verify the certificate; an empty one is ignored. Turning
     * verification off overrides it.
     *
     * @return void
     */
    public function test_ca_bundle(): void {
        $this->assertSame(
            '/etc/ssl/certs/private-ca.pem',
            $this->settings(['lrs_ssl_cabundle' => '/etc/ssl/certs/private-ca.pem'])['verify']
        );
        $this->assertTrue($this->settings(['lrs_ssl_cabundle' => ''])['verify']);
        $this->assertFalse($this->settings([
            'lrs_ssl_cabundle' => '/etc/ssl/certs/private-ca.pem',
            'lrs_ssl_verification' => '0',
        ])['verify']);
    }

    /**
     * Moodle's blocked hosts and ports aren't applied, error responses aren't exceptions, and
     * the timeouts are set.
     *
     * @return void
     */
    public function test_request_settings(): void {
        $settings = $this->settings([]);

        $this->assertTrue($settings['ignoresecurity']);
        $this->assertFalse($settings['http_errors']);
        $this->assertSame(10, $settings['connect_timeout']);
        $this->assertSame(60, $settings['timeout']);
        $this->assertArrayNotHasKey('mock', $settings);
    }

    /**
     * The xAPI headers and Basic auth are set.
     *
     * @return void
     */
    public function test_headers(): void {
        $headers = $this->settings(['lrs_username' => 'user', 'lrs_password' => 'pass'])['headers'];

        $this->assertSame('Basic ' . base64_encode('user:pass'), $headers['Authorization']);
        $this->assertSame('1.0.3', $headers['X-Experience-API-Version']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }
}
