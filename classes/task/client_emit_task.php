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

namespace logstore_xapi\task;

defined('MOODLE_INTERNAL') || die();

use logstore_xapi\client\queue_processor;

require_once(dirname(__DIR__, 2) . '/lib.php');

/**
 * Emit queued client-side statements to the LRS.
 *
 * @package   logstore_xapi
 * @copyright 2026 Lachlan Keown <lachlankeown@gmail.com>, NZ ADL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_emit_task extends \core\task\scheduled_task {
    /**
     * Get a descriptive task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskclientemit', 'logstore_xapi');
    }

    /**
     * Process one client statement batch.
     *
     * Prunes aged idempotency and dead-letter rows first so neither table
     * grows without bound, then delivers the live and retry queues unless
     * client-side capture is disabled.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $cutoff = time() - XAPI_CLIENT_SENT_RETENTION_SECS;
        $prunedsent = $DB->count_records_select('logstore_xapi_client_sent', 'timecreated < ?', [$cutoff]);
        if ($prunedsent > 0) {
            $DB->delete_records_select('logstore_xapi_client_sent', 'timecreated < ?', [$cutoff]);
        }
        $pruneddead = $DB->count_records_select('logstore_xapi_client_log',
            'type = ? AND attempts >= ? AND timecreated < ?',
            [XAPI_IMPORT_TYPE_FAILED, XAPI_CLIENT_QUEUE_MAX_ATTEMPTS, $cutoff]);
        if ($pruneddead > 0) {
            $DB->delete_records_select('logstore_xapi_client_log',
                'type = ? AND attempts >= ? AND timecreated < ?',
                [XAPI_IMPORT_TYPE_FAILED, XAPI_CLIENT_QUEUE_MAX_ATTEMPTS, $cutoff]);
        }
        mtrace('logstore_xapi client_emit_task: pruned ' . $prunedsent . ' sent and ' .
            $pruneddead . ' exhausted client statements.');

        if (!get_config('logstore_xapi', 'captureclientsideevents')) {
            mtrace('logstore_xapi client_emit_task: client-side capture is disabled, skipping queues.');
            return;
        }

        $batchsize = (int)get_config('logstore_xapi', 'maxbatchsize');
        if ($batchsize <= 0) {
            $batchsize = 30;
        }

        $live = queue_processor::process_queued($batchsize, XAPI_IMPORT_TYPE_LIVE);
        mtrace('logstore_xapi client_emit_task: ' . $live['sent'] . ' sent, ' .
            $live['failed'] . ' failed, ' . ($live['filtered'] ?? 0) . ' filtered, ' .
            ($live['exhausted'] ?? 0) . ' exhausted from live queue.');

        // Failed records are retried by the same task. This keeps the minimal
        // implementation from requiring a second queue or failed-task class.
        $failed = queue_processor::process_queued($batchsize, XAPI_IMPORT_TYPE_FAILED);
        mtrace('logstore_xapi client_emit_task: ' . $failed['sent'] . ' sent, ' .
            $failed['failed'] . ' failed, ' . ($failed['filtered'] ?? 0) . ' filtered, ' .
            ($failed['exhausted'] ?? 0) . ' exhausted from retry queue.');
    }
}
