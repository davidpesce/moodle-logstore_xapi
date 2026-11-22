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

// Client-side statement hardening.
//
// ajax/client_events.php is authenticated (require_login + sesskey), which
// stops CSRF and unauthenticated posts, but any logged-in user knows their
// own sesskey and can craft the POST by hand (curl, devtools, etc.). The
// statement JSON must therefore be treated as untrusted user input: size
// capped before decoding, shape capped after decoding, spoofable/LRS-owned
// fields rejected, and everything bound via the $DB API (no SQL string
// interpolation) so a hostile payload cannot become SQL injection. The same
// payload is later forwarded to the LRS and may surface in reports, so
// over-long strings and control characters are rejected here as well.

// Maximum raw JSON accepted for one client statement (typical H5P
// statements are a few KB; 64KB leaves ample headroom for context/result).
define('XAPI_CLIENT_STATEMENT_MAX_BYTES', 65536);
// Maximum nesting depth / total keys / string lengths, guarding against
// deeply-nested or "billion laughs"-style payloads and DB/TEXT abuse.
define('XAPI_CLIENT_STATEMENT_MAX_DEPTH', 20);
define('XAPI_CLIENT_STATEMENT_MAX_KEYS', 200);
define('XAPI_CLIENT_STATEMENT_MAX_STRING', 8192);
define('XAPI_CLIENT_STATEMENT_MAX_IRI', 2048);
define('XAPI_CLIENT_STATEMENT_MAX_ID', 255);

// Top-level xAPI statement properties a browser may send. 'stored' and
// 'authority' are deliberately absent: they are assigned by the LRS and a
// client supplying them is attempting to spoof LRS state.
define('XAPI_CLIENT_STATEMENT_TOP_LEVEL', [
    'id', 'actor', 'verb', 'object', 'result', 'context', 'timestamp', 'version', 'attachments',
]);

// Object types defined by the xAPI spec.
define('XAPI_CLIENT_STATEMENT_OBJECT_TYPES', [
    'Activity', 'Agent', 'Group', 'StatementRef', 'SubStatement',
]);

// Retry budget for a failed client statement: 4320 one-minute task runs
// (3 days) before retries stop. Retention for sent-statement
// idempotency rows, after which a duplicate re-send is harmless.
define('XAPI_CLIENT_QUEUE_MAX_ATTEMPTS', 4320);
define('XAPI_CLIENT_SENT_RETENTION_SECS', 90 * DAYSECS);

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

// The "loaded" state of an event the LRS fails on (a server error) when it is sent alone, found
// by splitting a batch that keeps failing (see logstore_xapi_finish_batch()).
define('XAPI_LOADED_STUCK', 'stuck');

// The number of runs in a row the LRS may fail on (a server error) the batch at the head of the
// queue before the batch is split to find the events it fails on.
define('XAPI_MAX_SEND_ATTEMPTS', 5);

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
define('XAPI_REPORT_ERRORTYPE_DEFAULT', '0');
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
 * The error type and response filters are kept only for the error report: the
 * historic report's table has neither column.
 *
 * @param string $onpage The JSON-encoded filters.
 * @param array $eventnames The supported event names, as keys.
 * @param int $reportid The report the filters are for (XAPI_REPORT_ID_ERROR or XAPI_REPORT_ID_HISTORIC).
 * @return \stdClass The filters that were valid.
 */
function logstore_xapi_clean_report_filters(string $onpage, array $eventnames, int $reportid = XAPI_REPORT_ID_ERROR) {
    $filters = new \stdClass();
    $decoded = json_decode($onpage, true);
    if (!is_array($decoded)) {
        return $filters;
    }
    $errorreport = $reportid == XAPI_REPORT_ID_ERROR;

    if (isset($decoded['userfullname']) && is_string($decoded['userfullname'])) {
        $filters->userfullname = $decoded['userfullname'];
    }
    // The response filter's value is a response hash.
    if (
        $errorreport && isset($decoded['response']) && is_string($decoded['response'])
        && preg_match('/^[0-9a-f]{40}$/', $decoded['response'])
    ) {
        $filters->response = $decoded['response'];
    }

    $numeric = $errorreport ? ['errortype'] : [];
    foreach ([...$numeric, 'courseid', 'activity', 'datefrom', 'dateto'] as $name) {
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
        }
    }
    return ''; // Return blank if no errortype captured.
}

