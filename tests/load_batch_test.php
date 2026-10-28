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
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

use src\loader\utils\retry_later;
use function src\loader\utils\load_batch;
use function src\loader\utils\load_in_batches;

/**
 * Unit tests for sending a batch: repeated statement ids, conflicts (409), batches too large
 * (413), and an LRS that can't take statements now.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\loader\utils\load_batch
 * @covers    ::\src\loader\utils\load_batches
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

    /**
     * An LRS that answers each request in turn: a status to throw, or nothing for success.
     *
     * @param array $answers For each request, null for success, an exception, or a function of
     *     the statement ids that returns one of those.
     * @return callable
     */
    private function scripted_lrs(array $answers): callable {
        return function (array $config, array $statements) use (&$answers) {
            $ids = array_column($statements, 'id');
            $this->requests[] = $ids;
            $answer = array_shift($answers);
            $answer = is_callable($answer) ? $answer($ids) : $answer;
            if ($answer instanceof \Throwable) {
                throw $answer;
            }
        };
    }

    /**
     * When the LRS can't take statements now, the batch is left for a later run, and the batches
     * after it aren't sent.
     *
     * @return void
     */
    public function test_retry_leaves_the_batch_and_stops_the_run(): void {
        $events = [$this->event(1, 'a'), $this->event(2, 'b'), $this->event(3, 'c'), $this->event(4, 'd')];
        $config = ['lrs_max_batch_size' => 2] + $this->get_config();

        $loaded = load_in_batches($config, $events, $this->scripted_lrs([new retry_later('Service Unavailable', 503)]));

        $this->assertSame([['a', 'b']], $this->requests);
        $this->assertSame(array_fill(1, 4, XAPI_LOADED_RETRY), $this->loaded($loaded));
        $this->assertEquals(503, $loaded[0]['event']->errortype);
    }

    /**
     * A retry part-way through splitting a batch stops the split too.
     *
     * @return void
     */
    public function test_retry_stops_a_split(): void {
        $events = [$this->event(1, 'a'), $this->event(2, 'b'), $this->event(3, 'c'), $this->event(4, 'd')];
        $lrs = $this->scripted_lrs([new \Exception('Conflict', 409), new retry_later('Bad Gateway', 502)]);

        $loaded = load_batch($this->get_config(), $events, $lrs);

        $this->assertSame([['a', 'b', 'c', 'd'], ['a', 'b']], $this->requests);
        $this->assertSame(array_fill(1, 4, XAPI_LOADED_RETRY), $this->loaded($loaded));
    }

    /**
     * A batch the LRS finds too large (413) is split, whatever "Resend failed batches" is set to,
     * and only an event that is too large on its own fails.
     *
     * @return void
     */
    public function test_too_large_batch_is_split(): void {
        $events = [$this->event(1, 'a'), $this->event(2, 'b'), $this->event(3, 'c')];
        $toolarge = function (array $ids) {
            return (count($ids) > 1 || $ids === ['c']) ? new \Exception('Payload Too Large', 413) : null;
        };
        $lrs = $this->scripted_lrs(array_fill(0, 10, $toolarge));

        $loaded = load_batch($this->get_config('0'), $events, $lrs);

        $this->assertSame([1 => true, 2 => true, 3 => false], $this->loaded($loaded));
        $this->assertEquals(413, $loaded[2]['event']->errortype);
    }
}
