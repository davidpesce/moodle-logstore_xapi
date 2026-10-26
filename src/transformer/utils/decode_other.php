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
 * Transformer utility for decoding an event's serialized data.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Decode the 'other' field of a log record.
 *
 * The field is normally PHP-serialized, but the standard log store writes it
 * as JSON when its jsonformat setting is enabled, and historic events are read
 * from that table. Decoding is therefore attempted both ways rather than
 * assuming one format, so that transformers keep working either way.
 *
 * Deserialization restricts allowed_classes to stdClass, matching the
 * hardening core applies when it reads the same field. The bytes are written
 * server-side by core's own event system, so this is defence in depth rather
 * than a fix for a reachable object injection.
 *
 * The format detection mirrors \tool_log\helper\reader::decode_other() so that
 * both stores read the same field the same way.
 *
 * @param mixed $other The raw value of the 'other' column.
 * @return mixed The decoded value, or null if it could not be decoded.
 */
function decode_other($other) {
    // Already decoded, e.g. an event constructed in memory rather than read
    // back from the log tables.
    if (is_array($other) || is_object($other)) {
        return $other;
    }

    if (!is_string($other) || $other === '') {
        return null;
    }

    // Sniff the format rather than attempting one and falling back, since
    // unserialize() raises a PHP warning on input it cannot parse. This is the
    // same test core applies in \tool_log\helper\reader::decode_other().
    if ($other === 'N;' || preg_match('~^.:~', $other)) {
        return unserialize($other, ['allowed_classes' => [\stdClass::class]]);
    }

    // Anything else is treated as JSON, which yields null when it is not.
    return json_decode($other, true);
}
