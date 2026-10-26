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
 * Unit tests for hiding the protected user in anonymous events' statements.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \src\transformer\utils\anonymise_statements
 */
final class anonymise_statements_test extends advanced_testcase {
    /**
     * Transformer config with a repository holding a grader (1) and a student (2).
     *
     * @return array
     */
    private function get_config(): array {
        return [
            'app_url' => 'http://www.example.org',
            'send_name' => true,
            'repo' => new \src\transformer\repos\TestRepository((object) [
                'user' => [
                    (object) ['id' => 1, 'firstname' => 'Grace', 'lastname' => 'Grader', 'email' => 'g@example.org'],
                    (object) ['id' => 2, 'firstname' => 'Sam', 'lastname' => 'Student', 'email' => 's@example.org'],
                ],
            ]),
        ];
    }

    /**
     * The agent for a user, built as the transformers build it.
     *
     * @param array $config The transformer config.
     * @param int $userid The user id.
     * @return array
     */
    private function agent(array $config, int $userid): array {
        return \src\transformer\utils\get_user($config, $config['repo']->read_record_by_id('user', $userid));
    }

    /**
     * A statement naming the grader as actor and the student in an extension and a team.
     *
     * @param array $config The transformer config.
     * @return array
     */
    private function statement(array $config): array {
        return [
            'actor' => $this->agent($config, 1),
            'verb' => ['id' => 'http://id.tincanapi.com/verb/viewed'],
            'object' => ['id' => 'http://www.example.org/mod/assign/view.php?id=1'],
            'context' => [
                'instructor' => $this->agent($config, 1),
                'team' => [
                    'objectType' => 'Group',
                    'member' => [$this->agent($config, 2)],
                ],
                'extensions' => [
                    'https://yetanalytics.com/profiles/prepositions/concepts/context-extensions/for' =>
                        $this->agent($config, 2),
                ],
            ],
        ];
    }

    /**
     * In an assignment event under blind marking, only the student (the related user) is hidden.
     *
     * @return void
     */
    public function test_assign_event_hides_only_the_student(): void {
        $config = $this->get_config();
        $event = (object) [
            'eventname' => '\mod_assign\event\submission_viewed',
            'userid' => 1,
            'relateduserid' => 2,
            'anonymous' => 1,
        ];
        $anonymous = \src\transformer\utils\get_anonymous_agent($config);

        [$statement] = \src\transformer\utils\anonymise_statements($config, $event, [$this->statement($config)]);

        $this->assertEquals($this->agent($config, 1), $statement['actor']);
        $this->assertEquals($this->agent($config, 1), $statement['context']['instructor']);
        $this->assertEquals([$anonymous], $statement['context']['team']['member']);
        $this->assertEquals(
            $anonymous,
            $statement['context']['extensions']['https://yetanalytics.com/profiles/prepositions/concepts/context-extensions/for']
        );
        $this->assertStringNotContainsString('Sam', json_encode($statement));
    }

    /**
     * With no related user, the user who triggered the event is hidden.
     *
     * @return void
     */
    public function test_feedback_event_without_related_user_hides_the_user(): void {
        $config = $this->get_config();
        $event = (object) [
            'eventname' => '\mod_feedback\event\course_module_viewed',
            'userid' => 2,
            'relateduserid' => null,
            'anonymous' => 1,
        ];
        $statement = ['actor' => $this->agent($config, 2), 'context' => ['instructor' => $this->agent($config, 1)]];

        [$statement] = \src\transformer\utils\anonymise_statements($config, $event, [$statement]);

        $this->assertEquals(\src\transformer\utils\get_anonymous_agent($config), $statement['actor']);
        $this->assertEquals($this->agent($config, 1), $statement['context']['instructor']);
    }

    /**
     * For an event from another component, the flag's meaning is unknown, so every agent is hidden.
     *
     * @return void
     */
    public function test_other_component_hides_every_agent(): void {
        $config = $this->get_config();
        $event = (object) [
            'eventname' => '\mod_thirdparty\event\something_happened',
            'userid' => 1,
            'relateduserid' => 2,
            'anonymous' => 1,
        ];

        [$statement] = \src\transformer\utils\anonymise_statements($config, $event, [$this->statement($config)]);

        $json = json_encode($statement);
        $this->assertStringNotContainsString('Grace', $json);
        $this->assertStringNotContainsString('Sam', $json);
        $this->assertSame('Group', $statement['context']['team']['objectType']);
    }
}
