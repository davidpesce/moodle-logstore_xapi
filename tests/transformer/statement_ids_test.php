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

use function src\transformer\utils\set_statement_ids;

/**
 * Unit tests for the statement ids, which stay the same while a statement's content does.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\set_statement_ids
 */
final class statement_ids_test extends \basic_testcase {
    /** @var array Transformer config. */
    private const CONFIG = ['app_url' => 'http://www.example.org'];

    /**
     * A statement about viewing a course.
     *
     * @param string $version The plugin version in the statement's context.
     * @return array
     */
    private function statement(string $version = '5.3.0'): array {
        return [
            'actor' => ['account' => ['homePage' => 'http://www.example.org', 'name' => '2']],
            'verb' => ['id' => 'http://id.tincanapi.com/verb/viewed'],
            'object' => ['id' => 'http://www.example.org/course/view.php?id=2'],
            'context' => ['extensions' => ['https://github.com/xAPI-vle/moodle-logstore_xapi' => $version]],
            'timestamp' => '2015-06-10T14:31:41+00:00',
        ];
    }

    /**
     * The ids of statements for an event.
     *
     * @param array $statements The statements.
     * @param int|null $standardlogid The event's logstore_standard_log id.
     * @return string[]
     */
    private function ids(array $statements, ?int $standardlogid = 100): array {
        $event = (object) ['logstorestandardlogid' => $standardlogid];
        return array_column(set_statement_ids(self::CONFIG, $event, $statements), 'id');
    }

    /**
     * Each statement gets a version 5 UUID as id, first in the statement, and keeps its content.
     *
     * @return void
     */
    public function test_statements_get_an_id(): void {
        $statements = set_statement_ids(self::CONFIG, (object) ['logstorestandardlogid' => 100], [$this->statement()]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $statements[0]['id']
        );
        $this->assertSame('id', array_key_first($statements[0]));
        unset($statements[0]['id']);
        $this->assertSame([$this->statement()], $statements);
    }

    /**
     * Sending the same statement again gives the same id, so the LRS ignores the resend.
     *
     * @return void
     */
    public function test_same_statement_same_id(): void {
        $this->assertSame($this->ids([$this->statement()]), $this->ids([$this->statement()]));
        $this->assertSame($this->ids([$this->statement()], null), $this->ids([$this->statement()], null));
    }

    /**
     * A statement whose content changed (here, after an upgrade) gets a new id, so the LRS stores
     * it instead of rejecting it as a conflict.
     *
     * @return void
     */
    public function test_changed_statement_new_id(): void {
        $this->assertNotSame($this->ids([$this->statement('5.3.0')]), $this->ids([$this->statement('5.3.1')]));
    }

    /**
     * Different events with identical statements get different ids when their standard log ids
     * differ, and the same id without one.
     *
     * @return void
     */
    public function test_standard_log_id_keeps_events_apart(): void {
        $this->assertNotSame($this->ids([$this->statement()], 100), $this->ids([$this->statement()], 101));
        $this->assertNotSame($this->ids([$this->statement()], 100), $this->ids([$this->statement()], null));
        $this->assertSame($this->ids([$this->statement()], null), $this->ids([$this->statement()], 0));
    }

    /**
     * Identical statements of one event get different ids, in a stable order.
     *
     * @return void
     */
    public function test_identical_statements_of_an_event(): void {
        $ids = $this->ids([$this->statement(), $this->statement()]);

        $this->assertCount(2, array_unique($ids));
        $this->assertSame($ids, $this->ids([$this->statement(), $this->statement()]));
        $this->assertSame($this->ids([$this->statement()])[0], $ids[0]);
    }
}
