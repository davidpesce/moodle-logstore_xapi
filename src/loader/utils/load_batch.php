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
        // whole batch. The ids come from the statements' content, so a 409 for a statement means
        // it is already stored (an LRS may answer 409 to an exact resend). An event with several
        // statements may have some new ones, so they are sent one at a time.
        if ($e->getCode() === 409 && $batchsize === 1) {
            $statementerror = load_statements_one_by_one($config, get_batch_statements($transformedevents), $loader);
            if (is_null($statementerror)) {
                $loginfo('The LRS already has the statements of this event: ' . $e->getMessage());
                return construct_loaded_events($transformedevents, true);
            }
            $e = $statementerror;
        }
        $logerror("Failed load batch (" . $batchsize . " events)" . $e->getMessage());
        $loginfo($e->getTraceAsString());
        set_load_error($transformedevents, $e);

        if ($e instanceof retry_later) {
            // The LRS can't take statements now: leave the batch in the queue for a later run.
            // A batch it has failed on before (a server error) is split instead, to find the
            // events it fails on; an event it fails on alone is stuck.
            if (!is_server_error_to_split($config, $e)) {
                return construct_loaded_events($transformedevents, XAPI_LOADED_RETRY);
            }
            if ($batchsize === 1) {
                return construct_loaded_events($transformedevents, XAPI_LOADED_STUCK);
            }
        } else if ($batchsize === 1 || !is_split_error($config, $e)) {
            return construct_loaded_events($transformedevents, false);
        }

        // Split the batch in half and send each half, down to the events that fail.
        $newconfig = $config;
        $newconfig['lrs_max_batch_size'] = round($batchsize / 2);
        return load_batches($newconfig, get_event_batches($newconfig, $transformedevents), $loader);
    }
}

/**
 * Send statements one at a time, counting a 409 (already stored) as sent.
 *
 * @param array $config An array of configuration settings.
 * @param array $statements The statements.
 * @param callable $loader
 * @return \Throwable|null The first error other than a 409, or null when every statement is stored.
 */
function load_statements_one_by_one(array $config, array $statements, callable $loader): ?\Throwable {
    if (count($statements) < 2) {
        return null;
    }
    foreach ($statements as $statement) {
        try {
            $loader($config, [$statement]);
        } catch (\Throwable $e) {
            if ($e->getCode() !== 409) {
                return $e;
            }
        }
    }
    return null;
}

/**
 * Keep the error on the transformed events, for the failed log and the task's output.
 *
 * @param array $transformedevents The transformed events.
 * @param \Throwable $e The error.
 * @return void
 */
function set_load_error(array $transformedevents, \Throwable $e): void {
    foreach ($transformedevents as $event) {
        if ($event['transformed'] == true) {
            $event['event']->response = $e->getMessage();
            $event['event']->errortype = $e->getCode();
        }
    }
}

/**
 * Should a batch the LRS can't take now be split, to find the events it fails on?
 *
 * Only for a server error (5xx), and only when the batch has failed with one in the runs before
 * (see logstore_xapi_send_queued_events()): an LRS that is down fails every batch, and splitting
 * would only send it more requests.
 *
 * @param array $config An array of configuration settings.
 * @param \Throwable $e The error.
 * @return bool
 */
function is_server_error_to_split(array $config, \Throwable $e): bool {
    return !empty($config['split_server_errors']) && $e->getCode() >= 500 && $e->getCode() < 600;
}

/**
 * Should a batch that failed with this error be split, so that only the events at fault fail?
 *
 * Always for a 409, so the statements the LRS doesn't have yet are stored, and for a 413, so the
 * batch fits; a single event that is still too large fails. For a 400 (bad data), only when
 * "Resend failed batches" is on.
 *
 * @param array $config An array of configuration settings.
 * @param \Throwable $e The error.
 * @return bool
 */
function is_split_error(array $config, \Throwable $e): bool {
    return in_array($e->getCode(), [409, 413], true)
        || ($e->getCode() === 400 && $config['lrs_resend_failed_batches'] === '1');
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
