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
 * Unit tests for the LRS request cURL option builder.
 *
 * These guard the TLS posture of the LRS connection. A silent regression here
 * would not fail any other test but would put credentials and learner data on
 * the wire unprotected.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \src\loader\moodle_curl_lrs\build_curl_options
 */
final class build_curl_options_test extends advanced_testcase {
    /**
     * Build options for a given config, with the request parts held constant.
     *
     * @param array $config Loader config overrides.
     * @return array
     */
    private function build(array $config): array {
        return \src\loader\moodle_curl_lrs\build_curl_options(
            $config,
            'https://lrs.example.com/statements',
            base64_encode('user:pass'),
            '[]'
        );
    }

    /**
     * Verification is on when nothing is configured, so a site that has never
     * seen these settings is still protected.
     *
     * @return void
     */
    public function test_verification_on_by_default(): void {
        $options = $this->build([]);

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    /**
     * An explicitly enabled setting keeps verification on.
     *
     * @return void
     */
    public function test_verification_on_when_enabled(): void {
        $options = $this->build(['lrs_ssl_verification' => true]);

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    /**
     * Opting out drops both peer and host checks together.
     *
     * @return void
     */
    public function test_verification_off_when_disabled(): void {
        $options = $this->build(['lrs_ssl_verification' => false]);

        $this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    /**
     * The setting arrives from get_config() as a string, so '0' must disable
     * and '1' must not.
     *
     * @return void
     */
    public function test_verification_handles_string_config_values(): void {
        $disabled = $this->build(['lrs_ssl_verification' => '0']);
        $this->assertFalse($disabled[CURLOPT_SSL_VERIFYPEER]);

        $enabled = $this->build(['lrs_ssl_verification' => '1']);
        $this->assertTrue($enabled[CURLOPT_SSL_VERIFYPEER]);
    }

    /**
     * A CA bundle is passed through and leaves verification intact.
     *
     * @return void
     */
    public function test_ca_bundle_applied_without_weakening_verification(): void {
        $options = $this->build(['lrs_ssl_cabundle' => '/etc/ssl/certs/private-ca.pem']);

        $this->assertSame('/etc/ssl/certs/private-ca.pem', $options[CURLOPT_CAINFO]);
        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
    }

    /**
     * An empty CA bundle setting must not set CURLOPT_CAINFO at all, since an
     * empty path would break the default trust store.
     *
     * @return void
     */
    public function test_empty_ca_bundle_is_not_applied(): void {
        $options = $this->build(['lrs_ssl_cabundle' => '']);

        $this->assertArrayNotHasKey(CURLOPT_CAINFO, $options);
    }

    /**
     * The request itself is still assembled correctly.
     *
     * @return void
     */
    public function test_request_parts_are_set(): void {
        $options = $this->build([]);

        $this->assertSame('https://lrs.example.com/statements', $options[CURLOPT_URL]);
        $this->assertSame('[]', $options[CURLOPT_POSTFIELDS]);
        $this->assertTrue($options[CURLOPT_RETURNTRANSFER]);
        $this->assertContains('Authorization: Basic ' . base64_encode('user:pass'), $options[CURLOPT_HTTPHEADER]);
        $this->assertContains('X-Experience-API-Version: 1.0.3', $options[CURLOPT_HTTPHEADER]);
    }
}
