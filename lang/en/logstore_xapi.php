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
$string['account_homepage_desc'] = 'The homePage of every actor account: an absolute URL, such as https://moodle.example.com. Leave empty to use the site URL.';
$string['anonymousevents'] = 'Anonymous events';
$string['anonymousevents_anonymise'] = 'Send without the hidden user\'s identity';
$string['anonymousevents_desc'] = 'Moodle marks some events as anonymous because a user\'s identity must stay hidden, such
as assignment events while blind marking is on, and responses to anonymous feedback activities. By default these events are
sent with that user replaced by an anonymous actor. Other people in the statement, such as the grader, are still identified,
and assignment feedback comments are left out. Choose "Don\'t send" to keep these events out of the LRS entirely.';
$string['anonymousevents_skip'] = 'Don\'t send';
$string['autherror'] = 'The server is returning a 401 error. Please ensure the endpoint, username and auth secret/password for
    the xAPI is correct in the Logstore xAPI settings.';
$string['authmethod'] = 'LRS authentication';
$string['authmethod_basic'] = 'Basic (key and secret)';
$string['authmethod_desc'] = 'How to authenticate to the LRS. Basic sends the key and secret with every request. OAuth2 client credentials exchanges them, as client ID and client secret, for an access token at your identity provider\'s token endpoint, and sends the token.';
$string['authmethod_oauth2'] = 'OAuth2 client credentials';
$string['backgroundmode'] = 'Send statements by scheduled task?';
$string['backgroundmode_desc'] = 'This will force Moodle to send the statements to the LRS in the background,
        via a cron task to avoid blocking page responses. This will make the process less close to real time, but will help to prevent unpredictable
        Moodle performance linked to the performance of the LRS.';
