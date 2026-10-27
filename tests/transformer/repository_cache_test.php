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
 * Unit tests for the repository's cache of single records.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \src\transformer\repos\Repository
 * @covers    \src\transformer\repos\MoodleRepository
 */
final class repository_cache_test extends advanced_testcase {
    /**
     * A test repository holding two users, that counts the reads that reach its store.
     *
     * @return \src\transformer\repos\TestRepository
     */
    private function get_repo(): \src\transformer\repos\TestRepository {
        return new class ((object) [
            'user' => [
                (object) ['id' => 1, 'firstname' => 'Grace', 'email' => 'g@example.org'],
                (object) ['id' => 2, 'firstname' => 'Sam', 'email' => 's@example.org'],
            ],
        ]) extends \src\transformer\repos\TestRepository {
            /** @var int The number of reads that reached the store. */
            public $reads = 0;

            /**
             * Count the read, then read from the test data.
             *
             * @param string $type The name of the table to retrieve from.
             * @param array $query Any additional conditions to add to the query.
             * @param string $sort Sort string for how to order the data.
             * @return array
             */
            public function read_records(string $type, array $query, string $sort = '') {
                $this->reads++;
                return parent::read_records($type, $query, $sort);
            }
        };
    }

    /**
     * A record is read from the store once, however its id is given.
     *
     * @return void
     */
    public function test_record_is_read_once(): void {
        $repo = $this->get_repo();

        $this->assertSame('Grace', $repo->read_record_by_id('user', 1)->firstname);
        $repo->read_record_by_id('user', '1');
        $repo->read_record('user', ['id' => 1]);
        $this->assertSame(1, $repo->reads);

        // Other conditions are other entries, even when they match the same record.
        $repo->read_record_by_id('user', 2);
        $repo->read_record('user', ['email' => 'g@example.org']);
        $repo->read_record('user', ['email' => 'g@example.org']);
        $this->assertSame(3, $repo->reads);

        // Lists are not cached.
        $repo->read_records('user', ['id' => 1]);
        $this->assertSame(4, $repo->reads);
    }

    /**
     * Each read returns its own copy, so changing one does not change the next.
     *
     * @return void
     */
    public function test_records_are_copies(): void {
        $repo = $this->get_repo();

        $first = $repo->read_record_by_id('user', 1);
        $first->firstname = 'Changed';
        $second = $repo->read_record_by_id('user', 1);

        $this->assertSame('Grace', $second->firstname);
        $this->assertNotSame($first, $second);
    }

    /**
     * A missing record is not cached: it is read, and throws, each time.
     *
     * @return void
     */
    public function test_missing_record_is_not_cached(): void {
        $repo = $this->get_repo();

        foreach ([1, 2] as $attempt) {
            try {
                $repo->read_record_by_id('user', 99);
                $this->fail('A missing record must throw.');
            } catch (\Exception $e) {
                $this->assertSame('user not found.', $e->getMessage());
            }
            $this->assertSame($attempt, $repo->reads);
        }
    }

    /**
     * The Moodle repository queries the database once for a record it has read.
     *
     * @return void
     */
    public function test_moodle_repository_queries_once(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Grace']);
        $repo = new \src\transformer\repos\MoodleRepository($DB);

        $reads = $DB->perf_get_reads();
        $this->assertSame('Grace', $repo->read_record_by_id('user', $user->id)->firstname);
        $this->assertSame('Grace', $repo->read_record_by_id('user', (string) $user->id)->firstname);
        $this->assertSame($reads + 1, $DB->perf_get_reads());
    }
}
