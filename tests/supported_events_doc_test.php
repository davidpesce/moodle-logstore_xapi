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

/**
 * Tests that the supported events page lists the events the plugin maps.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class supported_events_doc_test extends \advanced_testcase {
    /**
     * docs/supported-events.md lists every event in get_event_function_map(), and no other.
     *
     * The map is read from its source: at run time it leaves out the events of plugins that
     * aren't installed.
     *
     * @return void
     */
    public function test_page_lists_the_mapped_events(): void {
        global $CFG;
        $dir = $CFG->dirroot . '/admin/tool/log/store/xapi';
        // An event class name, such as \core\event\course_viewed.
        $event = '\\\\[a-z0-9_]+\\\\event\\\\[a-z0-9_]+';

        preg_match_all("/'($event)'\s*=>/", file_get_contents($dir . '/src/transformer/get_event_function_map.php'), $map);
        preg_match_all("/`($event)`/", file_get_contents($dir . '/docs/supported-events.md'), $page);

        $mapped = array_unique($map[1]);
        $listed = array_unique($page[1]);
        sort($mapped);
        sort($listed);
        $this->assertNotEmpty($mapped);
        $this->assertSame($mapped, $listed);
    }
}
