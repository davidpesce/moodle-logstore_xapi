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

namespace src\transformer\repos;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/Repository.php');

/**
 * Test repository. Used in unit testing.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class TestRepository extends Repository {
    /** @var object Object to store the test data within. */
    private $testdata;

    /**
     * Create the object.
     *
     * @param object $testdata Location to store all testing data.
     * @return void
     */
    public function __construct($testdata) {
        $this->testdata = $testdata;
    }

    /**
     * Reads an array of objects from the store with the given type and query.
     *
     * Returns records the way $DB->get_records() does, so that tests see what production sees:
     * keyed by id, with every scalar value as a string. Conditions match as SQL equality does.
     *
     * @param string $type The name of the table to retrieve from.
     * @param array $query Any additional conditions to add to the query.
     * @param string $sort Sort string for how to order the data.
     * @return array
     */
    public function read_records(string $type, array $query, string $sort = '') {
        $records = $this->testdata->$type;
        $matchingrecords = [];

        foreach ($records as $record) {
            $record = $this->to_db_record($record);
            $conditionfailed = false;

            foreach ($query as $key => $value) {
                if (!$this->db_equals($record->$key ?? null, $value)) {
                    $conditionfailed = true;
                    break;
                }
            }

            if ($conditionfailed) {
                continue;
            }

            $matchingrecords[] = $record;
        }

        // Must account for lack of SQL and implement multidimensional sort with SQL syntax.
        if ($sort != '') {
            // Split by commas for each field argument.
            $fields = explode(',', $sort);

            $sortargs = [];
            foreach ($fields as $fielddeclaration) {
                // Remove (and record) direction, trim.
                $desc = str_contains(strtolower($fielddeclaration), 'desc');
                $field = preg_replace('/(DESC|ASC|\s)/i', '', $fielddeclaration);

                array_push($sortargs, array_column($matchingrecords, $field), ($desc) ? SORT_DESC : SORT_ASC);
            }
            $sortargs[] = &$matchingrecords;
            array_multisort(...$sortargs);
        }

        // Key by id after sorting: array_multisort() renumbers integer keys.
        $keyedrecords = [];
        foreach ($matchingrecords as $index => $record) {
            $keyedrecords[$record->id ?? $index] = $record;
        }

        return $keyedrecords;
    }

    /**
     * Copy a fixture record, converting scalar values to strings as the database driver does.
     *
     * @param object $record The fixture record.
     * @return \stdClass
     */
    private function to_db_record($record): \stdClass {
        $dbrecord = new \stdClass();
        foreach ((array) $record as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } else if (is_int($value) || is_float($value)) {
                $value = (string) $value;
            }
            $dbrecord->$key = $value;
        }
        return $dbrecord;
    }

    /**
     * Compare a stored value with a query value as SQL equality would.
     *
     * @param mixed $stored The value in the record (a string or null).
     * @param mixed $wanted The value in the query.
     * @return bool
     */
    private function db_equals($stored, $wanted): bool {
        if (is_null($stored) || is_null($wanted)) {
            return is_null($stored) && is_null($wanted);
        }
        if (is_bool($wanted)) {
            $wanted = $wanted ? '1' : '0';
        }
        return (string) $stored === (string) $wanted;
    }
}