/**
 * The conditions of the error or historic report, from its filters.
 *
 * The conditions are on the report's table, aliased x, joined to the user table, aliased u.
 *
 * @param int $reportid XAPI_REPORT_ID_ERROR or XAPI_REPORT_ID_HISTORIC.
 * @param \stdClass|null $filters The report's filters, as the filter form returns them.
 * @param array $eventnames The events to include when the filters don't choose any.
 * @return array The SQL conditions, and their parameters.
 */
function logstore_xapi_get_report_where(int $reportid, ?\stdClass $filters, array $eventnames): array {
    global $DB;

    $where = ['1 = 1'];
    $params = [];
    $filters = $filters ?? new \stdClass();

    if (!empty($filters->userfullname)) {
        $userfullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $where[] = $DB->sql_like($userfullname, ':userfullname', false, false);
        $params['userfullname'] = '%' . $DB->sql_like_escape($filters->userfullname) . '%';
    }

    if (!empty($filters->errortype)) {
        $where[] = 'x.errortype = :errortype';
        $params['errortype'] = $filters->errortype;
    }

    if (!empty($filters->courseid)) {
        // Course-wide: events in the course and its activities (indexed in core by courseid).
        $where[] = 'x.courseid = :courseid';
        $params['courseid'] = $filters->courseid;
    }

    if (!empty($filters->activity)) {
        $where[] = 'x.contextid = :activity';
        $params['activity'] = $filters->activity;
    }

    if (!empty($filters->eventnames)) {
        $eventnames = (array) $filters->eventnames;
    }

    if (!empty($filters->response)) {
        $where[] = 'x.responsehash = :response';
        $params['response'] = $filters->response;
    }

    if (!empty($filters->datefrom)) {
        $where[] = 'x.timecreated >= :datefrom';
        $params['datefrom'] = $filters->datefrom;
    }

    if (!empty($filters->dateto)) {
        // Set it to end of the day.
        $where[] = 'x.timecreated <= :dateto';
        $params['dateto'] = $filters->dateto + (DAYSECS - 1);
    }

    // With no events to offer (no routes enabled), match nothing rather than throw.
    [$insql, $inparams] = $DB->get_in_or_equal(array_values($eventnames), SQL_PARAMS_NAMED, 'evt', true, '');
    $where[] = "x.eventname $insql";
    $params = array_merge($params, $inparams);

    if ($reportid == XAPI_REPORT_ID_HISTORIC) {
        $where[] = "NOT EXISTS (SELECT 1 FROM {logstore_xapi_sent_log} lxsl WHERE lxsl.logstorestandardlogid = x.id)";
        [$skipwhere, $skipparams] = logstore_xapi_get_historic_skip_conditions('x');
        $where = array_merge($where, $skipwhere);
        $params = array_merge($params, $skipparams);
    }

    return [implode(' AND ', $where), $params];
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
 * The events the LRS couldn't take now, left in the queue for a later run, and the stuck events
 * moved to the end of the queue.
 *
 * @param array $events Loaded events.
 * @return array The events.
 */
function logstore_xapi_get_retry_events(array $events): array {
    return array_values(array_map(function ($loadedevent) {
        return $loadedevent['event'];
    }, array_filter($events, function ($loadedevent) {
        return in_array($loadedevent['loaded'], [XAPI_LOADED_RETRY, XAPI_LOADED_STUCK], true);
    })));
}

/**
 * Decide what happens to the events the LRS fails on alone (a server error), found by splitting a
 * batch it kept failing on.
 *
 * When the LRS took other statements in the same run, it works, and the fault is in these
 * events: they fail, and go to the failed log. Otherwise the LRS may be down, and they stay
 * stuck, to be moved to the end of the queue (see logstore_xapi_requeue_stuck_events()), so the
 * events behind them can be sent.
 *
 * @param array $loadedevents Loaded events.
 * @param bool $sentbefore Whether events were sent earlier in the same run.
 * @return array The loaded events.
 */
function logstore_xapi_resolve_stuck_events(array $loadedevents, bool $sentbefore): array {
    if ($sentbefore || !empty(logstore_xapi_get_successful_events($loadedevents))) {
        foreach ($loadedevents as $key => $loadedevent) {
            if ($loadedevent['loaded'] === XAPI_LOADED_STUCK) {
                $loadedevents[$key]['loaded'] = false;
            }
        }
    }
    return $loadedevents;
}

/**
 * Move stuck events to the end of the queue, where they are tried again after the events behind
 * them. Their queue rows are deleted with the processed events.
 *
 * @param array $loadedevents Loaded events.
 * @return void
 */
function logstore_xapi_requeue_stuck_events(array $loadedevents): void {
    global $DB;
    foreach ($loadedevents as $loadedevent) {
        if ($loadedevent['loaded'] === XAPI_LOADED_STUCK) {
            $event = clone $loadedevent['event'];
            unset($event->id);
            $event->attempts = (int) ($event->attempts ?? 0) + 1;
            $DB->insert_record('logstore_xapi_log', $event);
        }
    }
}

/**
 * Count a run in which the LRS failed on these events with a server error (5xx), for the events
 * left in the queue. Errors that say nothing about the events (no connection, refused
 * credentials, too many requests) aren't counted.
 *
 * @param array $loadedevents Loaded events.
 * @return void
 */
function logstore_xapi_count_send_attempts(array $loadedevents): void {
    global $DB;
    $left = array_filter($loadedevents, function ($loadedevent) {
        return $loadedevent['loaded'] === XAPI_LOADED_RETRY;
    });
    $servererror = array_filter($loadedevents, function ($loadedevent) {
        $code = (int) ($loadedevent['event']->errortype ?? 0);
        return $code >= 500 && $code < 600;
    });
    if (empty($left) || empty($servererror)) {
        return;
    }
    [$insql, $params] = $DB->get_in_or_equal(logstore_xapi_get_event_ids($left));
    $DB->execute("UPDATE {logstore_xapi_log} SET attempts = attempts + 1 WHERE id $insql", $params);
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
 * Events the LRS fails on alone are resolved first (see logstore_xapi_resolve_stuck_events()),
 * and a server error is counted against the events left in the queue.
 *
 * @param array $loadedevents Loaded events, as returned by store::process_events().
 * @param bool $sentbefore Whether events were sent earlier in the same run.
 * @return array The number of events sent, failed and left for a later run (sent, failed,
 *     retry), and the LRS's error when events were left (error), or null.
 */
function logstore_xapi_finish_batch(array $loadedevents, bool $sentbefore = false): array {
    global $DB;

    $loadedevents = logstore_xapi_resolve_stuck_events($loadedevents, $sentbefore);
    $transaction = $DB->start_delegated_transaction();
    try {
        logstore_xapi_store_failed_events($loadedevents, false);
        logstore_xapi_requeue_stuck_events($loadedevents);
        logstore_xapi_delete_processed_events($loadedevents);
        logstore_xapi_count_send_attempts($loadedevents);
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
 * A batch the LRS has failed on with a server error in XAPI_MAX_SEND_ATTEMPTS runs is split, to
 * find the events it fails on, so that one event can't hold up the queue for good.
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
        $split = (int) (reset($events)->attempts ?? 0) >= XAPI_MAX_SEND_ATTEMPTS;
        $loadedevents = $store->process_events($events, $type, $split);
        $counts = logstore_xapi_finish_batch($loadedevents, $totals['sent'] > 0);
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
 * Check whether a value looks like an absolute IRI.
 *
 * Mirrors the RFC 3986 scheme grammar: a letter followed by letters, digits,
 * '+', '-' or '.', then a colon and at least one additional non-whitespace
 * character. It does not attempt full RFC 3987 parsing. Length is capped so
 * a hostile client cannot smuggle megabytes inside a single IRI field.
 *
 * @param mixed $value Value to check.
 * @return bool
 */
function logstore_xapi_is_valid_iri($value) {
    return is_string($value) &&
        strlen($value) <= XAPI_CLIENT_STATEMENT_MAX_IRI &&
        strpos($value, "\0") === false &&
        (bool) preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:[^\s]+$/', $value);
}

/**
 * Validate raw client statement JSON before it is decoded.
 *
 * Must run before json_decode so a single huge POST cannot exhaust memory.
 * Guards against the "SQL injection equivalent" here too: the payload is
 * never interpolated into SQL (all writes go through $DB->insert_record
 * with bound parameters), but an uncapped TEXT value is still a DoS and
 * truncation vector, so it is rejected up front.
 *
 * @param mixed $statementjson Raw POST value.
 * @param string|null $error Set to a validation error when invalid.
 * @return bool
 */
function logstore_xapi_validate_client_statement_json($statementjson, &$error = null) {
    $error = null;
    if (!is_string($statementjson) || $statementjson === '') {
        $error = get_string('clientpayloadrequired', 'logstore_xapi');
        return false;
    }
    if (strpos($statementjson, "\0") !== false) {
        $error = get_string('clientpayloadchar', 'logstore_xapi');
        return false;
    }
    if (strlen($statementjson) > XAPI_CLIENT_STATEMENT_MAX_BYTES) {
        $error = get_string('clientpayloadsize', 'logstore_xapi');
        return false;
    }
    return true;
}

/**
 * Recursively cap the shape of a decoded statement.
 *
 * Limits nesting depth, total key count, key names and string values. This
 * blocks deeply-nested decoding bombs, key-flooding, null-byte smuggling
 * and control-character log/report injection in one pass.
 *
 * @param mixed $node Current node.
 * @param string|null $error Set to a validation error when invalid.
 * @param int $depth Current depth.
 * @param int $keycount Running total of array keys seen.
 * @return bool
 */
function logstore_xapi_check_client_statement_shape($node, &$error = null, $depth = 0, &$keycount = 0) {
    if ($depth > XAPI_CLIENT_STATEMENT_MAX_DEPTH) {
        $error = get_string('clientdepth', 'logstore_xapi');
        return false;
    }
    if (is_array($node)) {
        $keycount += count($node);
        if ($keycount > XAPI_CLIENT_STATEMENT_MAX_KEYS) {
            $error = get_string('clientmanyfields', 'logstore_xapi');
            return false;
        }
        foreach ($node as $key => $value) {
            if (!is_int($key) && (!is_string($key) || $key === '' || strlen($key) > 128 ||
                    preg_match('/[\x00-\x1F\x7F]/', $key))) {
                $error = get_string('clientfieldname', 'logstore_xapi');
                return false;
            }
            if (!logstore_xapi_check_client_statement_shape($value, $error, $depth + 1, $keycount)) {
                return false;
            }
        }
        return true;
    }
    if (is_string($node)) {
        if (strlen($node) > XAPI_CLIENT_STATEMENT_MAX_STRING) {
            $error = get_string('clientvaluetoolong', 'logstore_xapi');
            return false;
        }
        if (strpos($node, "\0") !== false ||
                preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $node)) {
            $error = get_string('clientvaluechar', 'logstore_xapi');
            return false;
        }
        return true;
    }
    if (is_null($node) || is_bool($node) || is_int($node) || is_float($node)) {
        if (is_float($node) && !is_finite($node)) {
            $error = get_string('clientnumber', 'logstore_xapi');
            return false;
        }
        return true;
    }
    $error = get_string('clientvalue', 'logstore_xapi');
    return false;
}

/**
 * Validate an ISO 8601 timestamp as used by xAPI.
 *
 * @param mixed $value Value to check.
 * @return bool
 */
function logstore_xapi_is_valid_statement_timestamp($value) {
    if (!is_string($value) || strlen($value) > 64 || strpos($value, "\0") !== false) {
        return false;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})?$/', $value)) {
        return false;
    }
    return strtotime($value) !== false;
}

/**
 * Validate the subset of an xAPI statement required by the client queue.
 *
 * The statement arrives from the browser, and any authenticated user can
 * forge it (sesskey proves the request came from their own session, not
 * that the H5P content produced it). Validation is therefore hostile-input
 * validation, not just well-formedness: size/shape caps, an allow-list of
 * top-level fields, rejection of LRS-owned fields (stored/authority),
 * IRI checks, and range checks on scores.
 *
 * Note: the actor is intentionally NOT trusted here. ajax/client_events.php
 * overwrites it with the authenticated user after validation.
 *
 * @param mixed $statement Decoded statement data.
 * @param string|null $error Set to a validation error when invalid.
 * @param string|null $statementjson Optional raw JSON for a size pre-check.
 * @return bool
 */
function logstore_xapi_validate_client_statement($statement, &$error = null, $statementjson = null) {
    $error = null;

    if ($statementjson !== null && !logstore_xapi_validate_client_statement_json($statementjson, $error)) {
        return false;
    }

    if (!is_array($statement)) {
        $error = get_string('clientnotobject', 'logstore_xapi');
        return false;
    }

    // Unknown top-level members carry no meaning for the queue and only
    // enlarge the LRS payload / stored row, so reject them. LRS-owned
    // members are rejected explicitly below with a clearer message.
    foreach (array_keys($statement) as $key) {
        if ($key === 'stored' || $key === 'authority') {
            $error = get_string('clientlrsfield', 'logstore_xapi');
            return false;
        }
        if (!in_array($key, XAPI_CLIENT_STATEMENT_TOP_LEVEL, true)) {
            $error = get_string('clienttopfield', 'logstore_xapi');
            return false;
        }
    }

    if (empty($statement['actor']) || !is_array($statement['actor'])) {
        $error = get_string('clientactor', 'logstore_xapi');
        return false;
    }

    if (empty($statement['verb']) || !is_array($statement['verb']) ||
            empty($statement['verb']['id']) || !logstore_xapi_is_valid_iri($statement['verb']['id'])) {
        $error = get_string('clientverb', 'logstore_xapi');
        return false;
    }

    if (empty($statement['object']) || !is_array($statement['object'])) {
        $error = get_string('clientobject', 'logstore_xapi');
        return false;
    }

    // Activity objects carry an IRI id. Statement references, sub-statements
    // and other xAPI object types identify themselves by objectType and may
    // carry a non-IRI id, so the IRI check only applies to plain activities.
    $objecttype = $statement['object']['objectType'] ?? null;
    if ($objecttype !== null &&
            (!is_string($objecttype) || !in_array($objecttype, XAPI_CLIENT_STATEMENT_OBJECT_TYPES, true))) {
        $error = get_string('clientobjecttype', 'logstore_xapi');
        return false;
    }
    if (isset($statement['object']['id'])) {
        if (!is_string($statement['object']['id']) || $statement['object']['id'] === '' ||
                strlen($statement['object']['id']) > XAPI_CLIENT_STATEMENT_MAX_IRI) {
            $error = get_string('clientobjectid', 'logstore_xapi');
            return false;
        }
        if (($objecttype === null || $objecttype === 'Activity') &&
                !logstore_xapi_is_valid_iri($statement['object']['id'])) {
            $error = get_string('clientobjectidiri', 'logstore_xapi');
            return false;
        }
    } else if ($objecttype === null) {
        $error = get_string('clientobjectidor', 'logstore_xapi');
        return false;
    }

    if (isset($statement['id'])) {
        if (!is_string($statement['id']) || $statement['id'] === '' ||
                strlen($statement['id']) > XAPI_CLIENT_STATEMENT_MAX_ID ||
                strpos($statement['id'], "\0") !== false ||
                preg_match('/[\x00-\x1F\x7F\s]/', $statement['id'])) {
            $error = get_string('clientstatementid', 'logstore_xapi');
            return false;
        }
    }

    if (isset($statement['timestamp']) && !logstore_xapi_is_valid_statement_timestamp($statement['timestamp'])) {
        $error = get_string('clienttimestamp', 'logstore_xapi');
        return false;
    }

    if (isset($statement['version'])) {
        if (!is_string($statement['version']) || strlen($statement['version']) > 16 ||
                !preg_match('/^1\.0\.\d+$/', $statement['version'])) {
            $error = get_string('clientversion', 'logstore_xapi');
            return false;
        }
    }

    $keycount = 0;
    if (!logstore_xapi_check_client_statement_shape($statement, $error, 0, $keycount)) {
        return false;
    }

    return true;
}

/**
 * Derive the course for a client-side statement and strip untrusted claims.
 *
 * sender.js hints the course via context.contextActivities.grouping, but that
 * value comes from the browser and can be forged to claim any course. Keep
 * only a grouping entry that points at a course on this site which the user
 * can access, and return its id so the queue row carries a server-verified
 * course. Anything else is removed so an unverified course association is
 * not forwarded to the LRS.
 *
 * A verified claim is enriched with the same standard course activity the
 * server-side transformer emits (cmi5 course type, localised name, and the
 * short-id/external-id extensions only when the admin enables them), so
 * client and server statements describe the course identically.
 *
 * @param array $statement Decoded statement, updated in place.
 * @param stdClass $user Authenticated user.
 * @return int|null Verified course id, or null when no claim verifies.
 */
function logstore_xapi_resolve_client_statement_course(array &$statement, $user) {
    global $CFG, $DB;

    $grouping = $statement['context']['contextActivities']['grouping'] ?? null;
    if ($grouping === null) {
        return null;
    }
    $entries = is_array($grouping) && array_key_exists('id', $grouping) ? [$grouping] : (array)$grouping;

    $base = preg_quote($CFG->wwwroot, '#');
    foreach ($entries as $entry) {
        if (!is_array($entry) || empty($entry['id']) || !is_string($entry['id'])) {
            continue;
        }
        if (!preg_match('#^' . $base . '/course/view\.php\?id=(\d+)$#', $entry['id'], $matches)) {
            continue;
        }
        $courseid = (int)$matches[1];
        if ($courseid <= 0) {
            continue;
        }
        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course || !can_access_course($course, $user)) {
            continue;
        }
        // Replace the browser's bare URL with the standard course activity,
        // mirroring classes/log/store.php so both statement sources agree.
        require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');
        $activityconfig = [
            'app_url' => $CFG->wwwroot,
            'send_short_course_id' => get_config('logstore_xapi', 'shortcourseid'),
            'send_course_and_module_idnumber' => get_config('logstore_xapi', 'sendidnumber'),
        ];
        $statement['context']['contextActivities']['grouping'] =
            [\src\transformer\utils\get_activity\course($activityconfig, $course)];
        return $courseid;
    }

    // No verifiable course claim: remove the grouping so it is not forwarded.
    unset($statement['context']['contextActivities']['grouping']);
    if (empty($statement['context']['contextActivities'])) {
        unset($statement['context']['contextActivities']);
    }
    if (empty($statement['context'])) {
        unset($statement['context']);
    }
    return null;
}

