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
        // The status is checked by the caller, so an error response is not an exception.
        'http_errors' => false,
    ];
    // Tests replace the network with a Guzzle mock handler.
    if (isset($config['lrs_mock_handler'])) {
        $settings['mock'] = $config['lrs_mock_handler'];
    }
    return $settings;
}

/**
 * The proxy for a request to the LRS, from the site's proxy settings.
 *
 * \core\http_client sets up the proxy too, but through Moodle 5.3 it gets two things wrong:
 * - It builds the URL of a proxy that needs a user name and password without ":" and "@"
 *   (MDL-88125), so authenticated proxies fail.
 * - It hands "Proxy bypass hosts" to Guzzle, which matches only exact host names and domain
 *   suffixes, not the IP ranges and *.domain patterns Moodle documents for the setting.
 *
 * So the proxy is set for each request here: the site's proxy, or an empty string (which turns
 * the proxy off) when the host is on the bypass list, as is_proxybypass() decides for \curl.
 *
 * @param string $url The request URL.
 * @return string|null The proxy URL, '' for no proxy, or null when the site has no proxy.
 */
function get_proxy(string $url): ?string {
    global $CFG;

    if (empty($CFG->proxyhost)) {
        return null;
    }
    if (is_proxybypass($url)) {
        return '';
    }

    $scheme = (($CFG->proxytype ?? '') === 'SOCKS5') ? 'socks5://' : 'http://';
    // As \curl does, credentials are used when both are set.
    $credentials = '';
    if (!empty($CFG->proxyuser) && !empty($CFG->proxypassword)) {
        $credentials = rawurlencode($CFG->proxyuser) . ':' . rawurlencode($CFG->proxypassword) . '@';
    }
    $port = empty($CFG->proxyport) ? '' : ':' . $CFG->proxyport;
    return $scheme . $credentials . $CFG->proxyhost . $port;
}

/**
 * POST a request, through the site's proxy unless the host is on the bypass list.
 *
 * @param \core\http_client $client The client.
 * @param string $url The URL.
 * @param array $options Guzzle request options.
 * @return \Psr\Http\Message\ResponseInterface
 * @throws \Exception With cURL's error and error number when there is no response.
 */
function post(\core\http_client $client, string $url, array $options) {
    $proxy = get_proxy($url);
    if (!is_null($proxy)) {
        $options['proxy'] = $proxy;
    }
    try {
        return $client->post($url, $options);
    } catch (TransferException $e) {
        // A transport failure (a refused connection, a timeout, a rejected TLS certificate)
        // has no response, so report cURL's own error and error number.
        $context = method_exists($e, 'getHandlerContext') ? $e->getHandlerContext() : [];
        throw new \Exception('cURL error: ' . ($context['error'] ?? $e->getMessage()), $context['errno'] ?? 0);
    }
}

/**
 * Is the LRS set up to use OAuth2 rather than Basic auth?
 *
 * @param array $config An array of configuration settings.
 * @return bool
 */
function uses_oauth2(array $config): bool {
    return ($config['lrs_auth_method'] ?? 'basic') === 'oauth2';
}

/**
 * The Authorization header for requests to the LRS.
 *
 * Basic auth sends the key and secret with every request. OAuth2 (the client credentials grant)
 * exchanges them, as client ID and client secret, for an access token at the identity provider's
 * token endpoint, and sends the token. Tokens are kept in the application cache until a minute
 * before they expire, so that each run, or in real-time mode each page request, doesn't ask for
 * a new one.
 *
 * @param \core\http_client $client The client.
 * @param array $config An array of configuration settings.
 * @return string
 */
function get_authorization(\core\http_client $client, array $config): string {
    if (!uses_oauth2($config)) {
        return get_basic_authorization($config);
    }
    $token = \cache::make('logstore_xapi', 'oauth2token')->get(get_token_cache_key($config));
    if ($token === false || $token['expires'] - time() < 60) {
        return renew_authorization($client, $config);
    }
    return 'Bearer ' . $token['access_token'];
}

/**
 * Get a new OAuth2 access token, cache it, and return the Authorization header for it.
 *
 * @param \core\http_client $client The client.
 * @param array $config An array of configuration settings.
 * @return string
 */
