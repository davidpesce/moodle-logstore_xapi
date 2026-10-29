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
 * Generic library for the logstore_xapi plugin.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('XAPI_REPORT_ID_ERROR', 0);
define('XAPI_REPORT_ID_HISTORIC', 1);

// Type constants.
define('XAPI_IMPORT_TYPE_LIVE', 0);
define('XAPI_IMPORT_TYPE_HISTORIC', 1);
define('XAPI_IMPORT_TYPE_FAILED', 2);

// Report source.
define('XAPI_REPORT_SOURCE_LOG', 'logstore_xapi_log');
define('XAPI_REPORT_SOURCE_FAILED', 'logstore_xapi_failed_log');
define('XAPI_REPORT_SOURCE_HISTORICAL', 'logstore_standard_log');

// Columns of the failed log that the error report may build filter options
// from. Used as an allow-list, since a column name cannot be bound as a query
// parameter and has to be interpolated into the SQL. Responses are offered by
// hash instead (logstore_xapi_get_response_options()).
define('XAPI_REPORT_FILTER_COLUMNS', ['errortype']);

// The most responses the error report's response filter lists. Each distinct LRS
// error is one option, so a site with many kinds of failure would otherwise get an
// unusable list; the most frequent ones are offered.
define('XAPI_REPORT_RESPONSE_OPTIONS_MAX', 100);

// Error types.
define('XAPI_REPORT_ERRORTYPE_NETWORK', 101);
define('XAPI_REPORT_ERRORTYPE_RECIPE', 400);
define('XAPI_REPORT_ERRORTYPE_AUTH', 401);
define('XAPI_REPORT_ERRORTYPE_LRS', 500);
define('XAPI_REPORT_ERRORTYPE_TRANSFORM', 10000); // This high number has been set to avoid conflicting with other error codes.

// The "loaded" state of events the LRS can't take now (see \src\loader\utils\retry_later):
// they stay in the queue for a later run. Sent events are true, failed ones false and skipped
// ones null.
define('XAPI_LOADED_RETRY', 'retry');

// The default time limit, in seconds, for each run of the tasks that send queued events.
define('XAPI_MAX_RUNTIME_DEFAULT', 5 * MINSECS);

// Resend values.
define('XAPI_REPORT_RESEND_FALSE', 0);
define('XAPI_REPORT_RESEND_TRUE', 1);

// Default values on url parameters.
define('XAPI_REPORT_STARTING_PAGE', 0);
define('XAPI_REPORT_PERPAGE_DEFAULT', 40);
define('XAPI_REPORT_PERPAGE_MAX', 500);
define('XAPI_REPORT_ONPAGE_DEFAULT', '');
define('XAPI_REPORT_COURSEID_DEFAULT', 0);
define('XAPI_REPORT_ACTIVITY_DEFAULT', 0);
define('XAPI_REPORT_EVENTNAMES_DEFAULT', []);
define('XAPI_REPORT_ERROTYPE_DEFAULT', '0');
define('XAPI_REPORT_RESPONSE_DEFAULT', '0');
define('XAPI_REPORT_USERNAME_DEFAULT', '');
define('XAPI_REPORT_DATEFROM_DEFAULT', 0);
define('XAPI_REPORT_DATETO_DEFAULT', 0);

// Events whose statements carry the text of private messages, of notes about a
// user, or of a user's search queries (which can hold names, health or other
// personal details). They are left out of the default routes so that a new
// install does not copy that text to the LRS unless an administrator chooses to.
define('XAPI_ROUTES_OFF_BY_DEFAULT', [
    '\\core\\event\\group_message_sent',
    '\\core\\event\\message_sent',
    '\\core\\event\\message_viewed',
    '\\core\\event\\note_created',
    '\\core\\event\\note_updated',
    '\\core\\event\\notes_viewed',
    '\\core\\event\\search_results_viewed',
]);

