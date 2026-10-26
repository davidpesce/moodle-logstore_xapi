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
 * Generic feedback handler for the response_submitted event.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\events\mod_feedback\response_submitted;

use src\transformer\utils;
use src\transformer\events\mod_feedback\item_answered;

/**
 * Generic handler for the mod_feedback response submitted event.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event to be transformed.
 * @return array
 */
function handler(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $user = $repo->read_record_by_id('user', $event->userid);
    $feedbackvalues = $repo->read_records('feedback_value', [
        'completed' => $event->objectid,
    ]);
    $feedbackcompleted = $repo->read_record_by_id('feedback_completed', $event->objectid);
    // The database returns anonymous_response as a string, so compare as an int. 1 is
    // FEEDBACK_ANONYMOUS_YES. Core also flags the event itself as anonymous.
    $isanon = !empty($event->anonymous) || (int) $feedbackcompleted->anonymous_response === 1;
    $actor = ($isanon)
        ? utils\get_anonymous_agent($config)
        : utils\get_user($config, $user);

    return array_merge(
        response_submitted($config, $event, $actor),
        array_reduce($feedbackvalues, function ($result, $feedbackvalue) use ($config, $event, $actor) {
            return array_merge($result, item_answered\handler($config, $event, $feedbackvalue, $actor));
        }, [])
    );
}
