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
 * English log store lang strings.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


$string['account_homepage'] = 'Actor Account HomePage';
$string['account_homepage_desc'] = 'Set the homePage field for actor accounts. Defaults to the app_url.';
$string['autherror'] = 'The server is returning a 401 error. Please ensure the endpoint, username and auth secret/password for
    the xAPI is correct in the Logstore xAPI settings.';
$string['backgroundmode'] = 'Send statements by scheduled task?';
$string['backgroundmode_desc'] = 'This will force Moodle to send the statements to the LRS in the background,
        via a cron task to avoid blocking page responses. This will make the process less close to real time, but will help to prevent unpredictable
        Moodle performance linked to the performance of the LRS.';
$string['cohorts'] = 'Cohorts';
$string['cohorts_help'] = 'Add cohort(s) to notifications';
$string['confirmresendevents'] = 'You are about to send {$a->count} record(s) to the queue for reprocessing.<br>Do you wish to continue?';
$string['confirmresendeventsheader'] = 'Resend events';
$string['confirmsendevents'] = 'You are about to send {$a->count} record(s) to the queue for reprocessing.<br>Do you wish to continue?';
$string['confirmsendeventsheader'] = 'Send events';
$string['context_platform'] = 'Context Platform';
$string['context_platform_desc'] = 'Set the context platform field of xAPI statements. Defaults to "Moodle".';
$string['contextidnolongerexists'] = 'Context ID {$a} no longer exists';
$string['count'] = 'Count';
$string['datetimegmt'] = 'Date/Time (GMT)';
$string['datetovalidation'] = 'The To date cannot be before the From date';
$string['enablesendingnotifications'] = 'Send notifications?';
$string['enablesendingnotifications_desc'] = 'Control if notifications should be sent to configured recipients.';
$string['endpoint'] = 'LRS endpoint';
$string['errorlogpage'] = "Error log page";
$string['errornotificationtrigger'] = 'Error notification trigger';
$string['errornotificationtrigger_desc'] = 'Threshold value at which point notifications will be triggered. When a number of errors greater than this value have been generated, the notification is sent.';
$string['errortype'] = 'Error Type';
$string['eventcontext'] = 'Event Context';
$string['eventname'] = 'Event Name';
$string['failed'] = 'Failed';
$string['failed_events'] = 'event(s) have failed to send to the LRS.';
$string['failedsubject'] = "XAPI Logstore: failed to send messages report";
$string['failedtosend'] = "The following statements have failed to be sent to the LDH.";
$string['failedtransformresponse'] = 'Event: "{$a}" was not transformed successfully';
$string['failurelog'] = "Failure log";
$string['filters'] = 'Filter logs';
$string['filters_help'] = 'Enable filters that INCLUDE some actions to be logged.';
$string['heading_actoridentification'] = 'Actor identification';
$string['heading_actoridentification_desc'] = 'Control how users are identified in xAPI statements.';
$string['heading_lrsconnection'] = 'LRS connection';
$string['heading_lrsconnection_desc'] = 'Endpoint and credentials for connecting to the Learning Record Store.';
$string['heading_notifications'] = 'Notifications';
$string['heading_notifications_desc'] = 'Configure error notification recipients and thresholds.';
$string['heading_processingbatches'] = 'Processing and batches';
$string['heading_processingbatches_desc'] = 'Control how and when statements are sent to the LRS.';
$string['heading_statementcontent'] = 'Statement content';
$string['heading_statementcontent_desc'] = 'Control what data is included in xAPI statements.';
$string['includecohorts'] = 'Include these cohorts in notifications';
$string['info'] = 'Info';
$string['insendfailednotificationstask'] = 'In send failed notifications task execute';
$string['lmsinstance'] = 'LMS instance on which the error(s) occurred';
$string['logguests'] = 'Log guest actions';
$string['logstore_not_enabled'] = 'The Logstore xAPI plugin is not currently enabled. Events will not be captured until it is enabled via <a href="{$a}">Manage log stores</a>.';
$string['logstorexapierrorlog'] = 'Logstore xAPI Error Log';
$string['logstorexapihistoriclog'] = 'Logstore xAPI Historic Log';
$string['lrserror'] = 'There is a problem with the LDH. The LDH has responded with a 500 error.';
$string['maxbatchsize'] = 'Maximum batch size';
$string['maxbatchsize_desc'] = 'Statements are sent to the LRS in batches. This setting controls the maximum number of
        statements that will be sent in a single operation. Setting this to zero will cause all available statements to
        be sent at once, although this is not recommended.';
$string['maxbatchsizeforfailed'] = 'Maximum batch size for failed requests';
$string['maxbatchsizeforfailed_desc'] = 'Statements are sent to the LRS in batches. This setting controls the maximum number of
        statements that will be sent in a single operation for failed requests. Setting this to zero will cause all available statements to
        be sent at once, although this is not recommended.';
