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
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for the failure notification task.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\task\sendfailednotifications_task
 */
final class sendfailednotifications_task_test extends advanced_testcase {
    /**
     * Add a row to the failed log.
     *
     * @return int The row id.
     */
    private function add_failed_event(): int {
        global $DB;
        return (int) $DB->insert_record('logstore_xapi_failed_log', (object) [
            'eventname' => '\core\event\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 0,
            'contextid' => \context_system::instance()->id,
            'contextlevel' => CONTEXT_SYSTEM,
            'contextinstanceid' => 0,
            'userid' => 2,
            'courseid' => 0,
            'anonymous' => 0,
            'timecreated' => time(),
            'errortype' => XAPI_REPORT_ERRORTYPE_NETWORK,
        ]);
    }

    /**
     * Each recipient gets one email, and the run is logged once without any email address.
     *
     * @return void
     */
    public function test_notifies_each_recipient_and_logs_the_run_once(): void {
        global $DB;
        $this->resetAfterTest();

        set_config('enablesendingnotifications', 1, 'logstore_xapi');
        set_config('errornotificationtrigger', 1, 'logstore_xapi');
        set_config('send_additional_email_addresses', 'one@example.com,two@example.com', 'logstore_xapi');
        $lastid = $this->add_failed_event();

        $sink = $this->redirectEmails();
        $this->expectOutputRegex('/./');
        (new task\sendfailednotifications_task())->execute();

        $this->assertSame(
            ['one@example.com', 'two@example.com'],
            array_map(fn($email) => $email->to, $sink->get_messages())
        );
        $logs = array_values($DB->get_records('logstore_xapi_notif_sent_log'));
        $this->assertCount(1, $logs);
        $this->assertEquals($lastid, $logs[0]->failedlogid);
        $this->assertArrayNotHasKey('email', $DB->get_columns('logstore_xapi_notif_sent_log'));

        // Nothing new has failed, so the next run sends nothing.
        $sink->clear();
        (new task\sendfailednotifications_task())->execute();
        $this->assertSame(0, $sink->count());
    }

    /**
     * Emailing an extra address doesn't change core's cached support user.
     *
     * @return void
     */
    public function test_support_user_is_not_changed(): void {
        $this->resetAfterTest();

        // With no support user or support email, core caches the admin as the support user.
        set_config('supportuserid', '');
        set_config('supportemail', '');
        \core_user::reset_internal_users();
        $supportemail = \core_user::get_support_user()->email;

        set_config('enablesendingnotifications', 1, 'logstore_xapi');
        set_config('errornotificationtrigger', 1, 'logstore_xapi');
        set_config('send_additional_email_addresses', 'one@example.com', 'logstore_xapi');
        $this->add_failed_event();

        $this->redirectEmails();
        $this->expectOutputRegex('/./');
        (new task\sendfailednotifications_task())->execute();

        $this->assertSame($supportemail, \core_user::get_support_user()->email);
    }
}
