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
 * Load batch of events.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\loader\utils;

/**
 * Load a batch of events.
 *
 * @param array $config An array of configuration settings.
 * @param array $transformedevents An array of events.
 * @param callable $loader
 * @return array
 */
function load_batch(array $config, array $transformedevents, callable $loader) {
    try {
        $loader($config, get_batch_statements($transformedevents));
        $loadedevents = construct_loaded_events($transformedevents, true);
        return $loadedevents;
    } catch (\Throwable $e) {
        $batchsize = count($transformedevents);
        $logerror = $config['log_error'];
        $loginfo = $config['log_info'];

        // A 409 means the LRS already has a statement with one of these ids, and it rejects the
        // whole batch. The ids come from the statements' content, so for a single event this
        // means its statements are already stored (an LRS may answer 409 to an exact resend).
        if ($e->getCode() === 409 && $batchsize === 1) {
            $loginfo('The LRS already has the statements of this event: ' . $e->getMessage());
            return construct_loaded_events($transformedevents, true);
        }
        $logerror("Failed load batch (" . $batchsize . " events)" . $e->getMessage());
        $loginfo($e->getTraceAsString());

        // Add error code.
        $errorcode = $e->getCode();
        $errormessage = $e->getMessage();
        foreach ($transformedevents as $event) {
            if ($event["transformed"] == true) {
                $event["event"]->response = $errormessage;
                $event["event"]->errortype = $errorcode;
            }
        }

        // Split the batch in half and send each half, down to the events that fail.
        if ($batchsize === 1 || !is_split_error($config, $e)) {
            $loadedevents = construct_loaded_events($transformedevents, false);
        } else {
            $newconfig = $config;
            $newconfig['lrs_max_batch_size'] = round($batchsize / 2);
            $batches = get_event_batches($newconfig, $transformedevents);
            $loadedevents = array_reduce($batches, function ($result, $batch) use ($newconfig, $loader) {
                $loadedbatchevents = load_batch($newconfig, $batch, $loader);
                return array_merge($result, $loadedbatchevents);
            }, []);
        }
        return $loadedevents;
    }
}

/**
 * Should a batch that failed with this error be split, so that only the events at fault fail?
 *
 * Always for a 409, so the statements the LRS doesn't have yet are stored. For a 400 (bad data),
 * only when "Resend failed batches" is on.
 *
 * @param array $config An array of configuration settings.
 * @param \Throwable $e The error.
 * @return bool
 */
function is_split_error(array $config, \Throwable $e): bool {
    return $e->getCode() === 409 || ($e->getCode() === 400 && $config['lrs_resend_failed_batches'] === '1');
}

/**
 * The statements to send for a batch of events, each id once.
 *
 * Statement ids come from their content (see set_statement_ids()), so a repeated id is an
 * identical statement, such as from two identical events in the same second. An LRS rejects a
 * batch with two statements with the same id, and would keep only one of them anyway.
 *
 * @param array $transformedevents The transformed events.
 * @return array The statements.
 */
function get_batch_statements(array $transformedevents): array {
    $statements = [];
    $ids = [];
    foreach ($transformedevents as $transformedevent) {
        foreach ($transformedevent['statements'] as $statement) {
            $id = $statement['id'] ?? null;
            if (!is_null($id) && isset($ids[$id])) {
                continue;
            }
            $ids[$id] = true;
            $statements[] = $statement;
        }
    }
    return $statements;
}