$string['maxbatchsizeforhistorical'] = 'Maximum batch size for historical requests';
$string['maxbatchsizeforhistorical_desc'] = 'Statements are sent to the LRS in batches. This setting controls the maximum number of
        statements that will be sent in a single operation for historical requests. Setting this to zero will cause all available statements to
        be sent at once, although this is not recommended.';
$string['mbox'] = 'Identify users by email';
$string['mbox_desc'] = 'Statements will identify users with their email (mbox) when this box is ticked.';
$string['networkerror'] = 'There was a network error sending the response.';
$string['noerrorsfound'] = 'No errors found';
$string['norows'] = "No rows to report";
$string['notificationsnotenabled'] = "Notifications not enabled";
$string['notificationtriggerlimitnotreached'] = "Notification trigger limit not reached";
$string['oauth2_token_endpoint'] = 'OAuth2 identity provider token endpoint';
$string['oauth2_enabled'] = 'OAuth2 identity provider token endpoint';
$string['oauth2_enabled_desc'] = 'When enabled,
        connect to the OAuth2 identity provider using Username/password as ClientID/client secret
        and the Client Credentials flow to get an auth token.
        This token is then used to authenticate to the LRS.
        Disable this option to connect to the LRS using Basic Auth';
$string['password'] = 'LRS auth secret';
$string['pluginadministration'] = 'Logstore xAPI administration';
$string['pluginname'] = 'Logstore xAPI';
$string['privacy:metadata:logstore_xapi_failed_log'] = 'xAPI holding table for failed events';
$string['privacy:metadata:logstore_xapi_failed_log:userid'] = 'User Id of xAPI holding table for failed events';
$string['privacy:metadata:logstore_xapi_log'] = 'xAPI holding table for cron processing';
$string['privacy:metadata:logstore_xapi_log:userid'] = 'User Id of xAPI holding table for cron processing';
$string['recipeerror'] = 'The LDH responded with a 400 error, this can be due to an issue with the recipe.';
$string['replayevent'] = 'Replay event';
$string['resendevents'] = 'Resend ({$a->count})';
$string['resendevents:failed'] = 'Events failed to be sent for reprocessing.';
$string['resendevents:success'] = 'Events successfully sent for reprocessing.';
$string['resendfailedbatches'] = 'Resend failed batches';
$string['resendfailedbatches_desc'] = 'When processing events in batches, try re-sending events in smaller batches if a batch fails. If not selected, the whole batch will not be sent in the event of a failed event.';
$string['response'] = 'Response';
$string['routes'] = 'Include actions with these routes';
$string['send_additional_email_addresses'] = 'Additional email addresses';
$string['send_additional_email_addresses_desc'] = 'Send notifications to list of email addresses. Comma separated values.';
$string['send_name'] = 'Set the actor name field';
$string['send_name_desc'] = 'Will add the user fullname to the actor name field when ticked.';
$string['send_response_choices'] = 'Send response choices';
$string['send_response_choices_desc'] = 'Statements for multiple choice and sequencing question answers will be sent to the LRS with the correct response and potential choices';
$string['send_username'] = 'Identify users by username';
$string['send_username_desc'] = 'Statements will identify users with their username when this box is ticked, but only if identifying users by email is disabled.';
$string['sendevents'] = 'Send ({$a->count})';
$string['sendidnumber'] = 'Send course and activity ID number';
$string['sendidnumber_desc'] = 'Statements will include the ID number (admin defined) for courses and activities in the object extensions';
$string['settings'] = 'General Settings';
$string['shortcourseid'] = 'Send short course name';
$string['shortcourseid_desc'] = 'Statements will contain the shortname for a course as a short course id extension';
$string['submit'] = 'Submit';
$string['successful_events'] = 'event(s) have been successfully processed.';
$string['taskemit'] = 'Emit records to LRS';
$string['taskfailed'] = 'Emit failed records to LRS';
$string['taskhistorical'] = 'Emit historical records to LRS';
$string['tasksendfailednotifications'] = 'Send failed notifications';
$string['type'] = 'Type';
$string['unknownerror'] = 'Error code: "{$a}"';
$string['unknownverb'] = 'Unknown verb was requested, It should be set by developer.';
$string['user'] = 'User';
$string['user_help'] = 'Searches the users fullname';
$string['username'] = 'LRS auth key';
$string['xapi'] = 'xAPI';
$string['xapi:manageerrors'] = 'Replay failed statements';
$string['xapi:managehistoric'] = 'Manage historic data';
$string['xapi:viewerrorlog'] = 'View xAPI error log';
$string['xapifieldset'] = 'Custom example fieldset';
$string['xapisettingstitle'] = 'Logstore xAPI Settings';
