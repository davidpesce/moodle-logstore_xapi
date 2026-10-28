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

namespace logstore_xapi\log;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../src/autoload.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

use core_plugin_manager;
use tool_log\log\writer as log_writer;
use tool_log\log\manager as log_manager;
use tool_log\helper\store as helper_store;
use tool_log\helper\reader as helper_reader;
use tool_log\helper\buffered_writer as helper_writer;
use core\event\base as event_base;
use stdClass as php_obj;
/**
 * Processes events and enables them to be sent to a logstore.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class store extends php_obj implements log_writer {
    use helper_store;
    use helper_reader;
    use helper_writer;

    /**
     * @var callable|null A Guzzle handler that stands in for the LRS, in unit tests only, so that
     *     the scheduled tasks can be tested against any LRS response. See \curl::mock_response().
     */
    public static $mocklrs = null;

    /**
     * Constructs a new store.
     * @param log_manager $manager
     */
    public function __construct(log_manager $manager) {
        $this->helper_setup($manager);
    }

    /**
     * Should the event be ignored (not logged)? Overrides helper_writer.
     * @param event_base $event
     * @return bool
     *
     */
    protected function is_event_ignored(event_base $event) {
        $allowguestlogging = $this->get_config('logguests', 1);
        if ((!CLI_SCRIPT || PHPUNIT_TEST) && !$allowguestlogging && (!isloggedin() || isguestuser())) {
            // Always log inside CLI scripts because we do not login there. As in logstore_standard,
            // a user who isn't logged in counts as a guest.
            return true;
        }

        $enabledevents = explode(',', $this->get_config('routes', ''));
        $isdisabledevent = !in_array($event->eventname, $enabledevents);
        return $isdisabledevent;
    }

    /**
     * Find the logstore_standard_log row an event was logged as.
     *
     * The event is matched on every column the two logs share, except the TEXT column "other",
     * which cannot be compared in SQL on every database. These include the columns of the
     * standard log's user-module index, so the lookup can use it. Identical events in the same
     * second match several rows: the latest is returned, or the latest before $below.
     *
     * @param \stdClass $event The event, as stored in logstore_xapi_log.
     * @param int|null $below Only consider rows with a lower id than this.
     * @return int The row id, or 0 when no row matches.
     */
    protected function get_event_id($event, ?int $below = null) {
        global $DB;

        $fields = ['eventname', 'component', 'action', 'target', 'objecttable', 'objectid', 'crud', 'edulevel',
            'contextid', 'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'relateduserid', 'anonymous',
            'timecreated'];
        $where = [];
        $params = [];
        foreach ($fields as $field) {
            if (isset($event->$field)) {
                $where[] = "$field = :$field";
                $params[$field] = $event->$field;
            } else {
                $where[] = "$field IS NULL";
            }
        }
        if (!is_null($below)) {
            $where[] = 'id < :below';
            $params['below'] = $below;
        }

        $sql = 'SELECT MAX(id) FROM {logstore_standard_log} WHERE ' . implode(' AND ', $where);
        return (int) $DB->get_field_sql($sql, $params);
    }

    /**
     * Insert events in bulk to the database. Overrides helper_writer.
     *
     * @param array $events raw event data
     * @return void
     */
    protected function insert_event_entries($events) {
        global $DB;

        // If in background mode, just save them in the database.
        if ($this->get_config('backgroundmode', false)) {
            // The standard log id is looked up when the event is sent, not here in the user's
            // request. The standard store buffers its events too, so its row may not exist yet:
            // it is written after this store's when the standard store comes later in the list
            // of enabled stores.
            $events = $this->convert_array_to_objects($events);
            foreach ($events as $event) {
                $event->type = XAPI_IMPORT_TYPE_LIVE;
            }
            $DB->insert_records('logstore_xapi_log', $events);
        } else {
            // Keep events that fail so they can be replayed, as the scheduled tasks do. This
            // runs during a web request, so no mtrace() output.
            $loadedevents = $this->process_events($events);
            logstore_xapi_store_failed_events($loadedevents, false);
            // Events the LRS can't take now go to the queue, for the emit task to send later.
            logstore_xapi_record_retry_events($loadedevents, false);
            foreach (logstore_xapi_get_retry_events($loadedevents) as $event) {
                $DB->insert_record('logstore_xapi_log', $event);
            }
        }
    }

    /**
     * Retrieve the maximum batch size.
     *
     * @return int
     */
    public function get_max_batch_size() {
        return $this->get_config('maxbatchsize', 100);
    }

    /**
     * Retrieve the maximum batch size for failed events.
     *
     * @return int
     */
    public function get_max_batch_size_for_failed() {
        return $this->get_config('maxbatchsizeforfailed', 100);
    }

    /**
     * Retrieve the maximum batch size for historical events.
     *
     * @return int
     */
    public function get_max_batch_size_for_historical() {
        return $this->get_config('maxbatchsizeforhistorical', 100);
    }

    /**
     * Set the events' type, and look up the logstore_standard_log id of those that have none.
     *
     * Events from the historic report and the failed log already have their id, so they keep it.
     * Identical events in the same second match the same rows, so each event in the batch is
     * given a row no other event in the batch has.
     *
     * @param array $events raw event data
     * @param int $eventtype event type
     * @return array
     */
    private function get_persistent_eventids(array $events, $eventtype = XAPI_IMPORT_TYPE_LIVE) {
        $assigned = [];
        foreach ($events as $event) {
            $event->type = $eventtype;
            if (empty($event->logstorestandardlogid)) {
                $id = $this->get_event_id($event);
                while ($id && isset($assigned[$id])) {
                    $id = $this->get_event_id($event, $id);
                }
                $event->logstorestandardlogid = $id;
            }
            $assigned[$event->logstorestandardlogid] = true;
        }
        return $events;
    }

    /**
     * Process events.
     * Transform events using the correct event handler and save sent events.
     *
     * @param array $events raw event data
     * @param int $eventtype event type
     * @return array
     */
    public function process_events(array $events, $eventtype = XAPI_IMPORT_TYPE_LIVE) {
        $events = $this->convert_array_to_objects($events);
        [$events, $skippedevents] = $this->split_skipped_events($events);
        $events = $this->get_persistent_eventids($events, $eventtype);

        $config = $this->get_handler_config();
        $loadedevents = \src\handler($config, $events);
        logstore_xapi_save_sent_events($loadedevents);

        return array_merge($loadedevents, $skippedevents);
    }

    /**
     * Separate the events that must not be sent.
     *
     * These are events the admin has chosen not to send, and anonymous events. Routes and
     * logguests are checked when an event is captured (is_event_ignored()), but events also
     * arrive from the historic report and the failed log, and a route may have been turned off
     * since an event was queued. Every path goes through process_events(), so the check is
     * repeated here.
     *
     * Core flags an event as anonymous when the user's identity must stay hidden (assignment
     * blind marking, anonymous feedback). By default those events are sent with the protected
     * user replaced by an anonymous actor (see utils\anonymise_statements()); with the
     * anonymousevents setting at "don't send", they are skipped here.
     *
     * Skipped events are returned with 'loaded' set to null: the tasks delete them from the
     * queue, but they are not recorded as sent or as failed.
     *
     * @param array $events Event objects.
     * @return array The events to send, and the skipped events in the loaded-event format.
     */
    private function split_skipped_events(array $events) {
        $enabledevents = array_flip(explode(',', $this->get_config('routes', '')));
        $allowguestlogging = $this->get_config('logguests', 1);
        $skipanonymous = $this->get_config('anonymousevents', XAPI_ANONYMOUS_EVENTS_ANONYMISE)
            === XAPI_ANONYMOUS_EVENTS_SKIP;

        $routedevents = [];
        $skippedevents = [];
        foreach ($events as $event) {
            $isrouted = isset($event->eventname) && isset($enabledevents[$event->eventname]);
            $isguest = $this->is_guest_event($event);
            $isskippedanonymous = $skipanonymous && !empty($event->anonymous);
            if ($isrouted && ($allowguestlogging || !$isguest) && !$isskippedanonymous) {
                $routedevents[] = $event;
            } else {
                $skippedevents[] = [
                    'event' => $event,
                    'statements' => [],
                    'transformed' => false,
                    'loaded' => null,
                ];
            }
        }
        return [$routedevents, $skippedevents];
    }

    /**
     * Was the event triggered by a guest, or by a user who isn't logged in (user id 0)?
     *
     * This is the "Log guest actions" rule of is_event_ignored(), for events that have been
     * queued. Like it, it leaves out events logged by CLI scripts, which have no logged-in user.
     *
     * @param \stdClass $event The event.
     * @return bool
     */
    private function is_guest_event(\stdClass $event): bool {
        global $CFG;

        if (!isset($event->userid)) {
            return false;
        }
        if ((string) $event->userid === '0') {
            return ($event->origin ?? '') !== 'cli';
        }
        return !empty($CFG->siteguest) && $event->userid == $CFG->siteguest;
    }

    /**
     * Get handler configuration.
     *
     * @return array Handler configuration.
     */
    protected function get_handler_config() {
        global $DB, $CFG;

        $plugin = core_plugin_manager::instance()->get_plugin_info('logstore_xapi');

        // Messages can hold LRS response bodies, and debugging() prints them unescaped.
        $logerror = function ($message = '') {
            debugging(s(\core_text::substr((string) $message, 0, 2000)), DEBUG_NORMAL);
        };
        $loginfo = function ($message = '') {
            debugging(s((string) $message), DEBUG_DEVELOPER);
        };

        $handlerconfig = [
            'log_error' => $logerror,
            'log_info' => $loginfo,
            'transformer' => [
                'source_lang' => 'en',
                'send_mbox' => $this->get_config('mbox', false),
                'send_name' => $this->get_config('send_name', true),
                'send_response_choices' => $this->get_config('sendresponsechoices', false),
                'send_short_course_id' => $this->get_config('shortcourseid', false),
                'send_course_and_module_idnumber' => $this->get_config('sendidnumber', false),
                'send_username' => $this->get_config('send_username', false),
                'account_homepage' => $this->get_config('account_homepage', $CFG->wwwroot),
                'context_platform' => $this->get_config('context_platform', 'Moodle'),
                'plugin_url' => 'https://github.com/xAPI-vle/moodle-logstore_xapi',
                'plugin_version' => $plugin->release,
                'repo' => new \src\transformer\repos\MoodleRepository($DB),
                'app_url' => $CFG->wwwroot,
            ],
            'loader' => [
                'loader' => 'moodle_curl_lrs',
                'lrs_endpoint' => $this->get_config('endpoint', ''),
                'lrs_username' => $this->get_config('username', ''),
                'lrs_password' => $this->get_config('password', ''),
                'lrs_auth_method' => $this->get_config('authmethod', 'basic'),
                'lrs_oauth2_token_endpoint' => $this->get_config('oauth2tokenendpoint', ''),
                'lrs_oauth2_scope' => $this->get_config('oauth2scope', ''),
                'lrs_mock_handler' => (defined('PHPUNIT_TEST') && PHPUNIT_TEST) ? self::$mocklrs : null,
                'lrs_max_batch_size' => $this->get_max_batch_size(),
                'lrs_resend_failed_batches' => $this->get_config('resendfailedbatches', false),
                'lrs_ssl_verification' => $this->get_config('sslverification', true),
                'lrs_ssl_cabundle' => $this->get_config('sslcabundle', ''),
            ],
        ];

        $handlerconfig['transformer'] = array_merge($handlerconfig['transformer'], [
            'source_url' => 'http://moodle.org',
            'source_name' => 'Moodle',
            'source_version' => $CFG->release,
        ]);

        return $handlerconfig;
    }

    /**
     * Determines if a connection exists to the store.
     * @return boolean
     */
    public function is_logging() {
        return true;
    }

    /**
     * Reread or convert event to object.
     *
     * @param array $events Array of events
     * @return array of objects of events.
     */
    protected function convert_array_to_objects($events) {
        $return = [];

        if (!empty($events)) {
            foreach ($events as $event) {
                if (is_object($event)) {
                    $return[] = $event;
                } else {
                    $return[] = (object)$event;
                }
            }
        }

        return $return;
    }
}
