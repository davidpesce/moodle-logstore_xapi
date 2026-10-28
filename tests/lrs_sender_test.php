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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/src/autoload.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');
require_once(__DIR__ . '/fixtures/mock_lrs.php');

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Unit tests for sending statements to the LRS with \core\http_client.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\loader\moodle_curl_lrs\load
 * @covers    ::\src\loader\moodle_curl_lrs\get_client_settings
 * @covers    ::\src\loader\moodle_curl_lrs\get_proxy
 */
final class lrs_sender_test extends \advanced_testcase {
    use mock_lrs;

    /**
     * Set up an LRS that records each request.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->lrs = new MockHandler();
        \cache::make('logstore_xapi', 'oauth2token')->purge();
    }

    /**
     * Statements are POSTed in one batch to the statements resource, with xAPI headers, Basic
     * auth, TLS verification and timeouts. The LRS on 127.0.0.1:8080 is not blocked.
     *
     * @return void
     */
    public function test_statements_are_posted(): void {
        $this->answer(new Response(200, [], '["a","b"]'));

        $loaded = $this->send($this->events(2));

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0]['request'];
        $options = $this->requests[0]['options'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('http://127.0.0.1:8080/xapi/statements', (string) $request->getUri());
        $this->assertSame('Basic ' . base64_encode('key:secret'), $request->getHeaderLine('Authorization'));
        $this->assertSame('1.0.3', $request->getHeaderLine('X-Experience-API-Version'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(
            [['verb' => ['id' => 'http://example.org/verb/1']], ['verb' => ['id' => 'http://example.org/verb/2']]],
            json_decode((string) $request->getBody(), true)
        );
        $this->assertTrue($options['verify']);
        $this->assertSame(10, $options['connect_timeout']);
        $this->assertSame(60, $options['timeout']);
        $this->assertSame([true, true], array_column($loaded, 'loaded'));
    }

    /**
     * A CA bundle is used to verify the certificate, unless verification is turned off.
     *
     * @return void
     */
    public function test_tls_settings(): void {
        $this->answer(new Response(200));
        $this->answer(new Response(200));

        $this->send($this->events(1), ['lrs_ssl_cabundle' => '/etc/ssl/private-ca.pem']);
        $this->send($this->events(1), ['lrs_ssl_cabundle' => '/etc/ssl/private-ca.pem', 'lrs_ssl_verification' => '0']);

        $this->assertSame('/etc/ssl/private-ca.pem', $this->requests[0]['options']['verify']);
        $this->assertFalse($this->requests[1]['options']['verify']);
    }

    /**
     * The proxy option of a request sent with the given proxy settings.
     *
     * @param array $proxysettings $CFG proxy settings.
     * @param string $endpoint The LRS endpoint.
     * @return string|null The proxy option, or null when the request has none.
     */
    private function get_request_proxy(array $proxysettings, string $endpoint = 'https://lrs.example.net/xapi/'): ?string {
        global $CFG;
        $this->resetAfterTest();
        foreach ($proxysettings as $name => $value) {
            $CFG->$name = $value;
        }
        $this->answer(new Response(200));

        $this->send($this->events(1), ['lrs_endpoint' => $endpoint]);

        return end($this->requests)['options']['proxy'] ?? null;
    }

    /**
     * The site's proxy is used, with its credentials (as user:password@, encoded) and type.
     *
     * @return void
     */
    public function test_site_proxy_is_used(): void {
        $proxy = ['proxyhost' => 'proxy.example.org', 'proxyport' => 3128, 'proxytype' => 'HTTP'];

        $this->assertNull($this->get_request_proxy([]));
        $this->assertSame('http://proxy.example.org:3128', $this->get_request_proxy($proxy));
        $this->assertSame(
            'http://ann:p%40ss%3Aword@proxy.example.org:3128',
            $this->get_request_proxy($proxy + ['proxyuser' => 'ann', 'proxypassword' => 'p@ss:word'])
        );
        $this->assertSame(
            'socks5://proxy.example.org:3128',
            $this->get_request_proxy(['proxytype' => 'SOCKS5', 'proxyuser' => '', 'proxypassword' => ''] + $proxy)
        );
    }

    /**
     * An LRS in "Proxy bypass hosts" is reached without the proxy, whatever form the entry
     * takes: an IP range, a *.domain pattern or an exact host.
     *
     * @return void
     */
    public function test_proxy_bypass(): void {
        $proxy = [
            'proxyhost' => 'proxy.example.org',
            'proxyport' => 3128,
            'proxytype' => 'HTTP',
            'proxyuser' => '',
            'proxypassword' => '',
            'proxybypass' => '192.168.0.0/16,*.internal.example.org,lrs.example.com',
        ];
        $endpoints = [
            'http://192.168.1.20:8080/xapi/' => '',
            'https://lrs.internal.example.org/xapi/' => '',
            'https://lrs.example.com/xapi/' => '',
            'https://lrs.example.net/xapi/' => 'http://proxy.example.org:3128',
        ];
        foreach ($endpoints as $endpoint => $expected) {
            $this->assertSame($expected, $this->get_request_proxy($proxy, $endpoint), $endpoint);
        }
    }

    /**
     * An error response fails the batch with the response body and status.
     *
     * @return void
     */
    public function test_error_response_fails_the_batch(): void {
        $this->answer(new Response(400, [], 'Invalid statement'));

        $loaded = $this->send($this->events(2));

        $this->assertSame([false, false], array_column($loaded, 'loaded'));
        $this->assertSame('Invalid statement', $loaded[0]['event']->response);
        $this->assertEquals(400, $loaded[0]['event']->errortype);
    }

    /**
     * A transport failure leaves the batch for a later run, with cURL's error and error number.
     *
     * @return void
     */
    public function test_transport_failure(): void {
        $this->answer(function (RequestInterface $request) {
            throw new ConnectException(
                'cURL error 7: Failed to connect',
                $request,
                null,
                ['errno' => 7, 'error' => 'Failed to connect to 127.0.0.1 port 8080']
            );
        });

        $loaded = $this->send($this->events(1));

        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertSame('cURL error: Failed to connect to 127.0.0.1 port 8080', $loaded[0]['event']->response);
        $this->assertEquals(7, $loaded[0]['event']->errortype);
    }

    /**
     * Refused credentials, a wrong endpoint, a request to wait and LRS failures leave the batch
     * for a later run; other errors are about the statements, and fail it.
     *
     * @return void
     */
    public function test_status_handling(): void {
        $expected = [
            401 => XAPI_LOADED_RETRY, 403 => XAPI_LOADED_RETRY, 404 => XAPI_LOADED_RETRY,
            408 => XAPI_LOADED_RETRY, 429 => XAPI_LOADED_RETRY, 500 => XAPI_LOADED_RETRY,
            502 => XAPI_LOADED_RETRY, 503 => XAPI_LOADED_RETRY, 504 => XAPI_LOADED_RETRY,
            400 => false, 422 => false,
        ];
        foreach ($expected as $status => $outcome) {
            $this->answer(new Response($status, [], 'Error ' . $status));
            $loaded = $this->send($this->events(1));

            $this->assertSame($outcome, $loaded[0]['loaded'], "HTTP $status");
            $this->assertEquals($status, $loaded[0]['event']->errortype);
        }
    }

    /**
     * Without an endpoint, nothing is sent and the batch waits until one is set.
     *
     * @return void
     */
    public function test_no_endpoint(): void {
        $loaded = $this->send($this->events(1), ['lrs_endpoint' => '']);

        $this->assertCount(0, $this->requests);
        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertSame('The LRS endpoint is not set.', $loaded[0]['event']->response);
    }

    /**
     * A batch with no statements is not sent.
     *
     * @return void
     */
    public function test_empty_batch_is_not_sent(): void {
        $loaded = $this->send($this->events(2, 0));

        $this->assertCount(0, $this->requests);
        $this->assertSame([true, true], array_column($loaded, 'loaded'));
    }

    /**
     * Invalid UTF-8 in a statement is replaced, rather than failing the whole batch.
     *
     * @return void
     */
    public function test_invalid_utf8_is_replaced(): void {
        $this->answer(new Response(200));
        $events = $this->events(1);
        $events[0]['statements'][0]['result'] = ['response' => "caf\xE9"];

        $loaded = $this->send($events);

        $this->assertTrue($loaded[0]['loaded']);
        $body = json_decode((string) $this->requests[0]['request']->getBody(), true);
        $this->assertSame("caf\u{FFFD}", $body[0]['result']['response']);
    }
}
