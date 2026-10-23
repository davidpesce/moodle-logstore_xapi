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

namespace logstore_xapi\mod_lesson\question_answered\choice_multianswer;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/tests/xapi_test_case.php');

/**
 * Unit test for mod_lesson lesson question (choice) answered.
 *
 * @package   logstore_xapi
 * @copyright Cliff Casey <cliff@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class choice_multianswer_test extends \logstore_xapi\xapi_test_case {
    /**
     * Retrieve the directory of the unit test.
     *
     * @return string
     */
    protected function get_test_dir() {
        return __DIR__;
    }

    /**
     * Retrieve the plugin type being tested.
     *
     * @return string
     */
    protected function get_plugin_type() {
        return "mod";
    }

    /**
     * Retrieve the plugin name being tested.
     *
     * @return string
     */
    protected function get_plugin_name() {
        return "glossary";
    }

    /**
     * Retrieve the transformer configuration.
     *
     * @return string
     */
    protected function get_transformer_config() {
        $testdata = $this->get_test_data();
        return [
            'source_url' => 'http://moodle.org',
            'source_name' => 'Moodle',
            'source_version' => '1.0.0',
            'source_lang' => 'en',
            'send_mbox' => false,
            'send_response_choices' => true,
            'send_short_course_id' => false,
            'send_course_and_module_idnumber' => false,
            'send_username' => false,
            'session_id' => 'test_session_id',
            'send_name' => true,
            'account_homepage' => 'http://www.example.org',
            'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
            'plugin_version' => '0.0.0-development',
            'repo' => new \src\transformer\repos\TestRepository($testdata),
            'app_url' => 'http://www.example.org',
        ];
    }

    /**
     * Appease auto-detecting of test cases. xapi_test_define('has default test cases.
     *
     * @covers ::question_viewed
     * @return void
     */
    public function test_init(): void {
    }
}