$string['cachedef_oauth2token'] = 'OAuth2 access tokens for the LRS';
$string['cachedef_senderror'] = 'The last error from the LRS';
$string['check_endpointhttps_details'] = 'The xAPI logstore sends learner data and its LRS credentials to the LRS endpoint. Over plain HTTP they are not encrypted, so anyone able to intercept the connection between this server and the LRS can read them. Use an https:// endpoint, unless the LRS is on a private network you trust.';
$string['check_endpointhttps_name'] = 'xAPI LRS endpoint encryption';
$string['check_endpointhttps_ok'] = 'The LRS endpoint uses HTTPS.';
$string['check_endpointhttps_warning'] = 'The LRS endpoint uses plain HTTP.';
$string['check_endpointhttps_warning_token'] = 'The OAuth2 token endpoint uses plain HTTP.';
$string['check_sending_details'] = 'When the LRS can\'t be reached, refuses the credentials or fails, events wait in the queue and are sent once it works again. They don\'t appear in the error report while they wait.';
$string['check_sending_lasterror'] = 'Last error from the LRS ({$a->time}): {$a->error}';
$string['check_sending_name'] = 'xAPI statements sent to the LRS';
$string['check_sending_ok'] = 'No event has been waiting more than an hour to be sent to the LRS.';
$string['check_sending_warning'] = '{$a->count} events are waiting to be sent to the LRS, the oldest for {$a->age}.';
$string['check_sslverification_details'] = 'The xAPI logstore sends learner data and its LRS credentials over this connection. With certificate verification disabled, anyone able to intercept the connection between this server and the LRS can read that data, capture the credentials, and alter the LRS response.';
$string['check_sslverification_na'] = 'The xAPI logstore is not enabled, so no data is sent to an LRS.';
$string['check_sslverification_name'] = 'xAPI LRS certificate verification';
$string['check_sslverification_ok'] = 'The LRS TLS certificate is verified.';
$string['check_sslverification_warning'] = 'LRS TLS certificate verification is disabled.';
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
$string['datefromrequired'] = 'Choose a start date. The historic report covers a date range, as searching the whole log can take too long on a large site.';
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
$string['failedloglifetime'] = 'Keep failed events for';
$string['failedloglifetime_desc'] = 'Delete failed events from the error report once they are older than this, counted from when the event happened. Deleted events can\'t be resent.';
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
$string['invalidreportid'] = 'Invalid report id.';
$string['lmsinstance'] = 'LMS instance on which the error(s) occurred';
$string['logguests'] = 'Log guest actions';
$string['logstore_not_enabled'] = 'The Logstore xAPI plugin is not currently enabled. Events will not be captured until it is enabled via <a href="{$a}">Manage log stores</a>.';
$string['logstorexapierrorlog'] = 'Logstore xAPI Error Log';
$string['logstorexapihistoriclog'] = 'Logstore xAPI Historic Log';
$string['lrserror'] = 'There is a problem with the LDH. The LDH has responded with a 500 error.';
$string['maxbatchsize'] = 'Maximum batch size';
$string['maxbatchsize_desc'] = 'Statements are sent to the LRS in batches, one request per batch, until the queue is empty or the time limit for each run passes. This setting controls the maximum number of events in a batch. Setting this to zero sends all queued events in one request, which is not recommended.';
$string['maxbatchsizeforfailed'] = 'Maximum batch size for failed requests';
$string['maxbatchsizeforfailed_desc'] = 'The maximum number of events taken from the queue at a time when failed events are sent again. Each batch is sent in requests of at most "Maximum batch size" events. Setting this to zero takes all of them at once, which is not recommended.';
$string['maxbatchsizeforhistorical'] = 'Maximum batch size for historical requests';
$string['maxbatchsizeforhistorical_desc'] = 'The maximum number of events taken from the queue at a time when historic events are sent. Each batch is sent in requests of at most "Maximum batch size" events. Setting this to zero takes all of them at once, which is not recommended.';
$string['maxruntime'] = 'Time limit for each run';
$string['maxruntime_desc'] = 'The tasks that send live, failed and historic events send batch after batch until their queue is empty, the LRS can\'t take statements, or this time has passed. It only matters when there is a backlog: raise it to clear a large one, such as a historic backfill, faster. A limit of 0 uses the default, 5 minutes.';
$string['mbox'] = 'Identify users by email';
$string['mbox_desc'] = 'Statements will identify users with their email (mbox) when this box is ticked.';
$string['networkerror'] = 'There was a network error sending the response.';
$string['noerrorsfound'] = 'No errors found';
$string['norows'] = "No rows to report";
$string['notificationsnotenabled'] = "Notifications not enabled";
$string['notificationtriggerlimitnotreached'] = "Notification trigger limit not reached";
$string['oauth2scope'] = 'OAuth2 scope';
$string['oauth2scope_desc'] = 'Optional. The scope to ask for with the token, if your identity provider needs one. Separate several scopes with spaces.';
$string['oauth2tokenendpoint'] = 'OAuth2 token endpoint';
$string['oauth2tokenendpoint_desc'] = 'The token endpoint of your identity provider, for example https://login.example.org/oauth2/token.';
$string['password'] = 'LRS auth secret';
$string['password_desc'] = 'With OAuth2, the client secret.';
$string['pluginadministration'] = 'Logstore xAPI administration';
$string['pluginname'] = 'Logstore xAPI';
$string['privacy:metadata:logstore_xapi_failed_log'] = 'xAPI holding table for failed events';
$string['privacy:metadata:logstore_xapi_failed_log:ip'] = 'The IP address the event was triggered from.';
$string['privacy:metadata:logstore_xapi_failed_log:other'] = 'Additional event data.';
$string['privacy:metadata:logstore_xapi_failed_log:realuserid'] = 'The ID of the real user, when logged in as another user.';
$string['privacy:metadata:logstore_xapi_failed_log:relateduserid'] = 'The ID of the user the event relates to.';
$string['privacy:metadata:logstore_xapi_failed_log:userid'] = 'The ID of the user who triggered the event.';
$string['privacy:metadata:logstore_xapi_log'] = 'xAPI holding table for cron processing';
$string['privacy:metadata:logstore_xapi_log:ip'] = 'The IP address the event was triggered from.';
$string['privacy:metadata:logstore_xapi_log:other'] = 'Additional event data.';
$string['privacy:metadata:logstore_xapi_log:realuserid'] = 'The ID of the real user, when logged in as another user.';
$string['privacy:metadata:logstore_xapi_log:relateduserid'] = 'The ID of the user the event relates to.';
$string['privacy:metadata:logstore_xapi_log:userid'] = 'The ID of the user who triggered the event.';
$string['privacy:metadata:lrs'] = 'Events are converted into xAPI statements and sent to the Learning Record Store (LRS) configured by the administrator.';
$string['privacy:metadata:lrs:email'] = 'The user\'s email address, when the site is set to send it.';
$string['privacy:metadata:lrs:eventdata'] = 'Details of what the user did, which can include their answers and search queries, and, when the site is set to send content, the text they wrote: forum posts, messages, comments, notes and free-text answers.';
$string['privacy:metadata:lrs:fullname'] = 'The user\'s full name, when the site is set to send it.';
$string['privacy:metadata:lrs:userid'] = 'The user\'s ID, which identifies them in the LRS unless the site sends their username or email address instead.';
$string['privacy:metadata:lrs:username'] = 'The user\'s username, when the site is set to send it instead of the ID.';
$string['privacy:path:failed'] = 'Failed events';
$string['recipeerror'] = 'The LDH responded with a 400 error, this can be due to an issue with the recipe.';
$string['removecohort'] = 'Remove {$a}';
$string['replayevent'] = 'Replay event';
$string['resendevents'] = 'Resend ({$a->count})';
$string['resendevents:queued'] = 'The events will be queued to be sent again by a background task, shortly. Until then they are still shown here.';
$string['resendfailedbatches'] = 'Resend failed batches';
$string['resendfailedbatches_desc'] = 'When processing events in batches, try re-sending events in smaller batches if a batch fails. If not selected, the whole batch will not be sent in the event of a failed event.';
$string['response'] = 'Response';
$string['responsesindexing'] = 'Failed events from before the last upgrade are still being indexed, so the response filter doesn\'t list all of them yet. Indexing runs with cron.';
$string['retry_events'] = '{$a->count} event(s) left in the queue to send later: {$a->error}';
$string['routegroupcore'] = 'Core';
$string['routegrouph5p'] = 'H5P';
$string['routes'] = 'Include actions with these routes';
$string['routes_desc'] = 'Private message, note and search events are off by default on new sites, because their statements include the text of the message or note, or the search query.';
$string['send_additional_email_addresses'] = 'Additional email addresses';
$string['send_additional_email_addresses_desc'] = 'Send notifications to list of email addresses. Comma separated values.';
$string['send_content'] = 'Send content';
$string['send_content_desc'] = 'Send what people write with the statements about it: forum post subjects and messages, private messages, wiki pages and comments, glossary definitions and comments, book chapters, notes, the text of essay and short-answer questions in quizzes and lessons, a teacher\'s comment on a lesson essay, and feedback text answers. With this off, the statements still say what happened, without the text. Feedback text answers are never sent from anonymous feedback, as the text can say who wrote it.';
$string['send_name'] = 'Set the actor name field';
$string['send_name_desc'] = 'Will add the user fullname to the actor name field when ticked.';
$string['send_response_choices'] = 'Send response choices';
$string['send_response_choices_desc'] = 'Send the possible and correct answers with quiz, lesson and feedback question statements: the choices of multiple-choice and sequencing questions, the pairs of matching questions, and the correct answer of true/false, short-answer and numerical questions. Leave this off if the LRS can be seen by learners, or by staff who shouldn\'t see the answers.';
$string['send_username'] = 'Identify users by username';
$string['send_username_desc'] = 'Statements will identify users with their username when this box is ticked, but only if identifying users by email is disabled.';
$string['sendevents'] = 'Send ({$a->count})';
$string['sendidnumber'] = 'Send course and activity ID number';
$string['sendidnumber_desc'] = 'Statements will include the ID number (admin defined) for courses and activities in the object extensions';
$string['settings'] = 'General Settings';
$string['shortcourseid'] = 'Send short course name';
$string['shortcourseid_desc'] = 'Statements will contain the shortname for a course as a short course id extension';
$string['sslcabundle'] = 'Custom CA certificate bundle';
$string['sslcabundle_desc'] = 'Absolute path to a PEM certificate bundle used to verify the LRS certificate. Set this when the LRS uses a private or internal certificate authority. This is the preferred alternative to disabling verification, because the certificate is still checked.';
$string['sslverification'] = 'Verify the LRS TLS certificate';
$string['sslverification_desc'] = 'Verify the TLS certificate presented by the LRS. Leave this enabled. Disabling it allows anyone able to intercept the connection to read learner data, capture the LRS username and password, and tamper with the response. If the LRS uses a private certificate authority, set the CA certificate bundle below instead of disabling this.';
$string['submit'] = 'Submit';
$string['successful_events'] = 'event(s) have been successfully processed.';
$string['taskcleanup'] = 'Delete old xAPI log rows';
$string['taskemit'] = 'Emit records to LRS';
$string['taskfailed'] = 'Emit failed records to LRS';
$string['taskhashfailedresponses'] = 'Index failed event responses for the error report';
$string['taskhistorical'] = 'Emit historical records to LRS';
$string['taskresend'] = 'Queue xAPI events to be sent again';
$string['tasksendfailednotifications'] = 'Send failed notifications';
$string['type'] = 'Type';
$string['unknownerror'] = 'Error code: "{$a}"';
$string['unknownverb'] = 'Unknown verb was requested, It should be set by developer.';
$string['updateactivities'] = 'Show the course\'s activities';
$string['user'] = 'User';
$string['user_help'] = 'Searches the users fullname';
$string['username'] = 'LRS auth key';
$string['username_desc'] = 'With OAuth2, the client ID.';
$string['xapi'] = 'xAPI';
$string['xapi:manageerrors'] = 'Replay failed statements';
$string['xapi:managehistoric'] = 'Manage historic data';
$string['xapi:viewerrorlog'] = 'View xAPI error log';
$string['xapifieldset'] = 'Custom example fieldset';
$string['xapisettingstitle'] = 'Logstore xAPI Settings';
