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

namespace logstore_xapi\privacy;

use advanced_testcase;
use context_system;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Unit tests for the logstore_xapi privacy provider.
 *
 * The provider previously declared personal data and then exported and deleted
 * none of it, so these tests assert that data actually leaves the tables.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\privacy\provider
 */
final class provider_test extends advanced_testcase {
    /** @var string[] Both event tables, which must be treated identically. */
    private const TABLES = ['logstore_xapi_log', 'logstore_xapi_failed_log'];

    /**
     * Insert an event row into both event tables.
     *
     * @param int $userid The acting user.
     * @param int $contextid The context the event happened in.
     * @param int $relateduserid Optional related user.
     * @return void
     */
    private function create_event(int $userid, int $contextid, int $relateduserid = 0): void {
        global $DB;

        foreach (self::TABLES as $table) {
            $DB->insert_record($table, (object) [
                'eventname' => '\core\event\course_viewed',
                'component' => 'core',
                'action' => 'viewed',
                'target' => 'course',
                'crud' => 'r',
                'edulevel' => 0,
                'contextid' => $contextid,
                'contextlevel' => CONTEXT_SYSTEM,
                'contextinstanceid' => 0,
                'userid' => $userid,
                'courseid' => 0,
                'relateduserid' => $relateduserid,
                'anonymous' => 0,
                'other' => 'N;',
                'timecreated' => time(),
                'origin' => 'web',
                'ip' => '198.51.100.1',
                'realuserid' => 0,
            ]);
        }
    }

    /**
     * Count rows for a user in a table.
     *
     * @param string $table The table to count in.
     * @param int $userid The user to count for.
     * @return int
     */
    private function count_for_user(string $table, int $userid): int {
        global $DB;
        return $DB->count_records($table, ['userid' => $userid]);
    }

    /**
     * The provider reports the contexts a user has data in, for both tables.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $this->create_event($user->id, $context->id);

        $contextlist = new \core_privacy\local\request\contextlist();
        provider::add_contexts_for_userid($contextlist, $user->id);

        $this->assertContains((int) $context->id, array_map('intval', $contextlist->get_contextids()));
    }

    /**
     * Users with data in a context are reported, including related users.
     *
     * @return void
     */
    public function test_add_userids_for_context(): void {
        $this->resetAfterTest();
        $actor = $this->getDataGenerator()->create_user();
        $related = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $this->create_event($actor->id, $context->id, $related->id);

        $userlist = new userlist($context, 'logstore_xapi');
        provider::add_userids_for_context($userlist);

        $found = $userlist->get_userids();
        $this->assertContains((int) $actor->id, array_map('intval', $found));
        $this->assertContains((int) $related->id, array_map('intval', $found));
    }

    /**
     * Exported data is written for the user.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $this->create_event($user->id, $context->id);

        $contextlist = new approved_contextlist($user, 'logstore_xapi', [$context->id]);
        provider::export_user_data($contextlist);

        $this->assertTrue(writer::with_context($context)->has_any_data());
    }

    /**
     * Deleting for a user removes their rows from both tables and leaves other
     * users untouched. This is the regression the empty provider allowed.
     *
     * @return void
     */
    public function test_delete_data_for_user_removes_only_that_user(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $this->create_event($user->id, $context->id);
        $this->create_event($other->id, $context->id);

        foreach (self::TABLES as $table) {
            $this->assertSame(1, $this->count_for_user($table, $user->id));
        }

        $contextlist = new approved_contextlist($user, 'logstore_xapi', [$context->id]);
        provider::delete_data_for_user($contextlist);

        foreach (self::TABLES as $table) {
            $this->assertSame(0, $this->count_for_user($table, $user->id), "$table still holds data for the user");
            $this->assertSame(1, $this->count_for_user($table, $other->id), "$table lost another user's data");
        }
    }

    /**
     * Deleting a whole context clears both tables.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $this->resetAfterTest();
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $this->create_event($user->id, $context->id);

        provider::delete_data_for_all_users_in_context($context);

        foreach (self::TABLES as $table) {
            $this->assertSame(0, $DB->count_records($table, ['contextid' => $context->id]));
        }
    }

    /**
     * Deleting an approved userlist removes those users from both tables only.
     *
     * @return void
     */
    public function test_delete_data_for_userlist(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $this->create_event($user->id, $context->id);
        $this->create_event($other->id, $context->id);

        $userlist = new approved_userlist($context, 'logstore_xapi', [$user->id]);
        provider::delete_data_for_userlist($userlist);

        foreach (self::TABLES as $table) {
            $this->assertSame(0, $this->count_for_user($table, $user->id));
            $this->assertSame(1, $this->count_for_user($table, $other->id));
        }
    }

    /**
     * The provider is dispatched by tool_log's aggregating provider, rather
     * than merely being callable on its own. Declaring the wrong interfaces
     * would leave it silently unreachable by the privacy subsystem.
     *
     * @return void
     */
    public function test_provider_implements_tool_log_interfaces(): void {
        $this->assertInstanceOf(\tool_log\local\privacy\logstore_provider::class, new provider());
        $this->assertInstanceOf(\tool_log\local\privacy\logstore_userlist_provider::class, new provider());
    }

    /**
     * Every field declared in the metadata must exist as a real column, which
     * the previous provider got wrong (it declared a 'moodleuserid' field).
     *
     * @return void
     */
    public function test_metadata_fields_match_real_columns(): void {
        global $DB;

        $collection = provider::get_metadata(new \core_privacy\local\metadata\collection('logstore_xapi'));

        foreach ($collection->get_collection() as $item) {
            $table = $item->get_name();
            $columns = $DB->get_columns($table);

            foreach (array_keys($item->get_privacy_fields()) as $field) {
                $this->assertArrayHasKey($field, $columns, "Declared field $field does not exist in $table");
            }
        }
    }
}
