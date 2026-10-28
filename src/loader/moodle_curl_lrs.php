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
 * Sends statements to the LRS with Moodle's HTTP client.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\loader\moodle_curl_lrs;

use GuzzleHttp\Exception\TransferException;
use src\loader\utils;

/**
 * The \core\http_client settings for sending statements to the LRS.
 *
 * - The site's proxy settings apply: \core\http_client reads them.
 * - Moodle's blocked hosts and allowed ports (curlsecurityblockedhosts, curlsecurityallowedport)
 *   are not applied, as for core's own admin-configured backends (the Solr search engine, the
 *   Python machine learning backend). Only an administrator can set the endpoint, and an LRS
 *   often runs on an internal network or on a port such as 8080, which the default lists block.
 * - The TLS certificate is verified, against a private CA bundle when one is set, unless an
 *   administrator has turned verification off.
 * - Without limits, an unresponsive LRS blocks the emit task, or in real-time mode the page
 *   request that flushes the log buffer. The total allows for a full batch.
 *
 * @param array $config An array of configuration settings.
 * @return array
 */
function get_client_settings(array $config): array {
    $verify = empty($config['lrs_ssl_cabundle']) ? true : $config['lrs_ssl_cabundle'];
    if (isset($config['lrs_ssl_verification']) && empty($config['lrs_ssl_verification'])) {
        $verify = false;
    }

    $settings = [
        'ignoresecurity' => true,
        'connect_timeout' => 10,
        'timeout' => 60,
        'verify' => $verify,
        // The status is checked below, so an error response is not an exception.
        'http_errors' => false,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode(($config['lrs_username'] ?? '') . ':' . ($config['lrs_password'] ?? '')),
            'X-Experience-API-Version' => '1.0.3',
            'Content-Type' => 'application/json',
        ],
    ];
    // Tests replace the network with a Guzzle mock handler.
    if (isset($config['lrs_mock_handler'])) {
        $settings['mock'] = $config['lrs_mock_handler'];
    }
    return $settings;
}

/**
 * Load data necessary to send statements to LRS.
 *
 * @param array $config An array of configuration settings.
 * @param array $events An array of events.
 * @return array
 */
function load(array $config, array $events) {
    // One client for all the batches, so the connection to the LRS is reused.
    $client = new \core\http_client(get_client_settings($config));
    $url = utils\correct_endpoint($config['lrs_endpoint']) . '/statements';

    $sendhttpstatements = function (array $config, array $statements) use ($client, $url) {
        // A batch where no event produced a statement has nothing to send.
        if (empty($statements)) {
            return;
        }

        $postdata = json_encode($statements, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($postdata === false) {
            throw new \Exception('JSON encode error: ' . json_last_error_msg());
        }

        try {
            $response = $client->post($url, ['body' => $postdata]);
        } catch (TransferException $e) {
            // A transport failure (a refused connection, a timeout, a rejected TLS certificate)
            // has no response, so report cURL's own error and error number.
            $context = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];
            throw new \Exception('cURL error: ' . ($context['error'] ?? $e->getMessage()), $context['errno'] ?? 0);
        }

        if ($response->getStatusCode() !== 200) {
            throw new \Exception((string) $response->getBody(), $response->getStatusCode());
        }
    };
    return utils\load_in_batches($config, $events, $sendhttpstatements);
}
