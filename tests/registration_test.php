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

use src\transformer\utils;

/**
 * Unit tests for the context.registration of statements about an attempt.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\transformer\utils\get_registration
 * @covers    ::\src\transformer\utils\get_attempt_name
 * @covers    ::\src\transformer\utils\get_quiz_attempt_name
 * @covers    ::\src\transformer\utils\get_lesson_attempt_name
 * @covers    ::\src\transformer\utils\get_assign_attempt_name
 * @covers    ::\src\transformer\utils\get_lesson_retake_name
 * @covers    ::\src\transformer\utils\get_uuid_v5
 */
final class registration_test extends \basic_testcase {
    /**
     * A config with a test repository.
     *
     * @param array $data The repository's tables.
     * @return array
     */
    private function config(array $data = []): array {
        return [
            'app_url' => 'http://www.example.org',
            'repo' => new \src\transformer\repos\TestRepository((object) $data),
        ];
    }

    /**
     * The UUID is an RFC 4122 version 5 UUID in the URL namespace.
     *
     * @return void
     */
    public function test_uuid_v5(): void {
        // As Python's uuid.uuid5(uuid.NAMESPACE_URL, 'http://www.example.org/mod/quiz/attempt/1').
        $this->assertSame('89fdb5f6-c98d-54d1-8a37-bb602c90a9ef', utils\get_uuid_v5('http://www.example.org/mod/quiz/attempt/1'));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            utils\get_uuid_v5('anything')
        );
    }

    /**
     * Each quiz attempt has its own registration, the same for every event in it.
     *
     * @return void
     */
    public function test_quiz_attempts(): void {
        $config = $this->config();
        $started = (object) ['eventname' => '\mod_quiz\event\attempt_started', 'objectid' => 1];
        $submitted = (object) ['eventname' => '\mod_quiz\event\attempt_submitted', 'objectid' => 1];
        $graded = (object) [
            'eventname' => '\mod_quiz\event\question_manually_graded',
            'objectid' => 9,
            'other' => json_encode(['attemptid' => 1, 'quizid' => 1, 'slot' => 2]),
        ];
        $other = (object) ['eventname' => '\mod_quiz\event\attempt_started', 'objectid' => 2];

        $registration = utils\get_registration($config, $started);
        $this->assertSame($registration, utils\get_registration($config, $submitted));
        $this->assertSame($registration, utils\get_registration($config, $graded));
        $this->assertNotSame($registration, utils\get_registration($config, $other));
    }

    /**
     * A lesson retake is the number of the learner's retakes completed before the event.
     *
     * @return void
     */
    public function test_lesson_retakes(): void {
        $config = $this->config([
            'lesson_grades' => [
                (object) ['id' => 1, 'lessonid' => 3, 'userid' => 5, 'completed' => 100],
                // This retake's grade, stored after its lesson_ended event.
                (object) ['id' => 2, 'lessonid' => 3, 'userid' => 5, 'completed' => 200],
                (object) ['id' => 3, 'lessonid' => 3, 'userid' => 6, 'completed' => 50],
            ],
        ]);
        $event = function (string $name, int $time) {
            return (object) ['eventname' => "\\mod_lesson\\event\\$name", 'objectid' => 3, 'userid' => 5, 'timecreated' => $time];
        };

        $this->assertSame('/mod/lesson/3/user/5/retake/0', utils\get_attempt_name($config, $event('lesson_started', 50)));
        $this->assertSame('/mod/lesson/3/user/5/retake/1', utils\get_attempt_name($config, $event('lesson_started', 150)));
        $this->assertSame('/mod/lesson/3/user/5/retake/1', utils\get_attempt_name($config, $event('lesson_ended', 200)));
        $this->assertSame('/mod/lesson/3/user/5/retake/2', utils\get_attempt_name($config, $event('lesson_restarted', 250)));
    }

    /**
     * A reopened assignment submission is a new attempt; the grade of an attempt shares its
     * registration.
     *
     * @return void
     */
    public function test_assignment_attempts(): void {
        $config = $this->config([
            'assign_submission' => [
                (object) ['id' => 1, 'assignment' => 4, 'userid' => 5, 'attemptnumber' => 0],
                (object) ['id' => 2, 'assignment' => 4, 'userid' => 5, 'attemptnumber' => 1],
            ],
            'assign_grades' => [
                (object) ['id' => 7, 'assignment' => 4, 'userid' => 5, 'attemptnumber' => 1],
            ],
        ]);
        $first = (object) ['eventname' => '\mod_assign\event\assessable_submitted', 'objectid' => 1, 'userid' => 5];
        $second = (object) ['eventname' => '\mod_assign\event\assessable_submitted', 'objectid' => 2, 'userid' => 5];
        $graded = (object) ['eventname' => '\mod_assign\event\submission_graded', 'objectid' => 7, 'userid' => 9];

        $this->assertNotSame(utils\get_registration($config, $first), utils\get_registration($config, $second));
        $this->assertSame(utils\get_registration($config, $second), utils\get_registration($config, $graded));
    }

    /**
     * Anonymous events and events with no attempt have no registration.
     *
     * @return void
     */
    public function test_no_registration(): void {
        $config = $this->config();
        $anonymous = (object) ['eventname' => '\mod_quiz\event\attempt_started', 'objectid' => 1, 'anonymous' => 1];
        $courseview = (object) ['eventname' => '\core\event\course_viewed', 'objectid' => null];
        $launched = (object) ['eventname' => '\mod_scorm\event\sco_launched', 'objectid' => 3];

        $this->assertNull(utils\get_registration($config, $anonymous));
        $this->assertNull(utils\get_registration($config, $courseview));
        $this->assertNull(utils\get_registration($config, $launched));
    }
}
