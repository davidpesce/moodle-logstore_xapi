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
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/db/upgrade.php');

/**
 * Tests for plugin upgrade behavior.
 *
 * @package   logstore_xapi
 * @copyright 2026 Flavio <flavio@ferreira-michell.org>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::logstore_xapi_upgrade_routes
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Add the gradebook event without changing other saved routes.
     *
     * @covers ::logstore_xapi_upgrade_routes
     * @return void
     */
    public function test_upgrade_preserves_existing_routes_and_adds_user_graded(): void {
        $this->resetAfterTest();
        $existing = ['\\core\\event\\course_viewed', '\\core\\event\\user_created'];
        set_config('routes', implode(',', $existing), 'logstore_xapi');

        logstore_xapi_upgrade_routes();

        $this->assertSame(
            [...$existing, '\\core\\event\\user_graded'],
            explode(',', get_config('logstore_xapi', 'routes'))
        );
    }

    /**
     * An explicitly empty route list becomes the requested gradebook-only route.
     *
     * @covers ::logstore_xapi_upgrade_routes
     * @return void
     */
    public function test_upgrade_adds_user_graded_to_empty_route_list(): void {
        $this->resetAfterTest();
        set_config('routes', '', 'logstore_xapi');

        logstore_xapi_upgrade_routes();

        $this->assertSame('\\core\\event\\user_graded', get_config('logstore_xapi', 'routes'));
    }

    /**
     * An unset route setting is initialized with the new-install defaults.
     *
     * @covers ::logstore_xapi_upgrade_routes
     * @return void
     */
    public function test_upgrade_initializes_unset_routes_with_defaults(): void {
        $this->resetAfterTest();
        unset_config('routes', 'logstore_xapi');

        logstore_xapi_upgrade_routes();

        $eventnames = array_keys(\src\transformer\get_event_function_map());
        $expected = array_keys(logstore_xapi_get_default_routes($eventnames));
        $this->assertSame($expected, explode(',', get_config('logstore_xapi', 'routes')));
        $this->assertContains('\\core\\event\\user_graded', $expected);
    }
}
