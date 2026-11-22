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
 * AJAX endpoint: receives and queues client-side xAPI events.
 *
 * @package   logstore_xapi
 * @copyright 2026 Lachlan Keown <lachlankeown@gmail.com>, NZ ADL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(dirname(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__))))))) . '/config.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

global $DB, $CFG;

require_login();
require_sesskey();

header('Content-Type: application/json');

// State-changing endpoint: only POST carries the statement body.
if (!data_submitted()) {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'action' => 'rejected',
        'queued' => false,
        'delivered' => false,
        'duplicate' => false,
        'message' => get_string('clientajaxmethod', 'logstore_xapi'),
    ]);
    die;
}

// Guest telemetry is meaningless (no real user to attribute the actor to)
// and only fills the queue, so reject it up front.
if (isguestuser()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'action' => 'rejected',
        'queued' => false,
        'delivered' => false,
        'duplicate' => false,
        'message' => get_string('clientajaxguest', 'logstore_xapi'),
    ]);
    die;
}

$statementjson = required_param('statement', PARAM_RAW);

// Counter-spoofing: require_login + sesskey prove the request came
// from the logged-in user's own session (no CSRF), but the user can still
// hand-craft the POST body. Treat it as hostile input: reject oversized
// payloads BEFORE json_decode (memory/DoS), then decode with a depth cap.
if (!logstore_xapi_validate_client_statement_json($statementjson, $error)) {
    http_response_code($error === get_string('clientpayloadsize', 'logstore_xapi') ? 413 : 400);
    echo json_encode([
        'success' => false,
        'action' => 'rejected',
        'queued' => false,
        'delivered' => false,
        'duplicate' => false,
        'message' => $error ?: get_string('clientajaxinvalid', 'logstore_xapi'),
    ]);
    die;
}
$statement = json_decode($statementjson, true, XAPI_CLIENT_STATEMENT_MAX_DEPTH + 1);

if (json_last_error() !== JSON_ERROR_NONE || !logstore_xapi_validate_client_statement($statement, $error,
        $statementjson)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'action' => 'rejected',
        'queued' => false,
        'delivered' => false,
        'duplicate' => false,
        'message' => $error ?: get_string('clientajaxinvalid', 'logstore_xapi'),
    ]);
    die;
}

if (!\logstore_xapi\client\verb_policy::is_enabled($statement['verb']['id'] ?? '')) {
    echo json_encode([
        'success' => true,
        'action' => 'filtered',
        'queued' => false,
        'delivered' => false,
        'duplicate' => false,
        'message' => get_string('clientajaxfiltered', 'logstore_xapi'),
    ]);
    die;
}

// The actor is derived from the authenticated user and any client supplied
// actor is discarded so statements cannot be made to impersonate other users.
// LRS-owned fields are stripped as well in case a future validator change
// lets one through; the LRS assigns stored/authority/version itself.
unset($statement['stored'], $statement['authority']);
$statement['actor'] = \logstore_xapi\client\queue_processor::get_actor_for_user($USER, [
    'send_mbox' => (bool)get_config('logstore_xapi', 'mbox'),
    'send_name' => (bool)get_config('logstore_xapi', 'send_name'),
    'send_username' => (bool)get_config('logstore_xapi', 'send_username'),
    'account_homepage' => (string)get_config('logstore_xapi', 'account_homepage'),
    'app_url' => $CFG->wwwroot,
]);
// The browser's course grouping is a hint, not a fact: resolve it to a
// server-verified course id (or strip it) so forged claims reach neither
// the queue row nor the LRS.
$courseid = logstore_xapi_resolve_client_statement_course($statement, $USER);
// Match server-side transformers: the Send content setting controls learner-written
// response text. Apply the setting before storing the statement so it is not retained
// in the queue or sent to the LRS when disabled.
if (!\logstore_xapi\client\queue_processor::is_sending_content()) {
    \logstore_xapi\client\queue_processor::remove_response_content($statement);
}
$statementjson = json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if ($statementjson === false ||
        !logstore_xapi_validate_client_statement($statement, $error, $statementjson)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'action' => 'rejected',
        'queued' => false,
        'delivered' => false,
        'duplicate' => false,
        'message' => $error ?: get_string('clientajaxinvalid', 'logstore_xapi'),
    ]);
    die;
}

$context = context_user::instance($USER->id);
$result = logstore_xapi_queue_client_statement(
    $statement,
    $statementjson,
    $USER->id,
    $context->id,
    $courseid,
    getremoteaddr()
);

$isbackground = (bool)get_config('logstore_xapi', 'backgroundmode');

$action = $isbackground ? 'queued' : 'send';
$delivery = null;

if (!$result['duplicate'] && !$isbackground) {
    $record = $DB->get_record('logstore_xapi_client_log', ['id' => $result['id']]);
    if ($record === false) {
        // The scheduled task already delivered the statement concurrently.
        $delivery = [
            'processed' => 1,
            'sent' => 1,
            'failed' => 0,
            'skipped' => false,
            'results' => [['id' => $result['id'], 'success' => true, 'errortype' => 0, 'response' => '']],
        ];
    } else {
        $delivery = \logstore_xapi\client\queue_processor::process_records([$record]);
    }
}

if ($delivery !== null) {
    if (!empty($delivery['skipped'])) {
        $action = 'queued';
    } else if ($delivery['sent'] === 1) {
        $action = 'sent';
    } else {
        $action = 'failed';
    }
}

$success = $result['duplicate'] || $isbackground ||
    ($delivery !== null && (empty($delivery['skipped']) && $delivery['failed'] === 0));
$response = [
    'success' => $success,
    'action' => $result['duplicate'] ? 'duplicate' : $action,
    'queued' => !$result['duplicate'] && ($isbackground || $action === 'queued' || $action === 'failed'),
    'delivered' => !$result['duplicate'] && $action === 'sent',
    'duplicate' => $result['duplicate'],
    'message' => $result['duplicate'] ? get_string('clientajaxduplicate', 'logstore_xapi') :
        ($action === 'queued' ? get_string('clientajaxqueued', 'logstore_xapi') :
        ($action === 'sent' ? get_string('clientajaxsent', 'logstore_xapi') :
        get_string('clientajaxfailed', 'logstore_xapi'))),
];

if ($delivery !== null && $action === 'failed' && !empty($delivery['results'][0])) {
    $response['error'] = $delivery['results'][0]['response'];
    $response['errortype'] = $delivery['results'][0]['errortype'];
}

echo json_encode($response);
