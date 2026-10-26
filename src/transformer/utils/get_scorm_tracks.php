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
 * Transformer utility for retrieving SCORM tracks from Moodle 4.3+ tables.
 *
 * @package   logstore_xapi
 * @copyright David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Retrieves SCORM track data from the normalized Moodle 4.3+ tables.
 *
 * Returns objects with 'element' and 'value' properties, matching the shape
 * expected by get_scorm_verb() and get_scorm_result().
 *
 * The scoreraw_submitted and status_submitted events carry the scorm_attempt row id and the
 * CMI element and value that were just saved, but not the SCO. scorm_insert_track() saves the
 * value before triggering the event, so the SCO is the one whose stored value for that element
 * in this attempt matches the event.
 *
 * @param array $config The transformer config settings.
 * @param int $attemptid The scorm_attempt id, from the event's other['attemptid'].
 * @param string $cmielement The CMI element from the event, e.g. cmi.core.score.raw.
 * @param mixed $cmivalue The CMI value from the event.
 * @return array
 */
function get_scorm_tracks(array $config, int $attemptid, string $cmielement, $cmivalue) {
    $repo = $config['repo'];

    // Throws if the attempt does not exist, so the event is reported as a transform failure.
    $attemptrecord = $repo->read_record_by_id('scorm_attempt', $attemptid);
    $elementrecord = $repo->read_record('scorm_element', ['element' => $cmielement]);

    $candidates = $repo->read_records('scorm_scoes_value', [
        'attemptid' => $attemptrecord->id,
        'elementid' => $elementrecord->id,
    ]);
    $matching = array_filter($candidates, function ($candidate) use ($cmivalue) {
        return (string) $candidate->value === (string) $cmivalue;
    });
    // The value may have changed again before the event was processed; fall back to any SCO
    // that has this element in the attempt, preferring the most recently modified.
    $pool = $matching ?: $candidates;
    if (!$pool) {
        throw new \Exception("scorm_scoes_value not found for attempt $attemptid and $cmielement.");
    }
    usort($pool, function ($a, $b) {
        return ($b->timemodified ?? 0) <=> ($a->timemodified ?? 0);
    });
    $scoid = reset($pool)->scoid;

    $values = $repo->read_records('scorm_scoes_value', [
        'attemptid' => $attemptrecord->id,
        'scoid' => $scoid,
    ]);

    $tracks = [];
    foreach ($values as $v) {
        $element = $repo->read_record_by_id('scorm_element', $v->elementid);
        $track = new \stdClass();
        $track->element = $element->element;
        $track->value = $v->value;
        $tracks[] = $track;
    }

    return $tracks;
}
