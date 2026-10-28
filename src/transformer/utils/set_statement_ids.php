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

/**
 * Transformer utility for setting the ids of an event's statements.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Give each of an event's statements an id that stays the same while its content does.
 *
 * The id is a version 5 UUID of the site URL, the event's logstore_standard_log id and the
 * statement's content. Sending the same statement again, after a timeout or from the failed log
 * or the historic report, gives the same id, so the LRS ignores it instead of storing a
 * duplicate. A statement whose content has changed, after an upgrade or a change to the data it
 * reads, gets a new id and is stored, so a corrected statement is never dropped as a conflict.
 *
 * The standard log id keeps apart different events with identical statements, such as two
 * identical submissions to an anonymous feedback activity in the same second. Without one (when
 * logstore_standard is off), the id comes from the content alone. Identical statements of the
 * same event are numbered, as an LRS rejects a batch with two statements with the same id.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event.
 * @param array $statements The event's statements, as they will be sent.
 * @return array The statements, each with an id.
 */
function set_statement_ids(array $config, \stdClass $event, array $statements): array {
    $standardlogid = empty($event->logstorestandardlogid) ? '' : (string) $event->logstorestandardlogid;
    $occurrences = [];
    $result = [];
    foreach ($statements as $statement) {
        $content = json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($content === false) {
            $content = serialize($statement);
        }
        $occurrences[$content] = ($occurrences[$content] ?? 0) + 1;
        $name = $config['app_url'] . '/statement/' . $standardlogid . '/' . $occurrences[$content] . '/' . $content;
        $result[] = ['id' => get_uuid_v5($name)] + $statement;
    }
    return $result;
}