function renew_authorization(\core\http_client $client, array $config): string {
    $token = get_oauth2_token($client, $config, get_basic_authorization($config));
    \cache::make('logstore_xapi', 'oauth2token')->set(get_token_cache_key($config), $token);
    return 'Bearer ' . $token['access_token'];
}

/**
 * The Basic Authorization header for the key and secret.
 *
 * @param array $config An array of configuration settings.
 * @return string
 */
function get_basic_authorization(array $config): string {
    return 'Basic ' . base64_encode(($config['lrs_username'] ?? '') . ':' . ($config['lrs_password'] ?? ''));
}

/**
 * The cache key for an OAuth2 token: a change to any of the settings gets a new token.
 *
 * @param array $config An array of configuration settings.
 * @return string
 */
function get_token_cache_key(array $config): string {
    return sha1(implode("\n", [
        $config['lrs_oauth2_token_endpoint'] ?? '',
        $config['lrs_oauth2_scope'] ?? '',
        $config['lrs_username'] ?? '',
        $config['lrs_password'] ?? '',
    ]));
}

/**
 * Get an access token from the identity provider with the client credentials grant.
 *
 * The client ID and secret are sent with Basic auth (client_secret_basic, RFC 6749 2.3.1).
 * Any failure is reported as an authentication error (401), with the identity provider's answer.
 *
 * @param \core\http_client $client The client.
 * @param array $config An array of configuration settings.
 * @param string $basic The Basic Authorization header for the client ID and secret.
 * @return array The access_token, and when it expires (a Unix time).
 * @throws \Exception When no token is issued.
 */
function get_oauth2_token(\core\http_client $client, array $config, string $basic): array {
    $endpoint = $config['lrs_oauth2_token_endpoint'] ?? '';
    if ($endpoint === '') {
        throw new \Exception('OAuth2 token request failed: no token endpoint is set.', 401);
    }

    $form = ['grant_type' => 'client_credentials'];
    if (!empty($config['lrs_oauth2_scope'])) {
        $form['scope'] = $config['lrs_oauth2_scope'];
    }
    try {
        $response = post($client, $endpoint, [
            'form_params' => $form,
            'headers' => ['Authorization' => $basic, 'Accept' => 'application/json'],
        ]);
    } catch (\Exception $e) {
        throw new \Exception('OAuth2 token request failed: ' . $e->getMessage(), $e->getCode());
    }

    $body = (string) $response->getBody();
    $data = json_decode($body, true);
    $token = is_array($data) ? ($data['access_token'] ?? null) : null;
    if ($response->getStatusCode() !== 200 || !is_string($token) || $token === '') {
        throw new \Exception(
            'OAuth2 token request failed (HTTP ' . $response->getStatusCode() . '): ' . \core_text::substr($body, 0, 1000),
            401
        );
    }
    // Without expires_in (it is optional), the token is used for this run only.
    return [
        'access_token' => $token,
        'expires' => time() + (int) ($data['expires_in'] ?? 0),
    ];
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
    $authorization = null;

    $sendhttpstatements = function (array $config, array $statements) use ($client, $url, &$authorization) {
        // A batch where no event produced a statement has nothing to send.
        if (empty($statements)) {
            return;
        }

        $postdata = json_encode($statements, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($postdata === false) {
            throw new \Exception('JSON encode error: ' . json_last_error_msg());
        }

        $authorization = $authorization ?? get_authorization($client, $config);
        $send = function () use ($client, $url, $postdata, &$authorization) {
            return post($client, $url, [
                'body' => $postdata,
                'headers' => [
                    'Authorization' => $authorization,
                    'X-Experience-API-Version' => '1.0.3',
                    'Content-Type' => 'application/json',
                ],
            ]);
        };
        $response = $send();

        // An OAuth2 token can expire or be revoked before the time it was given for. Get a new
        // one and try again, once.
        if ($response->getStatusCode() === 401 && uses_oauth2($config)) {
            $authorization = renew_authorization($client, $config);
            $response = $send();
        }

        if ($response->getStatusCode() !== 200) {
            throw new \Exception((string) $response->getBody(), $response->getStatusCode());
        }
    };
    return utils\load_in_batches($config, $events, $sendhttpstatements);
}