// What to do with events core flags as anonymous (the user's identity must stay hidden, for
// example assignment events under blind marking): send them with the protected user replaced
// by an anonymous actor, or don't send them. The anonymousevents setting holds one of these.
define('XAPI_ANONYMOUS_EVENTS_ANONYMISE', 'anonymise');
define('XAPI_ANONYMOUS_EVENTS_SKIP', 'skip');

/**
 * Get the routes that are enabled on a new install.
 *
 * Every supported event is enabled except those in XAPI_ROUTES_OFF_BY_DEFAULT.
 * Sites that already have the setting keep their choice.
 *
 * @param array $eventnames The supported event names.
 * @return array The default setting, event names mapped to 1.
 */
function logstore_xapi_get_default_routes(array $eventnames) {
    $defaults = [];
    foreach ($eventnames as $eventname) {
        if (!in_array($eventname, XAPI_ROUTES_OFF_BY_DEFAULT, true)) {
            $defaults[$eventname] = 1;
        }
    }
    return $defaults;
}

/**
 * Get all visible cohorts in the system.
 *
 * @return array Returns an array of all visible cohorts.
 */
function logstore_xapi_get_cohorts() {
    global $DB;
    $array = ["visible" => 1];
    $cohorts = $DB->get_records("cohort", $array);
    return $cohorts;
}

/**
 * Get the selected cohorts from the settings.
 *
 * Only returns IDs for cohorts that still exist and are visible, filtering out
 * any cohorts that have been deleted or made invisible since the selection was saved.
 *
 * @return array Returns an array of selected cohort ids.
 */
