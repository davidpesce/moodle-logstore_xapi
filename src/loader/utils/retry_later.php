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
 * The LRS can't take statements now: try again on a later run.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\loader\utils;

/**
 * The LRS can't take statements now: try again on a later run.
 *
 * Thrown when the LRS can't be reached (no connection, a timeout, a TLS error, no endpoint set,
 * or anything else that stops the request), redirects (3xx), refuses the credentials (401, 403, a
 * failed OAuth2 token request), isn't at the endpoint (404), asks to wait (408, 429) or fails
 * itself (5xx). None of these say anything about the
 * statements, so the batch stays in the queue and the run stops, instead of moving the batch to
 * the failed log. The code is the HTTP status, or cURL's error number.
 */
class retry_later extends \Exception {
}
