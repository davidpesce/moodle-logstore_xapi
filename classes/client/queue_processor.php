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
 * Client-side xAPI statement queue processor.
 *
 * @package   logstore_xapi
 * @copyright 2026 Lachlan Keown <lachlankeown@gmail.com>, NZ ADL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\client;

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__DIR__, 2) . '/src/autoload.php');
require_once(dirname(__DIR__, 2) . '/lib.php');

/**
 * Queues, validates and delivers client-side xAPI statements.
 */
class queue_processor {
    /**
     * Build LRS loader configuration for client statements.
     *
     * @return array
     */
    public static function get_loader_config(): array {
        return [
            'lrs_endpoint' => get_config('logstore_xapi', 'endpoint') ?: '',
            'lrs_username' => get_config('logstore_xapi', 'username') ?: '',
            'lrs_password' => get_config('logstore_xapi', 'password') ?: '',
            'lrs_auth_method' => get_config('logstore_xapi', 'authmethod') ?: 'basic',
            'lrs_oauth2_token_endpoint' => get_config('logstore_xapi', 'oauth2tokenendpoint') ?: '',
            'lrs_oauth2_scope' => get_config('logstore_xapi', 'oauth2scope') ?: '',
            'lrs_max_batch_size' => get_config('logstore_xapi', 'maxbatchsize') ?: 30,
            'lrs_resend_failed_batches' => get_config('logstore_xapi', 'resendfailedbatches') ?: false,
            'lrs_ssl_verification' => get_config('logstore_xapi', 'sslverification') === false ? true :
                (bool)get_config('logstore_xapi', 'sslverification'),
            'lrs_ssl_cabundle' => get_config('logstore_xapi', 'sslcabundle') ?: '',
            'log_error' => function ($message = '') {
                mtrace($message);
            },
            'log_info' => function ($message = '') {
                mtrace($message);
            },
        ];
    }

    /**
     * Whether learner response text may be included in client statements.
     *
     * An unset value preserves the historical default of sending content.
     *
     * @return bool
     */
    public static function is_sending_content(): bool {
        $sendcontent = get_config('logstore_xapi', 'sendcontent');
        return $sendcontent === false || !empty($sendcontent);
    }

    /**
     * Remove learner response text from a client statement, including nested SubStatements.
     *
     * Scores, success and other result properties are retained.
     *
     * @param array $statement xAPI statement, modified in place.
     * @return bool Whether any response content was removed.
     */
    public static function remove_response_content(array &$statement): bool {
        $changed = false;
        if (isset($statement['result']) && is_array($statement['result']) &&
                array_key_exists('response', $statement['result'])) {
            unset($statement['result']['response']);
            $changed = true;
        }

        if (($statement['object']['objectType'] ?? null) === 'SubStatement' &&
                isset($statement['object']) && is_array($statement['object'])) {
            $changed = self::remove_response_content($statement['object']) || $changed;
        }
        return $changed;
    }

    /**
     * Build an xAPI actor for an authenticated user, mirroring the plugin's
     * actor identification settings.
     *
     * @param \stdClass $user Moodle user.
     * @param array $config Actor configuration.
     * @return array
     */
    public static function get_actor_for_user(\stdClass $user, array $config): array {
        $actor = [];

        if (!empty($config['send_name'])) {
            $actor['name'] = \src\transformer\utils\get_full_name($user);
        }

        $hasvalidemail = filter_var($user->email ?? '', FILTER_VALIDATE_EMAIL);
        if (!empty($config['send_mbox']) && $hasvalidemail) {
            $actor['mbox'] = 'mailto:' . $user->email;
            return $actor;
        }

        $homepage = !empty($config['account_homepage']) ? $config['account_homepage'] : ($config['app_url'] ?? '');
        if (!empty($config['send_username'])) {
            $actor['account'] = [
                'homePage' => $homepage,
                'name' => $user->username ?? '',
            ];
            return $actor;
        }

        $actor['account'] = [
            'homePage' => $config['app_url'] ?? '',
            'name' => (string)($user->id ?? ''),
        ];
        return $actor;
    }

    /**
     * Acquire the client queue lock.
     *
     * @param string $resource Lock resource identifier.
     * @param int $timeout Time in seconds to wait for the lock.
     * @return \core\lock\lock|false The lock when acquired, false otherwise.
     */
    public static function get_queue_lock(string $resource, int $timeout) {
        $lockfactory = \core\lock\lock_config::get_lock_factory('logstore_xapi');
        return $lockfactory->get_lock($resource, $timeout);
    }

    /**
     * Send a list of xAPI statements through the existing LRS loader.
     *
     * @param array $config Loader configuration.
     * @param array $statements xAPI statements.
     * @param array $records Queue records matching the statements.
     * @return array Results matching the records by numeric index.
     */
    public static function send_statements(array $config, array $statements, array $records): array {
        $events = [];
        foreach ($statements as $index => $statement) {
            $events[] = [
                'event' => $records[$index],
                'statements' => [$statement],
                'transformed' => true,
            ];
        }

        $loaded = \src\loader\moodle_curl_lrs\load($config, $events);
        return array_map(function ($result) {
            return [
                'loaded' => $result['loaded'] === true,
                'errortype' => ($result['event']->errortype ?? 0) ?: XAPI_REPORT_ERRORTYPE_NETWORK,
                'response' => $result['event']->response ?? '',
            ];
        }, $loaded);
    }

    /**
     * Process a batch of client queue records.
     *
     * @param array $records Queue records.
     * @return array Processing summary with per-record delivery results.
     */
    public static function process(array $records): array {
        global $DB;

        $summary = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'filtered' => 0,
            'exhausted' => 0,
            'results' => [],
        ];

