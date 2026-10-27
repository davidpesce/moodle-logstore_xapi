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

use stdClass as PhpObj;

/**
 * Generic respository.
 *
 * Single records are cached: the transformers read the same course, course module, user and
 * activity many times in a batch, and a quiz attempt reads them again for every question. The
 * store creates a new repository for each batch, so the cache lasts one batch. Lists of records
 * (read_records()) are not cached.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class Repository extends PhpObj {
    /** @var array Records read by read_record(), keyed by table and conditions. */
    private $records = [];

    /**
     * Reads an array of objects from the store with the given type and query.
     *
     * @param string $type The name of the table to retrieve from.
     * @param array $query Any additional conditions to add to the query.
     * @param string $sort Sort string for how to order the data.
     * @return array
     */
    abstract public function read_records(string $type, array $query, string $sort = '');

    /**
     * Reads an object from the store with the given type and query.
     *
     * A record read before by this repository is returned from the cache. Each call returns its
     * own copy, so a transformer that changes a record does not change it for the next one.
     * A missing record is not cached.
     *
     * @param string $type The name of the table to retrieve from.
     * @param array $query Any additional conditions to add to the query.
     * @throws \Exception if the record was not found
     * @return PhpObj
     */
    public function read_record(string $type, array $query) {
        // Ids come as integers from some callers and as strings from the database. They match
        // the same row, so they share an entry.
        $conditions = array_map(function ($value) {
            return is_int($value) ? (string) $value : $value;
        }, $query);
        ksort($conditions);
        $key = $type . ':' . serialize($conditions);

        if (!isset($this->records[$key])) {
            $this->records[$key] = $this->fetch_record($type, $query);
        }
        return clone $this->records[$key];
    }

    /**
     * Reads an object from the store with the given type and query, without the cache.
     *
     * @param string $type The name of the table to retrieve from.
     * @param array $query Any additional conditions to add to the query.
     * @throws \Exception if the record was not found
     * @return PhpObj
     */
    protected function fetch_record(string $type, array $query) {
        $records = $this->read_records($type, $query);
        if (empty($records)) {
            throw new \Exception("$type not found.");
        }
        return reset($records);
    }

    /**
     * Reads an object from the store with the given type and id.
     *
     * @param string $type The name of the table to retrieve from.
     * @param string $id The id of the record to retrieve.
     * @return PhpObj
     * @throws \Exception
     */
    public function read_record_by_id($type, $id) {
        return $this->read_record($type, ['id' => $id]);
    }
}
