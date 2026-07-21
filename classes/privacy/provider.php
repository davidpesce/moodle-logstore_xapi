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
 * Privacy Subsystem for logstore_xapi.
 *
 * Implements the tool_log subplugin interfaces so that the log privacy
 * aggregation in tool_log dispatches to this store, matching how
 * logstore_standard and logstore_database participate.
 *
 * Two tables hold event data: logstore_xapi_log (queued for processing) and
 * logstore_xapi_failed_log (events that could not be sent to the LRS). Both
 * share the standard log column layout, so both are handled identically.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \tool_log\local\privacy\logstore_provider,
    \tool_log\local\privacy\logstore_userlist_provider {
    /** @var string[] The tables holding event data. */
    private const TABLES = [
        'logstore_xapi_log',
        'logstore_xapi_failed_log',
    ];

    /**
     * The standard log columns, which are the only keys core's event restore
     * accepts. Both tables carry extra bookkeeping columns (errortype,
     * response, logstorestandardlogid, type) that must be stripped before a
     * record is handed to core, so this is an allow-list rather than a list of
     * columns to remove: a column added to either table in future is then
     * excluded automatically instead of breaking the export.
     *
     * @var string[]
     */
    private const EVENT_COLUMNS = [
        'id', 'eventname', 'component', 'action', 'target', 'objecttable',
        'objectid', 'crud', 'edulevel', 'contextid', 'contextlevel',
        'contextinstanceid', 'userid', 'courseid', 'relateduserid',
        'anonymous', 'other', 'timecreated', 'origin', 'ip', 'realuserid',
    ];

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $collection): collection {
        foreach (self::TABLES as $table) {
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

        return $collection;
    }

    /**
     * Add contexts that contain user information for the specified user.
     *
     * @param contextlist $contextlist The contextlist to add the contexts to.
     * @param int $userid The user to search.
     * @return void
     */
    public static function add_contexts_for_userid(contextlist $contextlist, $userid) {
        foreach (self::TABLES as $table) {
            $sql = "SELECT x.contextid
                      FROM {" . $table . "} x
                     WHERE x.userid = :userid1
                        OR x.relateduserid = :userid2
                        OR x.realuserid = :userid3";
            $contextlist->add_from_sql($sql, [
                'userid1' => $userid,
                'userid2' => $userid,
                'userid3' => $userid,
            ]);
        }
    }

    /**
     * Add all users that have data within a context.
     *
     * @param userlist $userlist The userlist to add the users to.
     * @return void
     */
    public static function add_userids_for_context(userlist $userlist) {
        $params = ['contextid' => $userlist->get_context()->id];

        foreach (self::TABLES as $table) {
            $sql = "SELECT userid, relateduserid, realuserid
                      FROM {" . $table . "}
                     WHERE contextid = :contextid";
            $userlist->add_from_sql('userid', $sql, $params);
            $userlist->add_from_sql('relateduserid', $sql, $params);
            $userlist->add_from_sql('realuserid', $sql, $params);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;
        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $select = "(userid = :userid1 OR relateduserid = :userid2 OR realuserid = :userid3) AND contextid $insql";
        $params = array_merge($inparams, [
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
        ]);

        foreach (self::TABLES as $table) {
            $path = self::get_export_subcontext($table);

            // Records are grouped by context and written out whenever the
            // context changes, so the whole table is never held in memory.
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
                // Core rebuilds an event object from the record and rejects any
                // key that is not an event property, so keep only the standard
                // log columns before handing the record over.
                $event = (object) array_intersect_key((array) $record, array_flip(self::EVENT_COLUMNS));
                $data[] = helper::transform_standard_log_record_for_userid($event, $userid);
                $lastcontextid = $record->contextid;
            }
            if ($lastcontextid) {
                $flush($lastcontextid, $data);
            }
            $recordset->close();
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        foreach (self::TABLES as $table) {
            $DB->delete_records($table, ['contextid' => $context->id]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['userid' => $contextlist->get_user()->id]);

        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "userid = :userid AND contextid $insql", $params);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     * @return void
     */
    public static function delete_data_for_userlist(approved_userlist $userlist) {
        global $DB;

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params = array_merge($inparams, ['contextid' => $userlist->get_context()->id]);

        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "contextid = :contextid AND userid $insql", $params);
        }
    }

    /**
     * The subcontext an export for a table is written under.
     *
     * @param string $table The table being exported.
     * @return array
     */
    protected static function get_export_subcontext(string $table): array {
        $path = [
            get_string('privacy:path:logs', 'tool_log'),
            get_string('pluginname', 'logstore_xapi'),
        ];

        // Keep failed events in their own folder so an export does not imply
        // they were successfully sent to the LRS.
        if ($table === 'logstore_xapi_failed_log') {
            $path[] = get_string('privacy:path:failed', 'logstore_xapi');
        }

        return $path;
    }
}
