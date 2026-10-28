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

namespace logstore_xapi;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A mock LRS for the tests of sending statements: Guzzle's mock handler, recording each request.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait mock_lrs {
    /** @var MockHandler The LRS. */
    private $lrs;

    /** @var array[] Each request the LRS received, with its request options. */
    private $requests = [];

    /**
     * Queue the LRS's next answer.
     *
     * @param callable|Response $answer A response, or a function of the request that returns or throws.
     * @return void
     */
    private function answer($answer): void {
        $this->lrs->append(function (RequestInterface $request, array $options) use ($answer) {
            $this->requests[] = ['request' => $request, 'options' => $options];
            return is_callable($answer) ? $answer($request) : $answer;
        });
    }

    /**
     * Loader config, sending to the mock LRS.
     *
     * @param array $settings Settings to change.
     * @return array
     */
    private function get_config(array $settings = []): array {
        return array_merge([
            'log_error' => function () {
            },
            'log_info' => function () {
            },
            'lrs_endpoint' => 'http://127.0.0.1:8080/xapi/',
            'lrs_username' => 'key',
            'lrs_password' => 'secret',
            'lrs_max_batch_size' => 30,
            'lrs_resend_failed_batches' => '0',
            'lrs_ssl_verification' => '1',
            'lrs_ssl_cabundle' => '',
            'lrs_mock_handler' => $this->lrs,
        ], $settings);
    }

    /**
     * Transformed events, each with the same number of statements.
     *
     * @param int $count The number of events.
     * @param int $statements The number of statements of each event.
     * @return array
     */
    private function events(int $count, int $statements = 1): array {
        $events = [];
        for ($i = 1; $i <= $count; $i++) {
            $events[] = [
                'event' => (object) ['id' => $i],
                'statements' => array_fill(0, $statements, ['verb' => ['id' => 'http://example.org/verb/' . $i]]),
                'transformed' => true,
            ];
        }
        return $events;
    }

    /**
     * Send events to the mock LRS.
     *
     * @param array $events Transformed events.
     * @param array $settings Settings to change.
     * @return array The loaded events.
     */
    private function send(array $events, array $settings = []): array {
        return \src\loader\moodle_curl_lrs\load($this->get_config($settings), $events);
    }
}
