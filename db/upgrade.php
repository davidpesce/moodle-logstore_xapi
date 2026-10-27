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
 * xAPI logstore upgrade.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 /**
  * Create the xapi_log table.
  *
  * @param object $dbman     Database manipulation object.
  * @param string $tablename The name of the table.
  * @return void
  */
function logstore_xapi_create_xapi_log_table($dbman, $tablename) {
    // Define table to be created.
    $table = new xmldb_table($tablename);

    // Adding fields to table.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('eventname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
    $table->add_field('action', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
    $table->add_field('target', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
    $table->add_field('objecttable', XMLDB_TYPE_CHAR, '50', null, null, null, null);
    $table->add_field('objectid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('crud', XMLDB_TYPE_CHAR, '1', null, XMLDB_NOTNULL, null, null);
    $table->add_field('edulevel', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null);
    $table->add_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('contextlevel', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('contextinstanceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('relateduserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('anonymous', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('other', XMLDB_TYPE_TEXT, null, null, null, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('origin', XMLDB_TYPE_CHAR, '10', null, null, null, null);
    $table->add_field('ip', XMLDB_TYPE_CHAR, '45', null, null, null, null);
    $table->add_field('realuserid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);

    // Adding keys to table.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

    // Adding indexes to table.
    $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
    $table->add_index('course-time', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'anonymous', 'timecreated']);
    $table->add_index('user-module', XMLDB_INDEX_NOTUNIQUE, [
        'userid',
        'contextlevel',
        'contextinstanceid',
        'crud',
        'edulevel',
        'timecreated',
    ]);

    // Conditionally launch create table.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
}

 /**
  * Create the xapi_sent_log table.
  *
  * @param object $dbman     Database manipulation object.
  * @param string $tablename The name of the table.
  * @return void
  */
function logstore_xapi_create_xapi_sent_log_table($dbman, $tablename) {
    // Define table to be created.
    $table = new xmldb_table($tablename);

    // Adding fields to table.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('logstorestandardlogid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('type', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

    // Adding keys to table.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

    // Adding indexes to table.
    $table->add_index('logstorestandardlogid', XMLDB_INDEX_NOTUNIQUE, ['logstorestandardlogid']);
    $table->add_index('type', XMLDB_INDEX_NOTUNIQUE, ['type']);
    $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

    // Conditionally launch create table.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
}

 /**
  * Create new columns in the database.
  *
  * @param object $dbman     Database manipulation object.
  * @param string $tablename The name of the table.
  * @return void
  */
function logstore_xapi_add_logstorestandardlogid_type_to_table($dbman, $tablename) {
    // Select table.
    $table = new xmldb_table($tablename);

    // Conditionally add field logstorestandardlogid to table.
    $field = new xmldb_field('logstorestandardlogid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $index = new xmldb_index("logstorestandardlogid", XMLDB_INDEX_NOTUNIQUE, ['logstorestandardlogid']);
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    // Conditionally add field type to table.
    $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    if (!$dbman->field_exists($table, $field)) {
        $dbman->add_field($table, $field);
    }

    $index = new xmldb_index("type", XMLDB_INDEX_NOTUNIQUE, ['type']);
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }
}

 /**
  * Create new columns in the database.
  *
  * @param object $dbman     Database manipulation object.
  * @return void
  */
function logstore_xapi_create_logstoreid_and_type_columns($dbman) {
    logstore_xapi_add_logstorestandardlogid_type_to_table($dbman, 'logstore_xapi_log');
    logstore_xapi_add_logstorestandardlogid_type_to_table($dbman, 'logstore_xapi_failed_log');
}

 /**
  * Create xapi_notification table.
  *
  * @param object $dbman     Database manipulation object.
  * @param string $tablename The name of the table.
  * @return void
  */
function logstore_xapi_create_xapi_notification_table($dbman, $tablename) {
    // Define table to be created.
    $table = new xmldb_table($tablename);

    // Adding fields to table.
    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('failedlogid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('email', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

    // Adding keys to table.
    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

    // Adding indexes to table.
    $table->add_index('failedlogid', XMLDB_INDEX_NOTUNIQUE, ['failedlogid']);
    $table->add_index('email', XMLDB_INDEX_NOTUNIQUE, ['email']);
    $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

    // Conditionally launch create table.
    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
}

 /**
  * Determine what needs to be done for each upgrade step.
  *
  * @param string $oldversion Prior version of plugin during an upgrade.
  * @return bool
  */
function xmldb_logstore_xapi_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2015081001) {
        logstore_xapi_create_xapi_log_table($dbman, 'logstore_xapi_log');
        upgrade_plugin_savepoint(true, 2015081001, 'logstore', 'xapi');
    }

    if ($oldversion < 2018082100) {
        logstore_xapi_create_xapi_log_table($dbman, 'logstore_xapi_failed_log');
        upgrade_plugin_savepoint(true, 2018082100, 'logstore', 'xapi');
    }

    if ($oldversion < 2020032700) {
        // Define field errortype to be added to logstore_xapi_failed_log.
        $table = new xmldb_table('logstore_xapi_failed_log');
        $field = new xmldb_field('errortype', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'realuserid');

        // Conditionally launch add field errortype.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field response to be added to logstore_xapi_failed_log.
        $field = new xmldb_field('response', XMLDB_TYPE_TEXT, null, null, null, null, null, 'errortype');

        // Conditionally launch add field response.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // The xAPI savepoint reached.
        upgrade_plugin_savepoint(true, 2020032700, 'logstore', 'xapi');
    }

    if ($oldversion < 2020041506) {
        logstore_xapi_create_xapi_notification_table($dbman, 'logstore_xapi_notif_sent_log');
        upgrade_plugin_savepoint(true, 2020041506, 'logstore', 'xapi');
    }

    if ($oldversion < 2020050100) {
        logstore_xapi_create_logstoreid_and_type_columns($dbman);
        upgrade_plugin_savepoint(true, 2020050100, 'logstore', 'xapi');
    }

    if ($oldversion < 2020050600) {
        logstore_xapi_create_xapi_sent_log_table($dbman, "logstore_xapi_sent_log");

        // The xAPI savepoint reached.
        upgrade_plugin_savepoint(true, 2020050600, 'logstore', 'xapi');
    }

    if ($oldversion < 2026102301) {
        // Only MAX(failedlogid) is ever read, so the recipients' email addresses are not kept.
        $table = new xmldb_table('logstore_xapi_notif_sent_log');
        $index = new xmldb_index('email', XMLDB_INDEX_NOTUNIQUE, ['email']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        $field = new xmldb_field('email');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026102301, 'logstore', 'xapi');
    }

    if ($oldversion < 2026102601) {
        // The error report filters and groups failed events by a hash of the response, as the
        // response is a TEXT column (slow to compare and group, and not comparable at all on
        // some databases), and indexes errortype, which it filters on.
        $table = new xmldb_table('logstore_xapi_failed_log');
        $field = new xmldb_field('responsehash', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'response');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        foreach (['errortype', 'responsehash'] as $column) {
            $index = new xmldb_index($column, XMLDB_INDEX_NOTUNIQUE, [$column]);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // Hashing existing rows is one update per row, which could hold up the upgrade of a site
        // with a large failed log, so an ad-hoc task does it in batches.
        \core\task\manager::queue_adhoc_task(new \logstore_xapi\task\hash_failed_responses(), true);

        upgrade_plugin_savepoint(true, 2026102601, 'logstore', 'xapi');
    }

    return true;
}
