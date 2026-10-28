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
 * Send batches of events in turn, until the LRS asks to try again later.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\loader\utils;

/**
 * Send batches of events in turn, until the LRS asks to try again later.
 *
 * Once a batch is left for a later run (see retry_later), the batches after it are not sent:
 * they are left for a later run too, so an LRS that is down costs one request, not one per
 * batch.
 *
 * @param array $config An array of configuration settings.
 * @param array $batches Batches of transformed events.
 * @param callable $loader
 * @return array The loaded events.
 */
function load_batches(array $config, array $batches, callable $loader): array {
    $loadedevents = [];
    $retry = false;
    foreach ($batches as $batch) {
        $loadedbatch = $retry ? construct_loaded_events($batch, XAPI_LOADED_RETRY) : load_batch($config, $batch, $loader);
        $retry = $retry || in_array(XAPI_LOADED_RETRY, array_column($loadedbatch, 'loaded'), true);
        $loadedevents = array_merge($loadedevents, $loadedbatch);
    }
    return $loadedevents;
}
