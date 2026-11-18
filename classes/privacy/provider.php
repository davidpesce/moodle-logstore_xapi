<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy Subsystem implementation for logstore_xapi.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\privacy;

use context;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use tool_log\local\privacy\helper;

/**
 * Privacy provider for the xAPI logstore.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \tool_log\local\privacy\logstore_provider,
    \tool_log\local\privacy\logstore_userlist_provider {
    /** @var string[] Tables containing Moodle event records. */
    private const EVENT_TABLES = [
        'logstore_xapi_log',
        'logstore_xapi_failed_log',
    ];

    /** @var string Client statement queue table. */
    private const CLIENT_TABLE = 'logstore_xapi_client_log';

    /** @var string Client statement sent/idempotency table. */
    private const CLIENT_SENT_TABLE = 'logstore_xapi_client_sent';

    /** @var string[] Tables with queued or sent client data. */
    private const CLIENT_TABLES = [
        'logstore_xapi_client_log',
        'logstore_xapi_client_sent',
    ];

    /** @var string[] Standard log columns accepted by event restore. */
    private const EVENT_COLUMNS = [
        'id', 'eventname', 'component', 'action', 'target', 'objecttable',
        'objectid', 'crud', 'edulevel', 'contextid', 'contextlevel',
        'contextinstanceid', 'userid', 'courseid', 'relateduserid',
        'anonymous', 'other', 'timecreated', 'origin', 'ip', 'realuserid',
    ];

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::EVENT_TABLES as $table) {
            $collection->add_database_table(
                $table,
                [
                    'userid' => 'privacy:metadata:' . $table . ':userid',
                    'relateduserid' => 'privacy:metadata:' . $table . ':relateduserid',
                    'realuserid' => 'privacy:metadata:' . $table . ':realuserid',
                    'ip' => 'privacy:metadata:' . $table . ':ip',
                    'other' => 'privacy:metadata:' . $table . ':other',
                ],
                'privacy:metadata:' . $table
            );
        }

        $collection->add_database_table(
            self::CLIENT_TABLE,
            [
                'statement' => 'privacy:metadata:' . self::CLIENT_TABLE . ':statement',
                'statementid' => 'privacy:metadata:' . self::CLIENT_TABLE . ':statementid',
                'userid' => 'privacy:metadata:' . self::CLIENT_TABLE . ':userid',
                'contextid' => 'privacy:metadata:' . self::CLIENT_TABLE . ':contextid',
                'courseid' => 'privacy:metadata:' . self::CLIENT_TABLE . ':courseid',
                'ip' => 'privacy:metadata:' . self::CLIENT_TABLE . ':ip',
                'timecreated' => 'privacy:metadata:' . self::CLIENT_TABLE . ':timecreated',
            ],
            'privacy:metadata:' . self::CLIENT_TABLE
        );

        $collection->add_database_table(
            self::CLIENT_SENT_TABLE,
            [
                'statementid' => 'privacy:metadata:' . self::CLIENT_SENT_TABLE . ':statementid',
                'userid' => 'privacy:metadata:' . self::CLIENT_SENT_TABLE . ':userid',
                'contextid' => 'privacy:metadata:' . self::CLIENT_SENT_TABLE . ':contextid',
                'timecreated' => 'privacy:metadata:' . self::CLIENT_SENT_TABLE . ':timecreated',
            ],
            'privacy:metadata:' . self::CLIENT_SENT_TABLE
        );

        // Sending events to the configured LRS is the plugin's purpose.
        $collection->add_external_location_link(
            'lrs',
            [
                'userid' => 'privacy:metadata:lrs:userid',
                'username' => 'privacy:metadata:lrs:username',
                'fullname' => 'privacy:metadata:lrs:fullname',
                'email' => 'privacy:metadata:lrs:email',
                'eventdata' => 'privacy:metadata:lrs:eventdata',
            ],
            'privacy:metadata:lrs'
        );

        return $collection;
    }

    /**
     * Add contexts containing data for a user.
     *
     * @param contextlist $contextlist Context list.
     * @param int $userid User id.
     * @return void
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        foreach (self::EVENT_TABLES as $table) {
            $sql = "SELECT contextid FROM {" . $table . "}
                     WHERE userid = :userid1
                        OR relateduserid = :userid2
                        OR realuserid = :userid3";
            $contextlist->add_from_sql($sql, [
                'userid1' => $userid,
                'userid2' => $userid,
                'userid3' => $userid,
            ]);
        }

        foreach (self::CLIENT_TABLES as $table) {
            $sql = 'SELECT contextid FROM {' . $table . '} WHERE userid = :userid';
            $contextlist->add_from_sql($sql, ['userid' => $userid]);
        }
    }

    /**
     * Add users with data in a context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function add_userids_for_context(userlist $userlist) {
        $params = ['contextid' => $userlist->get_context()->id];

        foreach (self::EVENT_TABLES as $table) {
            $sql = "SELECT userid, relateduserid, realuserid
                      FROM {" . $table . "}
                     WHERE contextid = :contextid";
            $userlist->add_from_sql('userid', $sql, $params);
            $userlist->add_from_sql('relateduserid', $sql, $params);
            $userlist->add_from_sql('realuserid', $sql, $params);
        }

        foreach (self::CLIENT_TABLES as $table) {
            $sql = 'SELECT userid FROM {' . $table . '} WHERE contextid = :contextid';
            $userlist->add_from_sql('userid', $sql, $params);
        }
    }

    /**
     * Export all user data for the specified user and contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        foreach (self::EVENT_TABLES as $table) {
            $select = "(userid = :userid1 OR relateduserid = :userid2 OR realuserid = :userid3)
                       AND contextid $insql";
            $params = array_merge($inparams, [
                'userid1' => $userid,
                'userid2' => $userid,
                'userid3' => $userid,
            ]);

            self::export_records($table, $select, $params, $userid);
        }

        $select = "userid = :userid AND contextid $insql";
        $params = array_merge($inparams, ['userid' => $userid]);
        foreach (self::CLIENT_TABLES as $table) {
            self::export_records($table, $select, $params, $userid);
        }
    }

    /**
     * Export records from one table, grouped by context.
     *
     * @param string $table Table name.
     * @param string $select SQL select.
     * @param array $params SQL parameters.
     * @param int $userid User id.
     * @return void
     */
    private static function export_records(string $table, string $select, array $params, int $userid): void {
        global $DB;

        $path = self::get_export_subcontext($table);
        $flush = function ($contextid, $data) use ($path) {
            writer::with_context(context::instance_by_id($contextid))
                ->export_data($path, (object) ['logs' => $data]);
        };

        $lastcontextid = null;
        $data = [];
        $recordset = $DB->get_recordset_select($table, $select, $params, 'contextid, timecreated, id');
        foreach ($recordset as $record) {
            if ($lastcontextid && $lastcontextid != $record->contextid) {
                $flush($lastcontextid, $data);
                $data = [];
            }

            if ($table === self::CLIENT_TABLE) {
                $data[] = (object) [
                    'statement' => $record->statement,
                    'statementid' => $record->statementid,
                    'userid' => $record->userid,
                    'contextid' => $record->contextid,
                    'courseid' => $record->courseid,
                    'ip' => $record->ip,
                    'timecreated' => $record->timecreated,
                ];
            } else if ($table === self::CLIENT_SENT_TABLE) {
                $data[] = (object) [
                    'statementid' => $record->statementid,
                    'userid' => $record->userid,
                    'contextid' => $record->contextid,
                    'timecreated' => $record->timecreated,
                ];
            } else {
                $event = (object) array_intersect_key((array) $record, array_flip(self::EVENT_COLUMNS));
                $data[] = helper::transform_standard_log_record_for_userid($event, $userid);
            }
            $lastcontextid = $record->contextid;
        }

        if ($lastcontextid) {
            $flush($lastcontextid, $data);
        }
        $recordset->close();
    }

    /**
     * Delete all data in a context.
     *
     * @param context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        foreach (array_merge(self::EVENT_TABLES, self::CLIENT_TABLES) as $table) {
            $DB->delete_records($table, ['contextid' => $context->id]);
        }
    }

    /**
     * Delete one user's data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $userid = $contextlist->get_user()->id;

        foreach (self::EVENT_TABLES as $table) {
            $params = array_merge($inparams, [
                'userid1' => $userid,
                'userid2' => $userid,
                'userid3' => $userid,
            ]);
            $DB->delete_records_select($table,
                "(userid = :userid1 OR relateduserid = :userid2 OR realuserid = :userid3) AND contextid $insql",
                $params);
        }

        $params = array_merge($inparams, ['userid' => $userid]);
        foreach (self::CLIENT_TABLES as $table) {
            $DB->delete_records_select($table, "userid = :userid AND contextid $insql", $params);
        }
    }

    /**
     * Delete multiple users in one context.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_userlist(approved_userlist $userlist) {
        global $DB;

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }        [$useridsql, $useridparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'userid');
        [$relateduseridsql, $relateduseridparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'relateduserid');
        [$realuseridsql, $realuseridparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'realuserid');
        $eventparams = array_merge(
            $useridparams,
            $relateduseridparams,
            $realuseridparams,
            ['contextid' => $userlist->get_context()->id]
        );

        foreach (self::EVENT_TABLES as $table) {
            $DB->delete_records_select($table,
                "contextid = :contextid AND (userid $useridsql OR relateduserid $relateduseridsql OR realuserid $realuseridsql)",
                $eventparams);
        }
        $clientparams = array_merge($useridparams, ['contextid' => $userlist->get_context()->id]);
        foreach (self::CLIENT_TABLES as $table) {
            $DB->delete_records_select($table, "contextid = :contextid AND userid $useridsql", $clientparams);
        }
    }

    /**
     * Return the export subcontext for a table.
     *
     * @param string $table Table name.
     * @return array
     */
    protected static function get_export_subcontext(string $table): array {
        $path = [
            get_string('privacy:path:logs', 'tool_log'),
            get_string('pluginname', 'logstore_xapi'),
        ];

        if ($table === 'logstore_xapi_failed_log') {
            $path[] = get_string('privacy:path:failed', 'logstore_xapi');
        } else if (in_array($table, self::CLIENT_TABLES, true)) {
            $path[] = get_string('privacy:path:client', 'logstore_xapi');
        }

        return $path;
    }
}
