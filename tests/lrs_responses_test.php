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
 * Unit tests for what happens to a batch after each kind of answer from the LRS, or none.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\loader\moodle_curl_lrs\load
 * @covers    ::\src\loader\moodle_curl_lrs\post
 * @covers    ::\src\loader\moodle_curl_lrs\is_retry_status
 */
final class lrs_responses_test extends \advanced_testcase {
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
     * A redirect isn't followed: the POST would become a GET that the LRS answers with 200, and
     * the statements would be counted as sent. The batch waits for the endpoint to be fixed.
     *
     * @return void
     */
    public function test_redirect_is_not_followed(): void {
        $this->answer(new Response(301, ['Location' => 'http://127.0.0.1:8080/xapi/statements/']));

        $loaded = $this->send($this->events(1));

        $this->assertCount(1, $this->requests);
        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertEquals(301, $loaded[0]['event']->errortype);
    }

    /**
     * An error that stops the request before it is sent, such as a CA bundle file that isn't
     * there, leaves the batch for a later run instead of failing it.
     *
     * @return void
     */
    public function test_request_error_is_retried_later(): void {
        $this->answer(function () {
            throw new \InvalidArgumentException('SSL CA bundle not found: /etc/ssl/missing.pem');
        });
        $loaded = $this->send($this->events(1));
        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertSame('SSL CA bundle not found: /etc/ssl/missing.pem', $loaded[0]['event']->response);

        // The same with Guzzle's own handler, which checks the bundle before connecting.
        $loaded = $this->send($this->events(1), [
            'lrs_mock_handler' => null,
            'lrs_endpoint' => 'https://127.0.0.1:1/xapi/',
            'lrs_ssl_cabundle' => '/etc/ssl/missing.pem',
        ]);
        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertStringContainsString('SSL CA bundle not found', $loaded[0]['event']->response);
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
}
