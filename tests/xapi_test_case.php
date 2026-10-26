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

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/vendor/autoload.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');

foreach (glob($CFG->dirroot . '/admin/tool/log/store/xapi/tests/utils/*.php') as $filename) {
    require_once($filename);
}

use Locker\XApi\Statement as LockerStatement;
use TestUtils as utils;

/**
 * Default test cases for the plugin.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 *            Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class xapi_test_case extends \advanced_testcase {
    /**
     * Retrieve the directory of the unit test.
     */
    abstract protected function get_test_dir();

    /**
     * Retrieve the plugin type being tested.
     */
    abstract protected function get_plugin_type();

    /**
     * Retrieve the plugin name being tested.
     */
    abstract protected function get_plugin_name();

    /**
     * Retrieve the test data from data.json.
     *
     * @return object
     */
    protected function get_test_data() {
        // Note: common data is re-read on each call; refactor if performance matters.
        // Get common event fields.
        global $CFG;
        $commondata = json_decode(file_get_contents($CFG->dirroot . '/admin/tool/log/store/xapi/tests/common/data.json'));
        $data = json_decode(file_get_contents($this->get_test_dir() . '/data.json'));

        return utils\deep_merge_objects($commondata, $data);
    }

    /**
     * Retrieve the event data from event.json.
     *
     * @return object
     */
    protected function get_event() {
        // Note: common data is re-read on each call; refactor if performance matters.
        // Get common event fields.
        global $CFG;
        $commonevent = json_decode(file_get_contents($CFG->dirroot . '/admin/tool/log/store/xapi/tests/common/event.json'));
        // Get this event.
        $event = json_decode(file_get_contents($this->get_test_dir() . '/event.json'));
        // Merge and return.
        return utils\deep_merge_objects($commonevent, $event);
    }

    /**
     * Retrieve the expected statement from statements.json.
     *
     * A null in statements.json means the property must be absent, so a test can drop a
     * property that tests/common/statement.json would otherwise add.
     *
     * @return array
     */
    protected function get_expected_statements() {
        // Note: common data is re-read on each call; refactor if performance matters.
        // Get common statement fields.
        global $CFG;
        $commonstatement = json_decode(file_get_contents($CFG->dirroot . '/admin/tool/log/store/xapi/tests/common/statement.json'));
        return array_map(function ($statement) use ($commonstatement) {
            // Add common expectations for all statements.
            return $this->remove_null_properties(utils\deep_merge_objects($commonstatement, $statement));
        }, json_decode(file_get_contents($this->get_test_dir() . '/statements.json')));
    }

    /**
     * Recursively remove properties whose value is null.
     *
     * @param \stdClass $object The expected statement, or part of it.
     * @return \stdClass
     */
    private function remove_null_properties(\stdClass $object): \stdClass {
        foreach (get_object_vars($object) as $property => $value) {
            if ($value === null) {
                unset($object->$property);
            } else if ($value instanceof \stdClass) {
                $object->$property = $this->remove_null_properties($value);
            }
        }
        return $object;
    }

    /**
     * Create the test event.
     *
     * @return void
     */
    public function test_create_event() {
        $event = $this->get_event();
        $logerror = function ($message = '') {
            echo("ERROR: $message\n");
        };
        $loginfo = function ($message = '') {
            echo("INFO: $message\n");
        };
        $handlerconfig = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'transformer' => $this->get_transformer_config(),
            'loader' => [
                'loader' => 'none',
                'lrs_endpoint' => '',
                'lrs_username' => '',
                'lrs_password' => '',
                'lrs_max_batch_size' => 1,
            ],
        ];
        $loadedevents = \src\handler($handlerconfig, [$event]);
        $statements = array_reduce($loadedevents, function ($result, $loadedevent) {
            $eventstatements = $loadedevent['statements'];
            return array_merge($result, $eventstatements);
        }, []);
        $this->assert_expected_statements($statements);
        foreach ($statements as $statement) {
            $this->assert_valid_xapi_statement($statement);
        }
    }

    /**
     * Get the transformer configuration.
     *
     * @return array
     */
    protected function get_transformer_config() {
        $testdata = $this->get_test_data();
        return [
            'source_url' => 'http://moodle.org',
            'source_name' => 'Moodle',
            'source_version' => '1.0.0',
            'source_lang' => 'en',
            'send_mbox' => false,
            'send_name' => true,
            'account_homepage' => 'http://www.example.org',
            'send_response_choices' => false,
            'send_short_course_id' => false,
            'send_course_and_module_idnumber' => false,
            'send_username' => false,
            'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
            'plugin_version' => '0.0.0-development',
            'repo' => new \src\transformer\repos\TestRepository($testdata),
            'app_url' => 'http://www.example.org',
        ];
    }

    /**
     * Assert that the statement is a valid xAPI statement.
     *
     * @param array $statement The xAPI statement.
     * @return void
     */
    private function assert_valid_xapi_statement(array $statement) {
        $errors = LockerStatement::createFromJson(json_encode($statement))->validate();
        $errorsjson = json_encode(array_map(function ($error) {
            return (string) $error;
        }, $errors));
        $this->assertEmpty($errors, $errorsjson);
        $this->assert_consistent_with_definition($statement);
    }

    /**
     * Assert the rules the schema validator does not check: responses and correct response
     * patterns must use the ids the interaction defines, true-false values must be "true" or
     * "false", and a score must fit its own bounds.
     *
     * @param array $statement The xAPI statement.
     * @return void
     */
    private function assert_consistent_with_definition(array $statement) {
        $definition = $statement['object']['definition'] ?? [];
        $result = $statement['result'] ?? [];
        $values = [];
        if (isset($result['response'])) {
            $values['result.response'] = $result['response'];
        }
        foreach ($definition['correctResponsesPattern'] ?? [] as $i => $pattern) {
            $values["correctResponsesPattern[$i]"] = $pattern;
        }

        $ids = function ($key) use ($definition) {
            return array_column($definition[$key] ?? [], 'id');
        };
        foreach ($values as $where => $value) {
            if ($value === '') {
                continue;
            }
            switch ($definition['interactionType'] ?? '') {
                case 'true-false':
                    $this->assertContains($value, ['true', 'false'], "$where must be true or false");
                    break;
                case 'choice':
                case 'sequencing':
                    if ($choices = $ids('choices')) {
                        foreach (explode('[,]', $value) as $id) {
                            $this->assertContains($id, $choices, "$where uses an id that is not a defined choice");
                        }
                    }
                    break;
                case 'matching':
                    $source = $ids('source');
                    $target = $ids('target');
                    if ($source && $target) {
                        foreach (explode('[,]', $value) as $pair) {
                            [$s, $t] = array_pad(explode('[.]', $pair, 2), 2, null);
                            $this->assertContains($s, $source, "$where uses an id that is not a defined source");
                            $this->assertContains($t, $target, "$where uses an id that is not a defined target");
                        }
                    }
                    break;
            }
        }

        $score = $result['score'] ?? [];
        if (isset($score['scaled'])) {
            $this->assertGreaterThanOrEqual(-1, $score['scaled'], 'score.scaled must be at least -1');
            $this->assertLessThanOrEqual(1, $score['scaled'], 'score.scaled must be at most 1');
        }
        if (isset($score['raw'], $score['min'])) {
            $this->assertGreaterThanOrEqual($score['min'], $score['raw'], 'score.raw must not be below score.min');
        }
        if (isset($score['raw'], $score['max'])) {
            $this->assertLessThanOrEqual($score['max'], $score['raw'], 'score.raw must not exceed score.max');
        }
    }

    /**
     * Assert that the statement generated matches the expected statement.
     *
     * @param array $statements The xAPI statements to match.
     * @return void
     */
    private function assert_expected_statements(array $statements) {

        // Skip the test if the plugin is not installed.
        $pluginname = $this->get_plugin_name();
        $plugintype = $this->get_plugin_type();
        $plugins = \core_plugin_manager::instance()->get_installed_plugins($plugintype);

        if (array_key_exists($pluginname, $plugins) || $plugintype == 'core') {
            $expectedstatements = $this->get_expected_statements();
            $this->assertEquals(
                utils\object_to_array($expectedstatements),
                utils\object_to_array($statements)
            );
        } else {
            $this->markTestSkipped('Plugin ' . $pluginname . ' not installed, skipping');
        }
    }
}
