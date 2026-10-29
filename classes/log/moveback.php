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

require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Class for queueing events again: failed events, or standard log rows from the historic report.
 *
 * @package     logstore_xapi
 * @subpackage  log
 * @author      Záborski László <laszlo.zaborski@learningpool.com>
 * @copyright   2020 Learning Pool Ltd (http://learningpool.com)
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moveback {
    /**
     * logstore database names.
     */
    const LOGSTORE_NEW = "logstore_xapi_log";

    /**
     * The event columns, which the queue, the failed log and the standard log share.
     */
    const COLUMNS = [
        'eventname', 'component', 'action', 'target', 'objecttable', 'objectid', 'crud', 'edulevel',
        'contextid', 'contextlevel', 'contextinstanceid', 'userid', 'courseid', 'relateduserid',
        'anonymous', 'other', 'timecreated', 'origin', 'ip', 'realuserid',
    ];

    /**
     * List of event IDs.
     *
     * @var array
     */
    protected $eventids = [];

    /**
     * Whether the ids are standard log rows (the historic report) rather than failed events.
     *
     * @var bool
     */
    protected $historical = false;

    /**
     * Constructor.
     *
     * @param array $eventids The ids of the failed events, or of the standard log rows.
     * @param bool $historical Whether the ids are standard log rows.
     */
    public function __construct(array $eventids, $historical = false) {
        $this->eventids = array_values($eventids);
        $this->historical = (bool) $historical;
    }

    /**
     * Queue the events, all together.
     *
     * Failed events are queued as failed-type events, without their error, and removed from the
     * failed log. Standard log rows are queued as historic events linked to their row, unless one
     * is already queued, and failed copies of them are removed from the failed log.
     *
     * @return bool Whether any of the events exist.
     */
    public function execute() {
        global $DB;

        if (empty($this->eventids)) {
            return false;
        }

        $source = $this->historical ? XAPI_REPORT_SOURCE_HISTORICAL : XAPI_REPORT_SOURCE_FAILED;
        [$insql, $params] = $DB->get_in_or_equal($this->eventids, SQL_PARAMS_NAMED, 'mb');
        if (!$DB->record_exists_select($source, "id $insql", $params)) {
            return false;
        }

        $columns = implode(', ', self::COLUMNS);
        $sourcecolumns = implode(', ', array_map(function ($column) {
            return 's.' . $column;
        }, self::COLUMNS));

        $transaction = $DB->start_delegated_transaction();
        if ($this->historical) {
            // Rows already queued are left out. They are found first: MySQL doesn't allow the
            // table an INSERT writes to in a subquery of its SELECT.
            $queued = $DB->get_fieldset_select(
                self::LOGSTORE_NEW,
                'logstorestandardlogid',
                "logstorestandardlogid $insql",
                $params
            );
            $toqueue = array_values(array_diff($this->eventids, $queued));
            if (!empty($toqueue)) {
                [$queuesql, $queueparams] = $DB->get_in_or_equal($toqueue, SQL_PARAMS_NAMED, 'mbq');
                $DB->execute(
                    "INSERT INTO {" . self::LOGSTORE_NEW . "} ($columns, logstorestandardlogid, type)
                     SELECT $sourcecolumns, s.id, :type
                       FROM {" . XAPI_REPORT_SOURCE_HISTORICAL . "} s
                      WHERE s.id $queuesql",
                    $queueparams + ['type' => XAPI_IMPORT_TYPE_HISTORIC]
                );
            }
            $DB->delete_records_select(XAPI_REPORT_SOURCE_FAILED, "logstorestandardlogid $insql", $params);
        } else {
            $DB->execute(
                "INSERT INTO {" . self::LOGSTORE_NEW . "} ($columns, logstorestandardlogid, type)
                 SELECT $sourcecolumns, s.logstorestandardlogid, :type
                   FROM {" . XAPI_REPORT_SOURCE_FAILED . "} s
                  WHERE s.id $insql",
                $params + ['type' => XAPI_IMPORT_TYPE_FAILED]
            );
            $DB->delete_records_select(XAPI_REPORT_SOURCE_FAILED, "id $insql", $params);
        }
        $transaction->allow_commit();

        return true;
    }
}
