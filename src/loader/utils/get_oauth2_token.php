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
 * Adjusts the endpoint to the appropriate place.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\loader\utils;

/**
 * Get an OAuth2 token from provided IdP using Client Credentials flow.
 * The Client ID and secrets are base-64-encoded in a Basic auth header. 
 *
 * @param string $token_endpoint The 'token' http endpoint of the OAuth2 IdP
 * @param string $client_id The client ID of this Moodle instance in the IdP
 * @param string $client_secret The client secret of this Moodle instance in the IdP 
 * @return array token data in JSON
 *                {"access_token": ..., "expires_in": ..., "scope": ..., "token_type": ... }
 * }
 *              
 */
function get_oauth2_token(string $token_endpoint, string $client_id, string $client_secret) {
    $auth_header = 'Basic ' . base64_encode($client_id . ':' . $client_secret);

    $request = curl_init();
    curl_setopt($request, CURLOPT_POST, 1);
    curl_setopt($request, CURLOPT_URL, $token_endpoint);
    curl_setopt($request, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($request, CURLOPT_HTTPHEADER, [
        'Authorization: ' . $auth_header,
        'content-type: application/x-www-form-urlencoded',
        'Accept: application/json'
    ]);

    $responsetext = curl_exec($request);
    $responsecode = curl_getinfo($request, CURLINFO_RESPONSE_CODE);

      if ($responsecode !== 200) {
          throw new \Exception($responsetext, $responsecode);
      }
    
    return json_decode($responsetext, true);
}
