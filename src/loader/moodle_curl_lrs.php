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
 * Loads Moodle curl for communication with LRS.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\loader\moodle_curl_lrs;
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (!isset($CFG)) {
    $CFG = (object) [ 'libdir' => 'utils' ];
}
require_once($CFG->libdir . '/filelib.php');

use src\loader\utils;

/**
 * Build the cURL options used for an LRS statement request.
 *
 * TLS peer verification is on unless an administrator has explicitly turned it
 * off. Note that this cannot be left to Moodle's \curl wrapper, which defaults
 * CURLOPT_SSL_VERIFYPEER to 0 (see curl::resetopt() in lib/filelib.php), so the
 * option is always set explicitly here.
 *
 * @param array $config An array of configuration settings.
 * @param string $url The statements endpoint.
 * @param string $auth The base64 encoded basic auth credentials.
 * @param string $postdata The JSON encoded statements.
 * @return array A map of cURL option constant => value.
 */
function build_curl_options(array $config, string $url, string $auth, string $postdata): array {
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_POSTFIELDS => $postdata,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $auth,
            'X-Experience-API-Version: 1.0.3',
            'Content-Type: application/json',
        ],
    ];

    // A private CA bundle keeps verification fully intact, so it is preferred
    // over turning verification off and is applied independently of it.
    if (!empty($config['lrs_ssl_cabundle'])) {
        $options[CURLOPT_CAINFO] = $config['lrs_ssl_cabundle'];
    }

    // Last resort escape hatch. Both checks are dropped together so that the
    // setting does what its name says; a half-disabled state would still fail
    // for self-signed certificates whose common name does not match.
    if (isset($config['lrs_ssl_verification']) && empty($config['lrs_ssl_verification'])) {
        $options[CURLOPT_SSL_VERIFYPEER] = false;
        $options[CURLOPT_SSL_VERIFYHOST] = 0;
    }

    return $options;
}

/**
 * Load data necessary to send statements to LRS.
 *
 * @param array $config An array of configuration settings.
 * @param array $events An array of events.
 * @return array
 */
function load(array $config, array $events) {

    $sendhttpstatements = function (array $config, array $statements) {
        $endpoint = $config['lrs_endpoint'];
        $username = $config['lrs_username'];
        $password = $config['lrs_password'];

        $url = utils\correct_endpoint($endpoint) . '/statements';
        $auth = base64_encode($username . ':' . $password);
        $postdata = json_encode($statements);

        if ($postdata === false) {
            throw new \Exception('JSON encode error: ' . json_last_error_msg());
        }

        $request = curl_init();
        curl_setopt_array($request, build_curl_options($config, $url, $auth, $postdata));

        $responsetext = curl_exec($request);
        $responsecode = curl_getinfo($request, CURLINFO_RESPONSE_CODE);

        // A transport level failure (of which a rejected TLS certificate is the
        // most likely) yields false with no response code, so report cURL's own
        // error rather than storing an empty message in the failed log.
        if ($responsetext === false) {
            throw new \Exception('cURL error: ' . curl_error($request), curl_errno($request));
        }

        if ($responsecode !== 200) {
            throw new \Exception($responsetext, $responsecode);
        }
    };
    return utils\load_in_batches($config, $events, $sendhttpstatements);
}
