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
 * Transformer utility for recognising personal calendar events.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Whether a calendar event is a user's own ("user" event type), which only its owner can see.
 *
 * Moodle logs those events in the user's context, which still says so once the event is deleted.
 *
 * @param \stdClass $event The calendar event's log event.
 * @param ?\stdClass $calendarevent The calendar event, if it still exists.
 * @return bool
 */
function is_personal_calendar_event(\stdClass $event, ?\stdClass $calendarevent = null): bool {
    return (int) ($event->contextlevel ?? 0) === CONTEXT_USER
        || ($calendarevent->eventtype ?? '') === 'user';
}
