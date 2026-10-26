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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

use src\transformer\utils;

/**
 * Unit tests for the homePage of actor accounts.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\get_account_homepage
 * @covers    ::\src\transformer\utils\get_user
 * @covers    ::\src\transformer\utils\get_anonymous_agent
 */
final class account_homepage_test extends \basic_testcase {
    /** @var \stdClass A user. */
    private \stdClass $user;

    /**
     * Set up a user.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->user = (object) ['id' => 7, 'username' => 'learner', 'email' => '', 'firstname' => 'A', 'lastname' => 'B'];
    }

    /**
     * The config for a site whose account homepage differs from its URL.
     *
     * @param string $homepage The account homepage setting.
     * @return array
     */
    private function config(string $homepage): array {
        return [
            'app_url' => 'https://moodle.example.com',
            'account_homepage' => $homepage,
            'send_username' => false,
            'send_name' => false,
            'send_mbox' => false,
        ];
    }

    /**
     * The setting applies to every account: by id, by username and the anonymous agent.
     *
     * @return void
     */
    public function test_setting_applies_to_all_accounts(): void {
        $config = $this->config('https://sso.example.com');

        $this->assertSame('https://sso.example.com', utils\get_user($config, $this->user)['account']['homePage']);
        $this->assertSame(
            'https://sso.example.com',
            utils\get_user(['send_username' => true] + $config, $this->user)['account']['homePage']
        );
        $this->assertSame('https://sso.example.com', utils\get_anonymous_agent($config)['account']['homePage']);
    }

    /**
     * An empty setting, or a saved value that isn't an absolute URL, uses the site URL.
     *
     * @return void
     */
    public function test_invalid_setting_uses_site_url(): void {
        foreach (['', 'www.example.org', 'My Moodle', '/relative/path'] as $homepage) {
            $this->assertSame('https://moodle.example.com', utils\get_account_homepage($this->config($homepage)), $homepage);
        }
        $this->assertSame('https://moodle.example.com', utils\get_account_homepage(['app_url' => 'https://moodle.example.com']));
    }
}
