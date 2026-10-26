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
 * Reporting view.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\notification;

require_once(__DIR__ . '/../../../../../config.php');
require_once($CFG->dirroot . '/lib/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

// Set context.
$systemcontext = context_system::instance();

// Add require login and only admin allowed to see this page.
require_login(null, false);

// Read parameters.
$id           = optional_param('id', XAPI_REPORT_ID_ERROR, PARAM_INT); // This is the report ID.
$run          = optional_param('run', false, PARAM_BOOL);
$page         = max(0, optional_param('page', XAPI_REPORT_STARTING_PAGE, PARAM_INT));
$perpage      = optional_param('perpage', XAPI_REPORT_PERPAGE_DEFAULT, PARAM_INT);
// A limit of 0 would fetch every row, so keep the page size within bounds.
$perpage      = min(max(1, $perpage), XAPI_REPORT_PERPAGE_MAX);
$onpage       = optional_param('onpage', XAPI_REPORT_ONPAGE_DEFAULT, PARAM_TEXT);

// Check access before doing any work: building the filter options scans the log tables.
switch ($id) {
    case XAPI_REPORT_ID_ERROR:
        require_capability('logstore/xapi:viewerrorlog', $systemcontext);
        break;
    case XAPI_REPORT_ID_HISTORIC:
        require_capability('logstore/xapi:managehistoric', $systemcontext);
        break;
    default:
        throw new moodle_exception('invalidreportid', 'logstore_xapi');
}

if ($id == XAPI_REPORT_ID_ERROR) {
    $run = true;
}

// Set pagination url's parameter.

$urlparams = [
    'id' => $id,
    'run' => $run,
    'page' => $page,
    'perpage' => $perpage,
    'onpage' => $onpage,
];

$url = new moodle_url('/admin/tool/log/store/xapi/report.php', ['id' => $id]);

// Set page parameters.
$PAGE->set_context($systemcontext);
$PAGE->set_url($url);

// Set filter params and defaults.
$eventnames = logstore_xapi_get_event_names_array();
if ($id == XAPI_REPORT_ID_HISTORIC) {
    // Offer only the events the admin has chosen to send.
    $routes = array_flip(explode(',', get_config('logstore_xapi', 'routes') ?: ''));
    $eventnames = array_intersect_key($eventnames, $routes);
}

$defaults = [
    'datefrom' => XAPI_REPORT_DATEFROM_DEFAULT,
    'dateto' => XAPI_REPORT_DATETO_DEFAULT,
    'eventcontext' => XAPI_REPORT_EVENTCONTEXT_DEFAULT,
    'eventnames' => XAPI_REPORT_EVENTNAMES_DEFAULT,
    'errortype' => XAPI_REPORT_ERROTYPE_DEFAULT,
    'resend' => XAPI_REPORT_RESEND_FALSE,
    'response' => XAPI_REPORT_RESPONSE_DEFAULT,
    'userfullname' => XAPI_REPORT_USERNAME_DEFAULT,
];

// Reread submitted params.
if (!empty($onpage)) {
    $formelements = logstore_xapi_clean_report_filters($onpage, $eventnames);

    foreach (array_keys($defaults) as $element) {
        if (isset($formelements->$element) && !empty($formelements->$element)) {
            $defaults[$element] = $formelements->$element;
        }
    }
}

$filterparams = [
    'defaults' => $defaults,
    'reportid' => $id,
    'eventnames' => $eventnames,
];

// Parameter settings depends on report id.
$basetable = XAPI_REPORT_SOURCE_FAILED;
$extraselect = 'x.errortype, x.response';
$pagename = 'logstorexapierrorlog';
$canmanage = false;

switch ($id) {
    case XAPI_REPORT_ID_ERROR:
        $filterparams['errortypes'] = logstore_xapi_get_distinct_options_from_failed_table('errortype');
        $filterparams['responses'] = logstore_xapi_get_distinct_options_from_failed_table('response');

        if (has_capability('logstore/xapi:manageerrors', $systemcontext)) {
            $canmanage = true;
        }
        break;

    case XAPI_REPORT_ID_HISTORIC:
        $basetable = XAPI_REPORT_SOURCE_HISTORICAL;
        $extraselect = 'u.username, x.contextid';
        $pagename = 'logstorexapihistoriclog';
        if ($run) {
            $filterparams['eventcontexts'] = logstore_xapi_get_logstore_standard_context_options();
        } else {
            $filterparams['eventcontexts'] = [];
        }
        $canmanage = true;

        break;

    default:
        // Unreachable: unknown report ids are rejected by the access check at the top.
        throw new moodle_exception('invalidreportid', 'logstore_xapi');
}

$notifications = [];
$mform = new logstore_xapi\form\reportfilter_form($url, $filterparams);

$params = [];
$where = ['1 = 1'];

// If we have submitted data overwrite form elements from form.
if ($fromform = $mform->get_data()) {
    // Set onpage because moodle_url function is not handling arrays.
    $formelements = clone $fromform;

    unset($formelements->resend, $formelements->submitbutton);

    $urlparams['onpage'] = json_encode($formelements);
}

if (isset($formelements)) {
    if (!empty($formelements->userfullname)) {
        $userfullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $where[] = $DB->sql_like($userfullname, ':userfullname', false, false);
        $params['userfullname'] = '%' . $DB->sql_like_escape($formelements->userfullname) . '%';
    }

    if (!empty($formelements->errortype)) {
        $where[] = 'x.errortype = :errortype';
        $params['errortype'] = $formelements->errortype;
    }

    if (!empty($formelements->eventcontext)) {
        $where[] = 'x.contextid = :eventcontext';
        $params['eventcontext'] = $formelements->eventcontext;
    }

    if (!empty($formelements->eventnames)) {
        $eventnames = $formelements->eventnames;
    }

    if (!empty($formelements->response)) {
        $where[] = 'x.response = :response';
        $params['response'] = $formelements->response;
    }

    if (!empty($formelements->datefrom)) {
        $where[] = 'x.timecreated >= :datefrom';
        $params['datefrom'] = $formelements->datefrom;
    }

    if (!empty($formelements->dateto)) {
        // Set it to end of the day.
        $where[] = 'x.timecreated <= :dateto';
        $params['dateto'] = $formelements->dateto + (DAYSECS - 1);
    }
}

if ($run) {
    // With no events to offer (no routes enabled), match nothing rather than throw.
    [$insql, $inparams] = $DB->get_in_or_equal($eventnames, SQL_PARAMS_NAMED, 'evt', true, '');
    $where[] = "x.eventname $insql";
    $params = array_merge($params, $inparams);

    if ($id == XAPI_REPORT_ID_HISTORIC) {
        $where[] = "NOT EXISTS (SELECT 1 FROM {logstore_xapi_sent_log} lxsl WHERE lxsl.logstorestandardlogid = x.id)";
    }

    $where = implode(' AND ', $where);

    $sql = "SELECT x.id, x.eventname, u.firstname, u.lastname, x.contextid, x.timecreated, $extraselect
              FROM {{$basetable}} x
         LEFT JOIN {user} u
                ON u.id = x.userid
             WHERE $where";

    // Resend elements.
    $canresenderrors = !empty($fromform->resend) && $fromform->resend == XAPI_REPORT_RESEND_TRUE && $canmanage;

    if ($canresenderrors) {
        $eventids = array_keys($DB->get_records_sql($sql, $params));

        if (!empty($eventids)) {
            $mover = new \logstore_xapi\log\moveback($eventids, $id);
            if ($mover->execute()) {
                $notifications[] = new notification(
                    get_string('resendevents:success', 'logstore_xapi'),
                    notification::NOTIFY_SUCCESS
                );
            } else {
                $notifications[] = new notification(
                    get_string('resendevents:failed', 'logstore_xapi'),
                    notification::NOTIFY_ERROR
                );
            }
        }
    }
}

// Instantiate a class for populating some form data.
$submitcount = new stdClass();
$submitcount->resend = XAPI_REPORT_RESEND_FALSE;

if ($run) {
    // Collect events to create view.
    $results = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    $sql = "SELECT COUNT(x.id)
          FROM {{$basetable}} x
     LEFT JOIN {user} u
            ON u.id = x.userid
         WHERE $where";
    $count = $DB->count_records_sql($sql, $params);
} else {
    // No results will be showing so count is 0.
    $count = 0;
}

// Now we have the count we can set this value for the submit button.
$submitcount->resendselected = get_string('resendevents', 'logstore_xapi', ['count' => $count]);

$mform->set_data($submitcount);

if (!empty($results)) {
    $table = new html_table();
    $table->head = [];
    $table->attributes['class'] = 'admintable generaltable';
    if ($id == XAPI_REPORT_ID_ERROR) {
        $table->head[] = get_string('type', 'logstore_xapi');
    }
    $table->head[] = get_string('eventname', 'logstore_xapi');
    if ($id == XAPI_REPORT_ID_HISTORIC) {
        // Core's string: the plugin's own 'username' string labels the LRS auth key setting.
        $table->head[] = get_string('username');
        $table->head[] = get_string('eventcontext', 'logstore_xapi');
    }
    if ($id == XAPI_REPORT_ID_ERROR) {
        $table->head[] = get_string('response', 'logstore_xapi');
    }
    $table->head[] = get_string('info', 'logstore_xapi');
    $table->head[] = get_string('datetimegmt', 'logstore_xapi');
    $table->head[] = '';
    $table->id = "report";

    foreach ($results as $result) {
        $row = [];
        if ($id == XAPI_REPORT_ID_ERROR) {
            $row[] = $result->errortype;
        }
        $row[] = s($result->eventname);
        if ($id == XAPI_REPORT_ID_HISTORIC) {
            $row[] = s($result->username);

            if ($context = context::instance_by_id($result->contextid, IGNORE_MISSING)) {
                // Escape the unescaped name ourselves: user context names are never escaped by
                // core, and html_writer::table() emits cell text verbatim.
                $row[] = s($context->get_context_name(true, false, false));
            } else {
                $row[] = get_string('contextidnolongerexists', 'logstore_xapi', $result->contextid);
            }
        }
        if ($id == XAPI_REPORT_ID_ERROR) {
            $response = '';
            if (isset($result->response)) {
                // The response body originates from the LRS and is untrusted, and
                // html_writer::table() emits cell text verbatim, so escape it.
                $response = \html_writer::tag('pre', s(logstore_xapi_get_response_display($result->response)));
            } else {
                $response = '-';
            }
            $row[] = $response;
        }
        $row[] = logstore_xapi_get_info_string($result);
        $row[] = userdate($result->timecreated);

        // Add container to the individual reply statements.
        $replycontainer = \html_writer::start_span('reply-event', ['id' => 'reply-event-id-' . $result->id]);
        $replycontainer .= \html_writer::end_span();
        $row[] = $replycontainer;

        $table->data[] = $row;
    }
}

// Set pagination url.
$paginationurl = new moodle_url('/admin/tool/log/store/xapi/report.php', $urlparams);

// Define the page layout and header/breadcrumb.
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string($pagename, 'logstore_xapi'));
$PAGE->set_heading(get_string($pagename, 'logstore_xapi'));

// Add requested items to the page view.
if ($canmanage && $run) {
    $PAGE->requires->js_call_amd('logstore_xapi/replayevents', 'init', [$count, XAPI_REPORT_RESEND_FALSE, XAPI_REPORT_RESEND_TRUE]);
}
$PAGE->requires->css('/admin/tool/log/store/xapi/styles.css');

// Show page.
echo $OUTPUT->header();

if (!empty($notifications)) {
    foreach ($notifications as $notification) {
        echo $OUTPUT->render($notification);
    }
}

echo \html_writer::start_div('', ['id' => 'xapierrorlog']);
echo \html_writer::start_div('', ['id' => 'xapierrorlog_form']);
$mform->display();
echo \html_writer::end_div();

if ($run) {
    if (empty($results)) {
        echo $OUTPUT->heading(get_string('noerrorsfound', 'logstore_xapi'));
    } else {
        echo \html_writer::start_div('no-overflow', ['id' => 'xapierrorlog_data']);
        echo \html_writer::table($table);
        echo \html_writer::end_div();
        echo $OUTPUT->paging_bar($count, $page, $perpage, $paginationurl);
    }
}
echo \html_writer::end_div();
echo $OUTPUT->footer();
