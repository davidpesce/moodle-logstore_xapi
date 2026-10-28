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

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

/**
 * Unit tests for OAuth2 client credentials when sending statements to the LRS.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::\src\loader\moodle_curl_lrs\get_authorization
 * @covers    ::\src\loader\moodle_curl_lrs\renew_authorization
 * @covers    ::\src\loader\moodle_curl_lrs\get_oauth2_token
 */
final class lrs_oauth2_test extends \advanced_testcase {
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
     * Settings for OAuth2 client credentials.
     *
     * @param array $settings Settings to change.
     * @return array
     */
    private function oauth2(array $settings = []): array {
        return array_merge([
            'lrs_auth_method' => 'oauth2',
            'lrs_oauth2_token_endpoint' => 'https://idp.example.org/oauth2/token',
            'lrs_oauth2_scope' => 'xapi:write',
        ], $settings);
    }

    /**
     * A token response from the identity provider.
     *
     * @param string $token The access token.
     * @return Response
     */
    private function token(string $token): Response {
        $body = json_encode(['access_token' => $token, 'token_type' => 'Bearer', 'expires_in' => 3600]);
        return new Response(200, ['Content-Type' => 'application/json'], $body);
    }

    /**
     * With OAuth2, a token is requested with the client credentials grant (the key and secret
     * as client ID and secret, with Basic auth), and the statements are sent with it.
     *
     * @return void
     */
    public function test_oauth2_token_is_requested_and_used(): void {
        $this->answer($this->token('abc123'));
        $this->answer(new Response(200));

        $loaded = $this->send($this->events(1), $this->oauth2());

        $this->assertCount(2, $this->requests);
        $tokenrequest = $this->requests[0]['request'];
        $this->assertSame('https://idp.example.org/oauth2/token', (string) $tokenrequest->getUri());
        $this->assertSame('POST', $tokenrequest->getMethod());
        $this->assertSame('Basic ' . base64_encode('key:secret'), $tokenrequest->getHeaderLine('Authorization'));
        parse_str((string) $tokenrequest->getBody(), $form);
        $this->assertSame(['grant_type' => 'client_credentials', 'scope' => 'xapi:write'], $form);
        $this->assertFalse($tokenrequest->hasHeader('X-Experience-API-Version'));

        $statementsrequest = $this->requests[1]['request'];
        $this->assertSame('Bearer abc123', $statementsrequest->getHeaderLine('Authorization'));
        $this->assertSame('1.0.3', $statementsrequest->getHeaderLine('X-Experience-API-Version'));
        $this->assertTrue($loaded[0]['loaded']);
    }

    /**
     * A token is reused by later runs until shortly before it expires, and a change to the
     * settings gets a new one.
     *
     * @return void
     */
    public function test_oauth2_token_is_cached(): void {
        $this->answer($this->token('abc123'));
        $this->answer(new Response(200));
        $this->answer(new Response(200));
        $this->answer($this->token('def456'));
        $this->answer(new Response(200));

        $this->send($this->events(1), $this->oauth2());
        $this->send($this->events(1), $this->oauth2());
        $this->send($this->events(1), $this->oauth2(['lrs_oauth2_scope' => 'xapi:all']));

        $urls = array_map(function ($request) {
            return (string) $request['request']->getUri();
        }, $this->requests);
        $this->assertSame([
            'https://idp.example.org/oauth2/token',
            'http://127.0.0.1:8080/xapi/statements',
            'http://127.0.0.1:8080/xapi/statements',
            'https://idp.example.org/oauth2/token',
            'http://127.0.0.1:8080/xapi/statements',
        ], $urls);
        $this->assertSame('Bearer abc123', $this->requests[2]['request']->getHeaderLine('Authorization'));
        $this->assertSame('Bearer def456', $this->requests[4]['request']->getHeaderLine('Authorization'));
    }

    /**
     * When the LRS refuses the token (401), a new one is requested and the batch sent again, once.
     *
     * @return void
     */
    public function test_refused_token_is_renewed_once(): void {
        $this->answer($this->token('old'));
        $this->answer(new Response(401, [], 'Token expired'));
        $this->answer($this->token('new'));
        $this->answer(new Response(200));

        $loaded = $this->send($this->events(1), $this->oauth2());

        $this->assertTrue($loaded[0]['loaded']);
        $this->assertCount(4, $this->requests);
        $this->assertSame('Bearer new', $this->requests[3]['request']->getHeaderLine('Authorization'));
    }

    /**
     * A token request that fails leaves the batch for a later run, as an authentication error
     * with the identity provider's answer.
     *
     * @return void
     */
    public function test_failed_token_request_is_retried_later(): void {
        $this->answer(new Response(400, [], '{"error":"invalid_client"}'));

        $loaded = $this->send($this->events(1), $this->oauth2());

        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertEquals(401, $loaded[0]['event']->errortype);
        $this->assertSame('OAuth2 token request failed (HTTP 400): {"error":"invalid_client"}', $loaded[0]['event']->response);
        $this->assertCount(1, $this->requests);
    }

    /**
     * Basic auth never asks for a token: a 401 isn't sent again in the run, and the batch waits
     * for a later one.
     *
     * @return void
     */
    public function test_basic_auth_401_asks_for_no_token(): void {
        $this->answer(new Response(401, [], 'Unauthorized'));

        $loaded = $this->send($this->events(1), ['lrs_oauth2_token_endpoint' => 'https://idp.example.org/oauth2/token']);

        $this->assertCount(1, $this->requests);
        $this->assertSame(XAPI_LOADED_RETRY, $loaded[0]['loaded']);
        $this->assertEquals(401, $loaded[0]['event']->errortype);
    }
}
