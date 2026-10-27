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
 * Transformer utility for the homePage of actor accounts.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * The homePage of every actor account: the "Actor Account HomePage" setting, or the site URL.
 *
 * The homePage must be an IRL, so a saved value that isn't an absolute URL (the setting was
 * free text before 5.3.0) falls back to the site URL rather than making every statement invalid.
 *
 * @param array $config The transformer config settings.
 * @return string
 */
function get_account_homepage(array $config): string {
    $homepage = (string) ($config['account_homepage'] ?? '');
    return preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:\/\/\S+$/', $homepage) ? $homepage : $config['app_url'];
}
