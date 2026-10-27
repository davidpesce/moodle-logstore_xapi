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
 * Transformer utility for the context.registration of statements about an attempt.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * The context.registration for an event's statements: one UUID per attempt.
 *
 * Only events that belong to an attempt Moodle records get one (quiz attempts, SCORM attempts,
 * lesson retakes and assignment attempts), so every statement with the same registration is
 * about the same attempt. The UUID is a version 5 UUID of a site-specific name for the attempt,
 * so live sending, historic backfill and retries give the same attempt the same registration.
 *
 * Anonymous events get none: a registration would link them to the same attempt's statements
 * that are not anonymous, such as those sent after an assignment's identities are revealed.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event.
 * @return ?string The registration, or null when the event has no attempt.
 */
function get_registration(array $config, \stdClass $event): ?string {
    if (!empty($event->anonymous)) {
        return null;
    }
    $attempt = get_attempt_name($config, $event);
    return is_null($attempt) ? null : get_uuid_v5($config['app_url'] . $attempt);
}

/**
 * A name for the attempt an event belongs to, unique within the site.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event.
 * @return ?string The name, or null when the event has no attempt.
 */
function get_attempt_name(array $config, \stdClass $event): ?string {
    $other = (array) decode_other($event->other ?? null);
    $eventname = substr($event->eventname, strrpos($event->eventname, '\\') + 1);
    switch (strtok(ltrim($event->eventname, '\\'), '\\')) {
        case 'mod_quiz':
            return get_quiz_attempt_name($eventname, $event, $other);
        case 'mod_scorm':
            // The SCORM attempt (scorm_attempt). sco_launched does not say which attempt it is.
            $scored = in_array($eventname, ['status_submitted', 'scoreraw_submitted'], true);
            return $scored && isset($other['attemptid']) ? '/mod/scorm/attempt/' . $other['attemptid'] : null;
        case 'mod_lesson':
            return get_lesson_attempt_name($config, $eventname, $event, $other);
        case 'mod_assign':
            return get_assign_attempt_name($config, $eventname, $event);
        default:
            return null;
    }
}

/**
 * The name of the quiz attempt a quiz event belongs to.
 *
 * @param string $eventname The event name, without its namespace.
 * @param \stdClass $event The event.
 * @param array $other The event's other data.
 * @return ?string
 */
function get_quiz_attempt_name(string $eventname, \stdClass $event, array $other): ?string {
    $attemptevents = [
        'attempt_started', 'attempt_viewed', 'attempt_reviewed', 'attempt_submitted', 'attempt_becameoverdue',
        'attempt_abandoned',
    ];
    if (in_array($eventname, $attemptevents, true)) {
        return '/mod/quiz/attempt/' . $event->objectid;
    }
    if ($eventname === 'question_manually_graded' && isset($other['attemptid'])) {
        return '/mod/quiz/attempt/' . $other['attemptid'];
    }
    return null;
}

/**
 * The name of the lesson retake a lesson event belongs to.
 *
 * @param array $config The transformer config settings.
 * @param string $eventname The event name, without its namespace.
 * @param \stdClass $event The event.
 * @param array $other The event's other data.
 * @return ?string
 */
function get_lesson_attempt_name(array $config, string $eventname, \stdClass $event, array $other): ?string {
    $repo = $config['repo'];
    switch ($eventname) {
        case 'lesson_started':
        case 'lesson_resumed':
        case 'lesson_restarted':
        case 'lesson_ended':
            return get_lesson_retake_name($config, (int) $event->objectid, (int) $event->userid, $event);
        case 'question_viewed':
        case 'question_answered':
        case 'content_page_viewed':
            $page = $repo->read_record_by_id('lesson_pages', $event->objectid);
            return get_lesson_retake_name($config, (int) $page->lessonid, (int) $event->userid, $event);
        case 'essay_assessed':
            // A teacher grades the learner's answer, which records its retake.
            $attempt = $repo->read_record_by_id('lesson_attempts', $other['attemptid']);
            return '/mod/lesson/' . $attempt->lessonid . '/user/' . $attempt->userid . '/retake/' . $attempt->retry;
        default:
            return null;
    }
}

/**
 * The name of the assignment attempt an assignment event belongs to. A reopened submission is
 * a new attempt with its own number.
 *
 * @param array $config The transformer config settings.
 * @param string $eventname The event name, without its namespace.
 * @param \stdClass $event The event.
 * @return ?string
 */
function get_assign_attempt_name(array $config, string $eventname, \stdClass $event): ?string {
    $repo = $config['repo'];
    if ($eventname === 'assessable_submitted') {
        $submission = $repo->read_record_by_id('assign_submission', $event->objectid);
        return '/mod/assign/' . $submission->assignment . '/user/' . $event->userid
            . '/attempt/' . $submission->attemptnumber;
    }
    if ($eventname === 'submission_graded') {
        $grade = $repo->read_record_by_id('assign_grades', $event->objectid);
        return '/mod/assign/' . $grade->assignment . '/user/' . $grade->userid . '/attempt/' . $grade->attemptnumber;
    }
    return null;
}

/**
 * The name of a lesson retake: the learner's retakes completed before the event.
 *
 * Core numbers retakes this way (lesson_attempts.retry is the count of the user's lesson_grades
 * when the answer is saved), and a retake's grade is stored after its lesson_ended event, so
 * counting the grades completed before the event gives the event's retake, also when the event
 * is sent later.
 *
 * @param array $config The transformer config settings.
 * @param int $lessonid The lesson id.
 * @param int $userid The learner's id.
 * @param \stdClass $event The event.
 * @return string
 */
function get_lesson_retake_name(array $config, int $lessonid, int $userid, \stdClass $event): string {
    $grades = $config['repo']->read_records('lesson_grades', ['lessonid' => $lessonid, 'userid' => $userid]);
    $retake = count(array_filter($grades, function ($grade) use ($event) {
        return $grade->completed < $event->timecreated;
    }));
    return '/mod/lesson/' . $lessonid . '/user/' . $userid . '/retake/' . $retake;
}

/**
 * A version 5 UUID (RFC 4122) of a name in the URL namespace.
 *
 * @param string $name The name, a URL.
 * @return string
 */
function get_uuid_v5(string $name): string {
    // The URL namespace, 6ba7b811-9dad-11d1-80b4-00c04fd430c8, as bytes.
    $namespace = hex2bin('6ba7b8119dad11d180b400c04fd430c8');
    $hash = sha1($namespace . $name);
    return sprintf(
        '%s-%s-%04x-%04x-%s',
        substr($hash, 0, 8),
        substr($hash, 8, 4),
        (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
        (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
        substr($hash, 20, 12)
    );
}