        if (empty($records)) {
            return $summary;
        }

        $statements = [];
        $recordmap = [];
        foreach ($records as $record) {
            // A concurrent worker may have delivered this record between extraction
            // and processing, so only act on records that are still queued. Deleted
            // records were sent successfully elsewhere and can be counted as sent.
            if (!$DB->record_exists('logstore_xapi_client_log', ['id' => $record->id])) {
                $summary['processed']++;
                $summary['sent']++;
                $summary['results'][] = [
                    'id' => $record->id,
                    'success' => true,
                    'errortype' => 0,
                    'response' => '',
                ];
                continue;
            }

            // Re-validate stored payloads: a row written before hardening,
            // or via a future caller, must not be able to exhaust the worker.
            $error = null;
            $statement = null;
            if (\logstore_xapi_validate_client_statement_json($record->statement ?? '', $error)) {
                $statement = json_decode($record->statement, true, XAPI_CLIENT_STATEMENT_MAX_DEPTH + 1);
            }
            if (json_last_error() !== JSON_ERROR_NONE ||
                    !\logstore_xapi_validate_client_statement($statement, $error, $record->statement ?? '')) {
                $record->errortype = XAPI_REPORT_ERRORTYPE_TRANSFORM;
                $record->response = $error ?: 'Stored client statement is not valid JSON.';
                \logstore_xapi_update_client_event_failure($record);
                $summary['processed']++;
                $summary['failed']++;
                $summary['results'][] = [
                    'id' => $record->id,
                    'success' => false,
                    'errortype' => XAPI_REPORT_ERRORTYPE_TRANSFORM,
                    'response' => $record->response,
                ];
                continue;
            }
            // The privacy setting can be changed after a statement was queued. Sanitize the
            // stored payload as well as the outbound statement before any later retry.
            if (!self::is_sending_content() && self::remove_response_content($statement)) {
                $record->statement = json_encode($statement, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $DB->set_field('logstore_xapi_client_log', 'statement', $record->statement, ['id' => $record->id]);
            }
            if (!verb_policy::is_enabled($statement['verb']['id'] ?? '')) {
                // Verb disabled after the statement was queued: drop silently
                // without sending to the LRS.
                $DB->delete_records('logstore_xapi_client_log', ['id' => $record->id]);
                $summary['processed']++;
                $summary['filtered']++;
                $summary['results'][] = [
                    'id' => $record->id,
                    'success' => true,
                    'filtered' => true,
                    'errortype' => 0,
                    'response' => '',
                ];
                continue;
            }
            if ((int)($record->type ?? XAPI_IMPORT_TYPE_LIVE) === XAPI_IMPORT_TYPE_FAILED &&
                    (int)($record->attempts ?? 0) >= XAPI_CLIENT_QUEUE_MAX_ATTEMPTS) {
                // Retry budget spent: keep the row for inspection but stop
                // spending LRS round-trips on it. Long-dead rows are pruned
                // by client_emit_task.
                $summary['processed']++;
                $summary['exhausted']++;
                $summary['results'][] = [
                    'id' => $record->id,
                    'success' => false,
                    'exhausted' => true,
                    'errortype' => (int)($record->errortype ?? 0),
                    'response' => $record->response ?? '',
                ];
                continue;
            }
            $recordmap[] = $record;
            $statements[] = $statement;
        }

        if (empty($statements)) {
            return $summary;
        }

        $results = self::send_statements(self::get_loader_config(), $statements, $recordmap);
        foreach ($results as $index => $result) {
            $record = $recordmap[$index];
            $summary['processed']++;
            if ($result['loaded']) {
                \logstore_xapi_add_client_event_to_sent_log($record);
                $DB->delete_records('logstore_xapi_client_log', ['id' => $record->id]);
                $summary['sent']++;
            } else {
                $record->errortype = $result['errortype'];
                $record->response = $result['response'];
                \logstore_xapi_update_client_event_failure($record);
                $summary['failed']++;
            }
            $summary['results'][] = [
                'id' => $record->id,
                'success' => $result['loaded'],
                'errortype' => $result['errortype'],
                'response' => $result['response'],
            ];
        }

        return $summary;
    }

    /**
     * Process a queued batch of client statements, protected by the queue lock.
     *
     * The lock is held while records are extracted and delivered so that two
     * scheduled task runs (for example on different cron hosts) cannot send the
     * same batch twice.
     *
     * @param int $batchsize Maximum number of records to process.
     * @param int $type Client queue type.
     * @return array Processing summary with per-record delivery results.
     */
    public static function process_queued(int $batchsize, int $type): array {
        $lock = self::get_queue_lock('client_queue_' . ((int)$type), 5);
        if (!$lock) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'filtered' => 0, 'exhausted' => 0,
                'skipped' => true, 'results' => []];
        }

        try {
            $records = \logstore_xapi_extract_client_events($batchsize, $type);
            return self::process($records);
        } finally {
            $lock->release();
        }
    }

    /**
     * Process given client records immediately, protected by the queue lock.
     *
     * @param array $records Queue records.
     * @return array Processing summary with per-record delivery results.
     */
    public static function process_records(array $records): array {
        $lock = self::get_queue_lock('client_queue_' . XAPI_IMPORT_TYPE_LIVE, 5);
        if (!$lock) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'filtered' => 0, 'exhausted' => 0,
                'skipped' => true, 'results' => []];
        }

        try {
            return self::process($records);
        } finally {
            $lock->release();
        }
    }
}
