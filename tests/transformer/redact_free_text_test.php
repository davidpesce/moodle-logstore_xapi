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
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/transformer/repos/TestRepository.php');

/**
 * Unit tests for personal-data redaction in free text.
 *
 * @package   logstore_xapi
 * @copyright 2026 Flavio <flavio@ferreira-michell.org>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \src\transformer\utils\redact_free_text
 */
final class redact_free_text_test extends \basic_testcase {
    /**
     * Redact text with a test repository.
     *
     * @param string|null $text The input text.
     * @param int $courseid The course id.
     * @param array $userids The user ids whose names must be redacted.
     * @param array $users The test users.
     * @param bool $redact Whether to apply the replacement rules.
     * @return string
     */
    private function redact(
        ?string $text,
        int $courseid = 0,
        array $userids = [],
        array $users = [],
        bool $redact = true
    ): string {
        $data = (object) ['user' => $users];
        $config = [
            'repo' => new \src\transformer\repos\TestRepository($data),
            'redact_free_text' => $redact,
        ];
        return \src\transformer\utils\redact_free_text($config, $text, $courseid, $userids);
    }

    /**
     * Personal identifiers and the author's names are replaced.
     *
     * @covers ::redact_free_text
     * @return void
     */
    public function test_redacts_personal_identifiers_and_author_name(): void {
        $users = [(object) [
            'id' => 2,
            'firstname' => 'sakura',
            'lastname' => 'uchiha',
            'username' => 'learner2',
        ]];

        $text = '<p>Sakura Uchiha a.b@example.com https://example.org/path 07700 900123 '
            . 'AB 12 34 56 C SW1A 1AA 123456</p>';

        $this->assertSame(
            '[name] [name] [email] [url] [phone] [ni] [postcode] [number]',
            $this->redact($text, 0, [2], $users)
        );
    }

    /**
     * Names of course enrollees are removed while common words stay readable.
     *
     * @covers ::redact_free_text
     * @return void
     */
    public function test_redacts_course_enrollee_name_but_keeps_common_word(): void {
        $users = [(object) [
            'id' => 3,
            'firstname' => 'kensuke',
            'lastname' => 'aida',
            'username' => 'learner3',
        ]];
        $data = (object) [
            'user' => $users,
            'enrol' => [(object) ['id' => 10, 'courseid' => 2]],
            'user_enrolments' => [(object) ['id' => 20, 'enrolid' => 10, 'userid' => 3]],
        ];
        $config = ['repo' => new \src\transformer\repos\TestRepository($data)];

        $this->assertSame(
            '[name] May passed.',
            \src\transformer\utils\redact_free_text($config, 'Kensuke May passed.', 2)
        );
    }

    /**
     * Names that are common words are not removed by the site-wide name lookup.
     *
     * @covers ::redact_free_text
     * @return void
     */
    public function test_does_not_redact_common_words_from_other_accounts(): void {
        $users = [
            (object) ['id' => 2, 'firstname' => 'may', 'lastname' => '', 'username' => 'mayuser'],
            (object) ['id' => 3, 'firstname' => 'will', 'lastname' => '', 'username' => 'willuser'],
            (object) ['id' => 4, 'firstname' => 'mark', 'lastname' => '', 'username' => 'markuser'],
        ];

        $this->assertSame('May will mark.', $this->redact('May will mark.', 0, [], $users));
    }

    /**
     * Empty input is safe and normalized text is capped after HTML decoding.
     *
     * @covers ::redact_free_text
     * @return void
     */
    public function test_empty_html_and_length_boundaries(): void {
        $this->assertSame('', $this->redact(null));
        $this->assertSame('', $this->redact(''));
        $this->assertSame('one & two', $this->redact('<p>one &amp; two</p>'));
        $this->assertSame(1000, mb_strlen($this->redact(str_repeat('x', 1200))));
    }

    /**
     * Disabling redaction keeps normalized personal text unchanged.
     *
     * @covers ::redact_free_text
     * @return void
     */
    public function test_disabled_redaction_preserves_text(): void {
        $users = [(object) ['id' => 2, 'firstname' => 'sakura', 'lastname' => 'uchiha']];

        $this->assertSame(
            'Sakura Uchiha at a.b@example.org',
            $this->redact('<p>Sakura Uchiha at a.b@example.org</p>', 0, [2], $users, false)
        );
    }
}
