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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Unit tests for the status check of sending statements to the LRS.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\check\sending
 */
final class sending_test extends advanced_testcase {
    /**
     * Enable the store, and clear the last error.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enabled_stores', 'logstore_standard,logstore_xapi', 'tool_log');
        \cache::make('logstore_xapi', 'senderror')->purge();
    }

    /**
     * Queue an event.
     *
     * @param int $age How long ago the event happened, in seconds.
     * @param int $type The import type.
     * @return void
     */
    private function queue(int $age, int $type = XAPI_IMPORT_TYPE_LIVE): void {
        global $DB;
        $DB->insert_record('logstore_xapi_log', (object) [
            'eventname' => '\core\event\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => 1,
            'contextlevel' => CONTEXT_SYSTEM,
            'contextinstanceid' => 0,
            'userid' => 2,
            'courseid' => SITEID,
            'anonymous' => 0,
            'timecreated' => time() - $age,
            'type' => $type,
        ]);
    }

    /**
     * Events sent on time are OK, even with some waiting, and old historic events don't count.
     *
     * @return void
     */
    public function test_events_sent_on_time(): void {
        $this->assertSame(result::OK, (new sending())->get_result()->get_status());

        $this->queue(MINSECS);
        $this->queue(YEARSECS, XAPI_IMPORT_TYPE_HISTORIC);
        $this->assertSame(result::OK, (new sending())->get_result()->get_status());
    }

    /**
     * A live event waiting more than an hour is a warning, with the last error from the LRS.
     *
     * @return void
     */
    public function test_events_waiting(): void {
        $this->queue(MINSECS);
        $this->queue(2 * HOURSECS);
        \cache::make('logstore_xapi', 'senderror')->set('last', (object) [
            'time' => time(),
            'code' => 503,
            'message' => '<h1>Service Unavailable</h1>',
        ]);

        $result = (new sending())->get_result();

        $this->assertSame(result::WARNING, $result->get_status());
        $summary = $result->get_summary();
        $this->assertStringContainsString('2 events are waiting to be sent to the LRS, the oldest for 2 hours', $summary);
        $this->assertStringContainsString('HTTP 503: Service Unavailable', $result->get_details());
    }

    /**
     * Nothing is sent while the store is disabled, so the check doesn't apply.
     *
     * @return void
     */
    public function test_disabled_store_is_na(): void {
        set_config('enabled_stores', 'logstore_standard', 'tool_log');
        $this->queue(2 * HOURSECS);

        $this->assertSame(result::NA, (new sending())->get_result()->get_status());
    }

    /**
     * The check is registered with core, so it appears in the system status report.
     *
     * @return void
     */
    public function test_check_is_registered_with_core(): void {
        $registered = array_filter(\core\check\manager::get_checks('status'), function ($check) {
            return $check instanceof sending;
        });

        $this->assertCount(1, $registered);
    }
}