/**
 * Queue a client-side xAPI statement unless it has already been queued or sent.
 *
 * Defence in depth: the caller (ajax/client_events.php) already validates,
 * but this re-checks sizes and scalar bindings so a future caller cannot
 * turn the queue into a truncation/SQL-injection point. All writes use
 * $DB->insert_record with bound parameters; no statement content is ever
 * interpolated into SQL.
 *
 * @param array $statement Decoded xAPI statement.
 * @param string $statementjson Original JSON representation.
 * @param int $userid Authenticated Moodle user id.
 * @param int $contextid Moodle context id.
 * @param int|null $courseid Optional course id.
 * @param string|null $ip Request IP address.
 * @return array Queue result.
 */
function logstore_xapi_queue_client_statement(array $statement, $statementjson, $userid, $contextid,
        $courseid = null, $ip = null) {
    global $DB;

    $validationerror = null;
    if (!logstore_xapi_validate_client_statement($statement, $validationerror, $statementjson)) {
        throw new coding_exception('Invalid client statement: ' . $validationerror);
    }

    $userid = (int)$userid;
    $contextid = (int)$contextid;
    if ($userid <= 0 || $contextid <= 0) {
        throw new coding_exception('Invalid user or context for client statement.');
    }

    $statementid = isset($statement['id']) ? (string)$statement['id'] : null;
    if ($statementid !== null && strlen($statementid) > XAPI_CLIENT_STATEMENT_MAX_ID) {
        throw new coding_exception('Invalid client statement id.');
    }

    // Bound to a char(45) column; keep only a valid IP (or nothing) so a
    // forged header cannot smuggle control characters into the log.
    $iprecord = null;
    if (is_string($ip) && $ip !== '') {
        $ip = trim(substr($ip, 0, 45));
        if (strpos($ip, "\0") === false && !preg_match('/[\x00-\x1F\x7F]/', $ip)) {
            $iprecord = $ip;
        }
    }

    $clientkey = hash('sha256', $statementid !== null ? 'id:' . $statementid : 'json:' . $statementjson);

    if ($DB->record_exists('logstore_xapi_client_log', ['clientkey' => $clientkey]) ||
            $DB->record_exists('logstore_xapi_client_sent', ['clientkey' => $clientkey])) {
        return ['queued' => false, 'duplicate' => true, 'id' => 0];
    }

    $record = (object) [
        'statement' => $statementjson,
        'clientkey' => $clientkey,
        'statementid' => $statementid,
        'userid' => $userid,
        'contextid' => $contextid,
        'courseid' => $courseid === null ? null : (int)$courseid,
        'timecreated' => time(),
        'ip' => $iprecord,
        'type' => XAPI_IMPORT_TYPE_LIVE,
        'attempts' => 0,
    ];

    try {
        $id = $DB->insert_record('logstore_xapi_client_log', $record);
    } catch (\dml_exception $exception) {
        // The unique client key also protects against two concurrent browser
        // requests for the same statement.
        if ($DB->record_exists('logstore_xapi_client_log', ['clientkey' => $clientkey]) ||
                $DB->record_exists('logstore_xapi_client_sent', ['clientkey' => $clientkey])) {
            return ['queued' => false, 'duplicate' => true, 'id' => 0];
        }
        throw $exception;
    }

    return [
        'queued' => true,
        'duplicate' => false,
        'id' => $id,
    ];
}

