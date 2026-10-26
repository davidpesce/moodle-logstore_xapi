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
 * Transformer utility to hide the protected user in the statements of an anonymous event.
 *
 * @package   logstore_xapi
 * @copyright David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * The agent that stands in for a user whose identity is hidden.
 *
 * Every anonymous statement uses the same agent, so the LRS cannot link them to each other or
 * to a person.
 *
 * @param array $config The transformer config settings.
 * @return array
 */
function get_anonymous_agent(array $config) {
    return [
        'name' => 'Anonymous Course Participant',
        'account' => [
            'homePage' => $config['app_url'],
            'name' => 'anonymous',
        ],
    ];
}

/**
 * Replace the protected user with the anonymous agent in the statements of an anonymous event.
 *
 * Core flags an event as anonymous when a user's identity must stay hidden. In the core events
 * that set the flag (assignments under blind marking, anonymous Feedback activities), that user
 * is the event's related user (the student or respondent), or the user who triggered it when
 * there is no related user. Other people, such as the grader, stay. For events from other
 * components the meaning of the flag is unknown, so every agent is replaced.
 *
 * Agents are replaced wherever they appear: actor, instructor, team members and extensions.
 *
 * @param array $config The transformer config settings.
 * @param \stdClass $event The event.
 * @param array $statements The event's statements.
 * @return array
 */
function anonymise_statements(array $config, \stdClass $event, array $statements) {
    $protectedagents = null;
    $component = explode('\\', ltrim($event->eventname ?? '', '\\'))[0];
    if (in_array($component, ['mod_assign', 'mod_feedback'], true)) {
        $protecteduserid = !empty($event->relateduserid) ? $event->relateduserid : ($event->userid ?? null);
        try {
            $protecteduser = $config['repo']->read_record_by_id('user', $protecteduserid);
            $protectedagents = [get_user($config, $protecteduser)];
        } catch (\Exception $e) {
            // Without the user's record, the agent to hide can't be identified, so hide everyone.
            $protectedagents = null;
        }
    }

    $anonymousagent = get_anonymous_agent($config);
    return array_map(function ($statement) use ($protectedagents, $anonymousagent) {
        return anonymise_agents($statement, $protectedagents, $anonymousagent);
    }, $statements);
}

/**
 * Replace agents in part of a statement.
 *
 * @param mixed $value Part of a statement.
 * @param ?array $protectedagents The agents to replace, or null to replace every agent.
 * @param array $anonymousagent The replacement.
 * @return mixed
 */
function anonymise_agents($value, ?array $protectedagents, array $anonymousagent) {
    if (!is_array($value)) {
        return $value;
    }

    if (is_agent($value) && (is_null($protectedagents) || in_array($value, $protectedagents))) {
        return $anonymousagent;
    }

    foreach ($value as $key => $item) {
        $value[$key] = anonymise_agents($item, $protectedagents, $anonymousagent);
    }
    return $value;
}

/**
 * Whether part of a statement is an agent: it has an inverse functional identifier and is not a group.
 *
 * @param array $value Part of a statement.
 * @return bool
 */
function is_agent(array $value) {
    $hasifi = isset($value['account']) || isset($value['mbox']) || isset($value['mbox_sha1sum'])
        || isset($value['openid']);
    return $hasifi && ($value['objectType'] ?? 'Agent') === 'Agent';
}
