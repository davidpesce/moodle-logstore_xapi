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

namespace XREmitter\Tests;

defined('MOODLE_INTERNAL') || die();

use \XREmitter\Events\AttemptStarted as Event;

class attempt_started_test extends event_test {
    protected static $recipename = 'attempt_started';

    /**
     * Sets up the tests.
     * @override EventTest
     */
    public function setup() {
        $this->event = new Event();
    }

    protected function construct_input() {
        return array_merge(
            parent::construct_input(),
            $this->construct_object('course'),
            $this->construct_object('module'),
            $this->construct_attempt()
        );
    }

    protected function assert_output($input, $output) {
        parent::assert_output($input, $output);
        $this->assert_verb('http://activitystrea.ms/schema/1.0/start', 'started', $output['verb']);
        $this->assert_object('module', $input, $output['context']['contextActivities']['grouping'][2]);
        $this->assert_object('course', $input, $output['context']['contextActivities']['grouping'][1]);
        $this->assert_attempt($input, $output['object']);
    }
}
