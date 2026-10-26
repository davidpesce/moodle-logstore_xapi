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
 * Unit tests for the event 'other' decoder.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \src\transformer\utils\decode_other
 */
final class decode_other_test extends advanced_testcase {
    /**
     * Call the decoder.
     *
     * @param mixed $other The raw value.
     * @return mixed
     */
    private function decode($other) {
        return \src\transformer\utils\decode_other($other);
    }

    /**
     * The historic format: a PHP-serialized array.
     *
     * @return void
     */
    public function test_decodes_serialized_array(): void {
        $value = ['discussionid' => 1, 'forumid' => 2, 'forumtype' => 'general'];

        $this->assertSame($value, $this->decode(serialize($value)));
    }

    /**
     * The standard log store writes JSON when jsonformat is enabled, and
     * historic events are read from that table. Before this decoder, such
     * events failed to transform.
     *
     * @return void
     */
    public function test_decodes_json_array(): void {
        $value = ['discussionid' => 1, 'forumid' => 2, 'forumtype' => 'general'];

        $this->assertSame($value, $this->decode(json_encode($value)));
    }

    /**
     * Serialized null is the common "no extra data" value and must not be
     * confused with a decode failure.
     *
     * @return void
     */
    public function test_decodes_serialized_null(): void {
        $this->assertNull($this->decode('N;'));
    }

    /**
     * Serialized false is the one input that legitimately decodes to false.
     *
     * @return void
     */
    public function test_decodes_serialized_false(): void {
        $this->assertFalse($this->decode('b:0;'));
    }

    /**
     * A serialized payload must not be misread as JSON, which is what makes
     * trying JSON first safe.
     *
     * @return void
     */
    public function test_serialized_payloads_are_not_read_as_json(): void {
        foreach ([[], ['a' => 1], 'text', 0, 1.5, true] as $value) {
            $this->assertEquals($value, $this->decode(serialize($value)));
        }
    }

    /**
     * Objects in the payload are restored as stdClass, and nothing else is
     * instantiated.
     *
     * @return void
     */
    public function test_objects_restore_as_stdclass(): void {
        $value = new \stdClass();
        $value->name = 'test';

        $decoded = $this->decode(serialize($value));

        $this->assertInstanceOf(\stdClass::class, $decoded);
        $this->assertSame('test', $decoded->name);
    }

    /**
     * A serialized object of another class must not be instantiated. PHP
     * restores it as __PHP_Incomplete_Class instead, which is the point of
     * restricting allowed_classes.
     *
     * @return void
     */
    public function test_other_classes_are_not_instantiated(): void {
        $decoded = $this->decode('O:8:"DateTime":0:{}');

        $this->assertNotInstanceOf(\DateTime::class, $decoded);
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $decoded);
    }

    /**
     * Values that are already decoded pass straight through.
     *
     * @return void
     */
    public function test_already_decoded_values_pass_through(): void {
        $array = ['a' => 1];
        $object = new \stdClass();

        $this->assertSame($array, $this->decode($array));
        $this->assertSame($object, $this->decode($object));
    }

    /**
     * Empty and non-string input yields null rather than a PHP warning.
     *
     * @return void
     */
    public function test_empty_and_invalid_input_returns_null(): void {
        $this->assertNull($this->decode(''));
        $this->assertNull($this->decode(null));
        $this->assertNull($this->decode('not serialized, not json'));
    }
}