/**
 * Status checks contributed to the system status report.
 *
 * Called by \\core\\check\\manager::get_checks('status').
 *
 * @return array of \\core\\check\\check
 */
function logstore_xapi_status_checks() {
    return [
        new \logstore_xapi\check\sending(),
    ];
}

/**
 * Extract client statements awaiting processing.
 *
 * Ordered by attempts so rows that already burned retries (including
 * exhausted ones past the attempt cap) sort last and cannot crowd
 * retryable rows out of the batch window.
 *
 * @param int $limitnum Maximum number of records.
 * @param int $type Client queue type.
 * @return array
 */
function logstore_xapi_extract_client_events($limitnum, $type) {
    global $DB;

    return $DB->get_records('logstore_xapi_client_log', ['type' => $type], 'attempts ASC, id ASC', '*', 0, $limitnum);
}

/**
 * Add a successfully sent client statement to the idempotency log.
 *
 * @param stdClass $event Client queue record.
 * @return void
 */
function logstore_xapi_add_client_event_to_sent_log($event) {
    global $DB;

    if (!$DB->record_exists('logstore_xapi_client_sent', ['clientkey' => $event->clientkey])) {
        $DB->insert_record('logstore_xapi_client_sent', (object) [
            'clientkey' => $event->clientkey,
            'statementid' => $event->statementid,
            'userid' => $event->userid,
            'contextid' => $event->contextid,
            'timecreated' => time(),
        ]);
    }
}

/**
 * Mark a client statement as failed so the failed queue task can retry it.
 *
 * @param stdClass $event Client queue record with error information.
 * @return void
 */
function logstore_xapi_update_client_event_failure($event) {
    global $DB;

    $record = (object) [
        'id' => $event->id,
        'type' => XAPI_IMPORT_TYPE_FAILED,
        'attempts' => ((int)($event->attempts ?? 0)) + 1,
        'errortype' => $event->errortype ?? XAPI_REPORT_ERRORTYPE_LRS,
        'response' => $event->response ?? '',
    ];
    $DB->update_record('logstore_xapi_client_log', $record);
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
