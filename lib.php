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
// parameter and has to be interpolated into the SQL.
define('XAPI_REPORT_FILTER_COLUMNS', ['errortype', 'response']);

// Error types.
define('XAPI_REPORT_ERRORTYPE_NETWORK', 101);
define('XAPI_REPORT_ERRORTYPE_RECIPE', 400);
define('XAPI_REPORT_ERRORTYPE_AUTH', 401);
define('XAPI_REPORT_ERRORTYPE_LRS', 500);
define('XAPI_REPORT_ERRORTYPE_TRANSFORM', 10000); // This high number has been set to avoid conflicting with other error codes.

// Resend values.
define('XAPI_REPORT_RESEND_FALSE', 0);
define('XAPI_REPORT_RESEND_TRUE', 1);

// Default values on url parameters.
define('XAPI_REPORT_STARTING_PAGE', 0);
define('XAPI_REPORT_PERPAGE_DEFAULT', 40);
define('XAPI_REPORT_PERPAGE_MAX', 500);
define('XAPI_REPORT_ONPAGE_DEFAULT', '');
define('XAPI_REPORT_EVENTCONTEXT_DEFAULT', '');
define('XAPI_REPORT_EVENTNAMES_DEFAULT', []);
define('XAPI_REPORT_ERROTYPE_DEFAULT', '0');
define('XAPI_REPORT_RESPONSE_DEFAULT', '0');
define('XAPI_REPORT_USERNAME_DEFAULT', '');
define('XAPI_REPORT_DATEFROM_DEFAULT', 0);
define('XAPI_REPORT_DATETO_DEFAULT', 0);

// Events whose statements carry the text of private messages or of notes about
// a user. They are left out of the default routes so that a new install does
// not copy that text to the LRS unless an administrator chooses to.
define('XAPI_ROUTES_OFF_BY_DEFAULT', [
    '\\core\\event\\group_message_sent',
    '\\core\\event\\message_sent',
    '\\core\\event\\message_viewed',
    '\\core\\event\\note_created',
    '\\core\\event\\note_updated',
    '\\core\\event\\notes_viewed',
]);

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
            // The response column holds raw LRS error bodies, and core's select template prints
            // option labels unescaped, so escape (and shorten) the label. The value stays raw.
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
 * Get the available context's from the logstore standard log table
 *
 * @return array
 * @throws dml_exception
 */
function logstore_xapi_get_logstore_standard_context_options() {
    global $DB;

    $options = [0 => get_string('any')];

    $sql = 'SELECT DISTINCT(contextid)
              FROM {logstore_standard_log} lssl
             WHERE EXISTS (SELECT 1
                             FROM {context} c
                            WHERE c.id = lssl.contextid)';
    $contextids = array_keys($DB->get_records_sql($sql));

    foreach ($contextids as $contextid) {
        $context = context::instance_by_id($contextid);
        $options[$context->id] = $context->get_context_name();
    }
    asort($options);

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

    foreach (['userfullname', 'response'] as $name) {
        if (isset($decoded[$name]) && is_string($decoded[$name])) {
            $filters->$name = $decoded[$name];
        }
    }

    foreach (['errortype', 'eventcontext', 'datefrom', 'dateto'] as $name) {
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
 * Take event data and add to the sent log if it doesn't exist already.
 *
 * @param stdObj $event raw event data
 * @return void
 */
function logstore_xapi_add_event_to_sent_log($event) {
    global $DB;

    $row = $DB->get_record('logstore_xapi_sent_log', ['logstorestandardlogid' => $event->logstorestandardlogid]);
    if (empty($row)) {
        $newrow = new stdClass();
        $newrow->logstorestandardlogid = $event->logstorestandardlogid;
        $newrow->type = $event->type;
        $newrow->timecreated = time();
        $DB->insert_record('logstore_xapi_sent_log', $newrow);
    }
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
    $sort = '';
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
 * Delete processed events.
 *
 * @param array $events raw events data
 * @return void
 */
function logstore_xapi_delete_processed_events($events) {
    global $DB;
    $eventids = logstore_xapi_get_event_ids($events);
    $DB->delete_records_list('logstore_xapi_log', 'id', $eventids);
}

/**
 * Log the number of events using mtrace.
 *
 * @param array $events raw events data
 * @return void
 */
function logstore_xapi_record_successful_events($events) {
    mtrace(count(logstore_xapi_get_successful_events($events)) . " " . get_string('successful_events', 'logstore_xapi'));
}

/**
 * Take successful events and save each using logstore_xapi_add_event_to_sent_log.
 *
 * @param array $events raw events data
 * @return void
 */
function logstore_xapi_save_sent_events(array $events) {
    $successfulevents = logstore_xapi_get_successful_events($events);
    foreach ($successfulevents as $event) {
        logstore_xapi_add_event_to_sent_log($event);
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
 * Security checks contributed to the site security report.
 *
 * Called by \core\check\manager::get_security_checks().
 *
 * @return array of \core\check\check
 */
function logstore_xapi_security_checks() {
    return [
        new \logstore_xapi\check\ssl_verification(),
    ];
}
