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

use logstore_xapi\log\moveback;

/**
 * Unit tests for moving events back to the queue with an empty id list.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\log\moveback
 */
final class moveback_empty_ids_test extends \advanced_testcase {
    /**
     * An empty id list moves nothing, rather than selecting the whole source table.
     *
     * @return void
     */
    public function test_empty_ids_move_nothing(): void {
        global $DB;
        $this->resetAfterTest();

        $DB->insert_record('logstore_xapi_failed_log', (object) [
            'eventname' => '\\core\\event\\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => 1,
            'contextlevel' => 50,
            'contextinstanceid' => 2,
            'userid' => 2,
            'courseid' => 2,
            'anonymous' => 0,
            'timecreated' => 1433946701,
            'type' => 2,
        ]);

        $mover = new moveback([]);

        $this->assertFalse($mover->execute());
        $this->assertEquals(1, $DB->count_records('logstore_xapi_failed_log'));
        $this->assertEquals(0, $DB->count_records('logstore_xapi_log'));
    }
}