function logstore_xapi_get_selected_cohorts() {
    global $DB;

    $selected = get_config('logstore_xapi', 'cohorts');

    if (empty($selected)) {
        return [];
    }

    $ids = array_filter(array_map('intval', explode(',', $selected)));

    if (empty($ids)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
    $inparams['visible'] = 1;
    $records = $DB->get_records_select('cohort', "id $insql AND visible = :visible", $inparams, '', 'id');

    return array_map('strval', array_keys($records));
}

/**
 * Return the active members of the given cohorts.
 *
 * A user who belongs to several of the cohorts is returned once, and deleted
 * or suspended users are left out, since they should not be notified.
 *
 * @param array $cohortids array of cohort ids
 * @return array user records keyed by user id
 */
function logstore_xapi_get_cohort_members($cohortids) {
    global $DB;

    if (empty($cohortids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal($cohortids, SQL_PARAMS_NAMED);
    $params['deleted'] = 0;
    $params['suspended'] = 0;
    // Only the fields email_to_user() needs, not the whole user record.
    $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
    $sql = "SELECT u.id, u.username, u.email, u.auth, u.mailformat, u.suspended, u.deleted, $namefields
              FROM {user} u
             WHERE u.deleted = :deleted
               AND u.suspended = :suspended
               AND EXISTS (SELECT 1
                             FROM {cohort_members} cm
                            WHERE cm.userid = u.id
                              AND cm.cohortid $insql)
          ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Get the users to send failure notifications to.
 *
 * Each email address is returned once, so that nobody is emailed twice when
 * they are both a cohort member and listed as an additional address.
 *
 * @return array Returns an array of user objects from cohorts and additional email addresses.
 */
function logstore_xapi_get_users_for_notifications() {
    // Get selected cohort users, will return a blank array if no cohorts are set.
    $cohorts = logstore_xapi_get_selected_cohorts();
    $users = [];
    foreach (logstore_xapi_get_cohort_members($cohorts) as $user) {
        $users[core_text::strtolower($user->email)] = $user;
    }

    // Get the manually set email addresses from the config.
    $emailaddresses = get_config('logstore_xapi', 'send_additional_email_addresses');
    $emailaddresses = explode(",", (string) $emailaddresses);
    foreach ($emailaddresses as $email) {
        // Remove whitespace from email addresses.
        $email = preg_replace('/\s+/', '', $email);
        $key = core_text::strtolower($email);
        if (validate_email($email) && !isset($users[$key])) {
            // If the email address is valid then add it to the list of users.
            $user = new stdClass();
            $user->email = $email;
            $users[$key] = $user;
        }
    }

    return array_values($users);
}

/**
 * Gets the unique column values
 *
 * The column name is interpolated into SQL, since identifiers cannot be bound
 * as parameters. It is checked against a fixed allow-list so that a future
 * caller cannot turn this helper into an injection point.
 *
 * @param string $column One of the columns listed in XAPI_REPORT_FILTER_COLUMNS.
 * @return array
 * @throws coding_exception If the column is not one this helper supports.
 * @throws dml_exception
 */
function logstore_xapi_get_distinct_options_from_failed_table($column) {
    global $DB;

    if (!in_array($column, XAPI_REPORT_FILTER_COLUMNS, true)) {
        throw new coding_exception('Unsupported filter column: ' . $column);
    }

    $options = [0 => get_string('any')];
    $results = $DB->get_fieldset_select('logstore_xapi_failed_log', "DISTINCT $column", '');
    if ($results) {
        foreach ($results as $result) {
            // Core's select template prints option labels unescaped, so escape (and shorten) the
            // label. The value stays raw.
            $label = (string) $result;
            if (core_text::strlen($label) > 100) {
                $label = core_text::substr($label, 0, 100) . '…';
            }
            $options[$result] = s($label);
        }
    }
    return $options;
}

/**
 * The hash the failed log stores with each response, which the error report filters and groups on.
 *
 * @param ?string $response The response.
 * @return ?string The SHA-1 of the response, or null when there is none.
 */
function logstore_xapi_hash_response(?string $response): ?string {
    return is_null($response) ? null : sha1($response);
}

/**
 * The error report's response filter options: the most frequent responses in the failed log.
 *
 * Options are keyed by response hash, so the report compares a short indexed column instead of
 * the TEXT response. Rows whose hash the upgrade has not backfilled yet are not listed (see
 * \logstore_xapi\task\hash_failed_responses).
 *
 * @return array Hash => escaped, shortened response text, after an "Any" option keyed 0.
 */
function logstore_xapi_get_response_options(): array {
    global $DB;

    $options = [0 => get_string('any')];
    $sql = "SELECT responsehash, MIN(id) AS sampleid, COUNT(1) AS failures
              FROM {logstore_xapi_failed_log}
             WHERE responsehash IS NOT NULL
          GROUP BY responsehash
          ORDER BY COUNT(1) DESC, MIN(id) ASC";
    $hashes = $DB->get_records_sql($sql, [], 0, XAPI_REPORT_RESPONSE_OPTIONS_MAX);
    if (empty($hashes)) {
        return $options;
    }

    [$insql, $params] = $DB->get_in_or_equal(array_column($hashes, 'sampleid'));
    $responses = $DB->get_records_select_menu('logstore_xapi_failed_log', "id $insql", $params, '', 'id, response');
    foreach ($hashes as $hash) {
        // The response column holds raw LRS error bodies, and core's select template prints
        // option labels unescaped, so escape (and shorten) the label.
        $label = (string) ($responses[$hash->sampleid] ?? '');
        if (core_text::strlen($label) > 100) {
            $label = core_text::substr($label, 0, 100) . '…';
        }
        $options[$hash->responsehash] = s($label);
    }
    return $options;
}

/**
 * Whether some failed events still have no response hash, so the error report's response
 * filter does not list them yet.
 *
 * @return bool
 */
function logstore_xapi_has_unhashed_responses(): bool {
    global $DB;
    return $DB->record_exists_select('logstore_xapi_failed_log', 'responsehash IS NULL AND response IS NOT NULL');
}

/**
 * The historic report's activity filter options: the activities of one course.
 *
 * The filter lists a course's activities once a course is chosen, rather than every context in
 * the standard log, which on a large site meant scanning the whole table.
 *
 * @param int $courseid The course id.
 * @return array Activity context id => escaped "Type: name" label.
 */
function logstore_xapi_get_course_activity_options(int $courseid): array {
    global $DB;

    if (empty($courseid) || !$DB->record_exists('course', ['id' => $courseid])) {
        return [];
    }
    $options = [];
    foreach (get_fast_modinfo($courseid)->get_cms() as $cm) {
        // Core's select template prints option labels unescaped, so escape them. The name is
        // already formatted (and escaped) by get_formatted_name().
        $options[$cm->context->id] = s($cm->get_module_type_name()) . ': ' . $cm->get_formatted_name();
    }
    return $options;
}

/**
 * Retrieves the available and enabled events for this plugin and outputs it into an array
 *
 * @return array
 */
function logstore_xapi_get_event_names_array() {

    if (!function_exists('\src\transformer\get_event_function_map')) {
        global $CFG;

        require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/transformer/get_event_function_map.php');
    }

    $eventnames = [];
    $eventfunctionmap = \src\transformer\get_event_function_map();
    foreach (array_keys($eventfunctionmap) as $eventname) {
        $eventnames[$eventname] = $eventname;
    }
    return $eventnames;
}

/**
 * Read the report filters carried in the onpage parameter.
 *
 * The parameter is JSON from the URL, so only the known filters are kept, each
 * with the type the report expects, and event names must be supported events.
 *
 * @param string $onpage The JSON-encoded filters.
 * @param array $eventnames The supported event names, as keys.
 * @return \stdClass The filters that were valid.
 */
function logstore_xapi_clean_report_filters(string $onpage, array $eventnames) {
    $filters = new \stdClass();
    $decoded = json_decode($onpage, true);
    if (!is_array($decoded)) {
        return $filters;
    }

    if (isset($decoded['userfullname']) && is_string($decoded['userfullname'])) {
        $filters->userfullname = $decoded['userfullname'];
    }
    // The response filter's value is a response hash.
    if (isset($decoded['response']) && is_string($decoded['response']) && preg_match('/^[0-9a-f]{40}$/', $decoded['response'])) {
        $filters->response = $decoded['response'];
    }

    foreach (['errortype', 'courseid', 'activity', 'datefrom', 'dateto'] as $name) {
        if (isset($decoded[$name]) && is_numeric($decoded[$name]) && (int) $decoded[$name] >= 0) {
            $filters->$name = (int) $decoded[$name];
        }
    }

    if (isset($decoded['eventnames']) && is_array($decoded['eventnames'])) {
        $names = array_values(array_filter($decoded['eventnames'], function ($name) use ($eventnames) {
            return is_string($name) && isset($eventnames[$name]);
        }));
        if (!empty($names)) {
            $filters->eventnames = $names;
        }
    }

    return $filters;
}

/**
 * SQL conditions that leave out of the historic report the events the store would skip.
 *
 * The store skips events from guests and users who aren't logged in (except CLI scripts) when
 * "Log guest actions" is off, and anonymous events when they are set not to be sent (see
 * store::split_skipped_events()). Skipped events are not recorded as sent, so without these
 * conditions the report would list them again on every visit.
 *
 * @param string $alias The alias of logstore_standard_log in the query.
 * @return array The SQL conditions and their named parameters.
 */
function logstore_xapi_get_historic_skip_conditions(string $alias = 'x'): array {
    global $CFG;

    $where = [];
    $params = [];
    $logguests = get_config('logstore_xapi', 'logguests');
    if ($logguests !== false && empty($logguests)) {
        $where[] = "NOT ($alias.userid = :skipguestid OR ($alias.userid = 0 AND COALESCE($alias.origin, '') <> 'cli'))";
        $params['skipguestid'] = (int) ($CFG->siteguest ?? 0);
    }
    if (get_config('logstore_xapi', 'anonymousevents') === XAPI_ANONYMOUS_EVENTS_SKIP) {
        $where[] = "$alias.anonymous = 0";
    }
    return [$where, $params];
}

/**
 * Decode the json array stored in the response column. Will return false if json is invalid
 *
 * @param string $response JSON string response.
 * @return array|bool
 */
function logstore_xapi_decode_response($response) {
    $decode = json_decode($response, true);
    // Check JSON is valid.
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decode;
    }
    return false;
}

/**
 * Text to show for a stored LRS response in the error report.
 *
 * JSON is pretty-printed. Anything else (cURL errors, plain-text or HTML error pages) is
 * shown as it is. The result is not escaped: the caller must escape it.
 *
 * @param string $response The stored response.
 * @return string
 */
function logstore_xapi_get_response_display(string $response): string {
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $response;
    }
    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT);
}

/**
 * Generate the string for the info column in the report
 *
 * @param object $row
 * @return string
 * @throws coding_exception
 */
function logstore_xapi_get_info_string($row) {
    if (!empty($row->errortype)) {
        switch ($row->errortype) {
            case XAPI_REPORT_ERRORTYPE_NETWORK:
                return get_string('networkerror', 'logstore_xapi');
            case XAPI_REPORT_ERRORTYPE_RECIPE:
                // Recipe issue.
                return get_string('recipeerror', 'logstore_xapi');
            case XAPI_REPORT_ERRORTYPE_AUTH:
                // Unauthorised, could be an issue with xAPI credentials.
                return get_string('autherror', 'logstore_xapi');
            case XAPI_REPORT_ERRORTYPE_LRS:
                // The xAPI server error.
                return get_string('lrserror', 'logstore_xapi');
            case XAPI_REPORT_ERRORTYPE_TRANSFORM:
                // Transform error.
                return get_string('failedtransformresponse', 'logstore_xapi', $row->eventname);
            default:
                // Generic error catch all.
                return get_string('unknownerror', 'logstore_xapi', $row->errortype);
                break;
        }
    }
    return ''; // Return blank if no errortype captured.
}

/**
 * Get successful events.
 *
 * @param array $events An array of events.
 * @return array
 */
function logstore_xapi_get_successful_events($events) {
    $loadedevents = array_filter($events, function ($loadedevent) {
        return $loadedevent['loaded'] === true;
    });
    $successfulevents = array_map(function ($loadedevent) {
        return $loadedevent['event'];
    }, $loadedevents);
    return $successfulevents;
}

/**
 * Extract events from logstore_xapi_log or logstore_xapi_failed_log.
 *
 * @param int $limitnum limit number
 * @param int $log log source
 * @param int $type event type
 * @return array
 */
function logstore_xapi_extract_events($limitnum, $log, $type) {
    global $DB;

    $conditions = ["type" => $type];
    // Oldest first.
    $sort = 'id';
    $fields = '*';
    $limitfrom = 0;

    $events = $DB->get_records($log, $conditions, $sort, $fields, $limitfrom, $limitnum);
    return $events;
}

/**
 * Get event ids.
 *
 * @param array $loadedevents raw events data
 * @return array
 */
function logstore_xapi_get_event_ids($loadedevents) {
    return array_map(function ($loadedevent) {
        return $loadedevent['event']->id;
    }, $loadedevents);
}

/**
 * Delete processed events from the queue, except those left for a later run.
 *
 * @param array $events raw events data
 * @return void
 */
function logstore_xapi_delete_processed_events($events) {
    global $DB;
    $processed = array_filter($events, function ($loadedevent) {
        return $loadedevent['loaded'] !== XAPI_LOADED_RETRY;
    });
    $DB->delete_records_list('logstore_xapi_log', 'id', logstore_xapi_get_event_ids($processed));
}

/**
 * The events the LRS couldn't take now, left in the queue for a later run.
 *
 * @param array $events Loaded events.
 * @return array The events.
 */
function logstore_xapi_get_retry_events(array $events): array {
    return array_values(array_map(function ($loadedevent) {
        return $loadedevent['event'];
    }, array_filter($events, function ($loadedevent) {
        return $loadedevent['loaded'] === XAPI_LOADED_RETRY;
    })));
}

/**
 * Keep the LRS's error, when events are left for a later run, for the status report.
 *
 * The last error is cleared once events are sent again.
 *
 * @param array $events Loaded events.
 * @return \stdClass|null The error (time, code and message), or null when no event was left.
 */
function logstore_xapi_record_retry_events(array $events): ?\stdClass {
    $cache = \cache::make('logstore_xapi', 'senderror');
    $retry = logstore_xapi_get_retry_events($events);
    if (empty($retry)) {
        if (!empty(logstore_xapi_get_successful_events($events))) {
            $cache->delete('last');
        }
        return null;
    }

    $error = (object) [
        'time' => time(),
        'code' => (int) ($retry[0]->errortype ?? 0),
        'message' => \core_text::substr((string) ($retry[0]->response ?? ''), 0, 1000),
    ];
    $cache->set('last', $error);
    return $error;
}

/**
 * A short description of an error from the LRS: the HTTP status or cURL error, and the message.
 *
 * @param \stdClass $error The error: code and message.
 * @return string
 */
function logstore_xapi_get_send_error_string(\stdClass $error): string {
    $message = shorten_text(strip_tags($error->message), 200);
    return $error->code >= 100 ? "HTTP {$error->code}: {$message}" : $message;
}

/**
 * Record the standard log rows of successfully sent events in logstore_xapi_sent_log.
 *
 * The historic report uses this to leave out events that have been sent. One query finds the
 * rows already recorded, and the rest are inserted together. Events that matched no standard
 * log row (id 0) are not recorded.
 *
 * @param array $events Loaded events, as returned by store::process_events().
 * @return void
 */
function logstore_xapi_save_sent_events(array $events) {
    global $DB;

    $types = [];
    foreach (logstore_xapi_get_successful_events($events) as $event) {
        if (!empty($event->logstorestandardlogid)) {
            $types[$event->logstorestandardlogid] = $event->type;
        }
    }
    if (empty($types)) {
        return;
    }

    [$insql, $params] = $DB->get_in_or_equal(array_keys($types));
    $recorded = $DB->get_fieldset_select(
        'logstore_xapi_sent_log',
        'logstorestandardlogid',
        "logstorestandardlogid $insql",
        $params
    );

    $now = time();
    $rows = [];
    foreach (array_diff_key($types, array_flip($recorded)) as $id => $type) {
        $rows[] = (object) ['logstorestandardlogid' => $id, 'type' => $type, 'timecreated' => $now];
    }
    if (!empty($rows)) {
        $DB->insert_records('logstore_xapi_sent_log', $rows);
    }
}

/**
 * Record the outcome of a processed batch and remove it from the queue.
 *
 * The failures are stored and the processed queue rows deleted in one transaction. If either
 * throws, neither happens: the batch stays queued and is sent again on the next run. The LRS
 * ignores the statements it already has, as they are sent with the same ids (see
 * \src\transformer\utils\set_statement_ids()). Events the LRS couldn't take now stay queued.
 *
 * @param array $loadedevents Loaded events, as returned by store::process_events().
 * @return array The number of events sent, failed and left for a later run (sent, failed,
 *     retry), and the LRS's error when events were left (error), or null.
 */
function logstore_xapi_finish_batch(array $loadedevents): array {
    global $DB;

    $transaction = $DB->start_delegated_transaction();
    try {
        logstore_xapi_store_failed_events($loadedevents, false);
        logstore_xapi_delete_processed_events($loadedevents);
        $transaction->allow_commit();
    } catch (\Throwable $e) {
        $transaction->rollback($e);
    }
    return [
        'sent' => count(logstore_xapi_get_successful_events($loadedevents)),
        'failed' => count(logstore_xapi_get_failed_events($loadedevents)),
        'retry' => count(logstore_xapi_get_retry_events($loadedevents)),
        'error' => logstore_xapi_record_retry_events($loadedevents),
    ];
}

/**
 * Send the queued events of one type, batch after batch.
 *
 * Sends until the queue is empty, the LRS can't take statements (the batch stays queued, see
 * \src\loader\utils\retry_later), the time limit for each run passes, or a batch removes
 * nothing from the queue, which would otherwise send the same events again. A summary is
 * printed at the end.
 *
 * @param \logstore_xapi\log\store $store The store.
 * @param int $type The events' type: XAPI_IMPORT_TYPE_LIVE, _FAILED or _HISTORIC.
 * @param int $batchsize The number of events to take from the queue at a time, or 0 for all.
 * @return void
 */
function logstore_xapi_send_queued_events(\logstore_xapi\log\store $store, int $type, int $batchsize): void {
    $timelimit = (int) get_config('logstore_xapi', 'maxruntime');
    $deadline = time() + ($timelimit > 0 ? $timelimit : XAPI_MAX_RUNTIME_DEFAULT);
    $totals = ['sent' => 0, 'failed' => 0, 'retry' => 0, 'error' => null];

    do {
        $events = logstore_xapi_extract_events($batchsize, XAPI_REPORT_SOURCE_LOG, $type);
        if (empty($events)) {
            break;
        }
        $loadedevents = $store->process_events($events, $type);
        $counts = logstore_xapi_finish_batch($loadedevents);
        foreach (['sent', 'failed', 'retry'] as $outcome) {
            $totals[$outcome] += $counts[$outcome];
        }
        $totals['error'] = $counts['error'] ?? $totals['error'];
        $removed = count($loadedevents) - $counts['retry'];
    } while ($counts['retry'] === 0 && $removed > 0 && time() < $deadline);

    mtrace($totals['failed'] . ' ' . get_string('failed_events', 'logstore_xapi'));
    mtrace($totals['sent'] . ' ' . get_string('successful_events', 'logstore_xapi'));
    if ($totals['retry'] > 0) {
        mtrace(get_string('retry_events', 'logstore_xapi', (object) [
            'count' => $totals['retry'],
            'error' => logstore_xapi_get_send_error_string($totals['error']),
        ]));
    }
}

/**
 * Get failed events as array.
 *
 * @param array $events An array of events.
 * @return array
 */
function logstore_xapi_get_failed_events($events) {
    $nonloadedevents = array_filter($events, function ($loadedevent) {
        return $loadedevent['loaded'] === false;
    });
    $failedevents = array_map(function ($nonloadedevent) {
        return $nonloadedevent['event'];
    }, $nonloadedevents);
    return $failedevents;
}

/**
 * Store failed events in logstore_xapi_failed_log.
 *
 * Events are inserted one at a time. insert_records() requires every record to have the same
 * fields, but an event that failed to transform carries only a response while one that failed
 * to load also carries an errortype, so a batch with both would throw.
 *
 * @param array $events An array of events.
 * @param bool $trace Whether to print a summary with mtrace(). Off when called during a web request.
 * @return void
 */
function logstore_xapi_store_failed_events($events, $trace = true) {
    global $DB;

    $failedevents = logstore_xapi_get_failed_events($events);
    foreach ($failedevents as $failedevent) {
        $failedevent->responsehash = logstore_xapi_hash_response($failedevent->response ?? null);
        $DB->insert_record('logstore_xapi_failed_log', $failedevent);
    }
    if ($trace) {
        mtrace(count($failedevents) . " " . get_string('failed_events', 'logstore_xapi'));
    }
}

/**
 * determine the type from the initial base table
 *
 * @param string $table
 * @return int
 */
function logstore_xapi_get_type_from_table($table) {
    switch ($table) {
        case XAPI_REPORT_SOURCE_LOG:
            return XAPI_IMPORT_TYPE_LIVE;
        case XAPI_REPORT_SOURCE_FAILED:
            return XAPI_IMPORT_TYPE_FAILED;
        case XAPI_REPORT_SOURCE_HISTORICAL:
            return XAPI_IMPORT_TYPE_HISTORIC;
        default:
            return XAPI_IMPORT_TYPE_LIVE;
    }
}

/**
 * Status checks contributed to the system status report.
 *
 * Called by \core\check\manager::get_checks('status').
 *
 * @return array of \core\check\check
 */
function logstore_xapi_status_checks() {
    return [
        new \logstore_xapi\check\sending(),
    ];
}

/**
 * Security checks contributed to the site security report.
 *
 * Called by \core\check\manager::get_security_checks().
 *
 * @return array of \core\check\check
 */
function logstore_xapi_security_checks() {
    return [
        new \logstore_xapi\check\ssl_verification(),
        new \logstore_xapi\check\endpoint_https(),
    ];
}
