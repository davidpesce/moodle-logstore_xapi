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

use function src\loader\utils\load_batch;

/**
 * Unit tests for sending a batch: repeated statement ids and conflicts (409).
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\loader\utils\load_batch
 */
final class load_batch_test extends \basic_testcase {
    /** @var array[] The statements of each request the LRS received. */
    private $requests = [];

    /**
     * Loader config.
     *
     * @param string $resendfailedbatches The "Resend failed batches" setting.
     * @return array
     */
    private function get_config(string $resendfailedbatches = '0'): array {
        return [
            'log_error' => function () {
            },
            'log_info' => function () {
            },
            'lrs_max_batch_size' => 30,
            'lrs_resend_failed_batches' => $resendfailedbatches,
        ];
    }

    /**
     * A transformed event with one statement.
     *
     * @param int $id The event id.
     * @param string $statementid The statement's id.
     * @return array
     */
    private function event(int $id, string $statementid): array {
        return [
            'event' => (object) ['id' => $id],
            'statements' => [['id' => $statementid]],
            'transformed' => true,
        ];
    }

    /**
     * An LRS that already has some statements, with different content, and answers 409 to a
     * batch that holds any of them.
     *
     * @param string[] $conflicts The ids of the statements it already has.
     * @return callable
     */
    private function lrs(array $conflicts = []): callable {
        return function (array $config, array $statements) use ($conflicts) {
            $this->requests[] = array_column($statements, 'id');
            if (array_intersect(array_column($statements, 'id'), $conflicts)) {
                throw new \Exception('Statement Conflict', 409);
            }
        };
    }

    /**
     * Whether each event loaded, by event id.
     *
     * @param array $loaded The loaded events.
     * @return bool[]
     */
    private function loaded(array $loaded): array {
        $result = [];
        foreach ($loaded as $event) {
            $result[$event['event']->id] = $event['loaded'];
        }
        ksort($result);
        return $result;
    }

    /**
     * A statement id that appears twice in a batch (identical statements) is sent once.
     *
     * @return void
     */
    public function test_repeated_ids_are_sent_once(): void {
        $events = [$this->event(1, 'a'), $this->event(2, 'a'), $this->event(3, 'b')];

        $loaded = load_batch($this->get_config(), $events, $this->lrs());

        $this->assertSame([['a', 'b']], $this->requests);
        $this->assertSame([1 => true, 2 => true, 3 => true], $this->loaded($loaded));
    }

    /**
     * A 409 splits the batch, even with "Resend failed batches" off, so the other events are
     * stored, and the event the LRS already has counts as sent.
     *
     * @return void
     */
    public function test_conflict_splits_the_batch(): void {
        $events = [$this->event(1, 'a'), $this->event(2, 'b'), $this->event(3, 'c'), $this->event(4, 'd')];

        $loaded = load_batch($this->get_config('0'), $events, $this->lrs(['c']));

        $this->assertSame([1 => true, 2 => true, 3 => true, 4 => true], $this->loaded($loaded));
        $this->assertSame([['a', 'b', 'c', 'd'], ['a', 'b'], ['c', 'd'], ['c'], ['d']], $this->requests);
    }

    /**
     * Other errors still fail the whole batch when "Resend failed batches" is off.
     *
     * @return void
     */
    public function test_other_errors_fail_the_batch(): void {
        $events = [$this->event(1, 'a'), $this->event(2, 'b')];
        $lrs = function () {
            throw new \Exception('Bad request', 400);
        };

        $loaded = load_batch($this->get_config('0'), $events, $lrs);

        $this->assertSame([1 => false, 2 => false], $this->loaded($loaded));
        $this->assertEquals(400, $loaded[0]['event']->errortype);
    }
}
