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
 * Transformer utility for retrieving H5P content activities.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity;

/**
 * Transformer utility for retrieving an H5P content activity, identified by its file URL.
 *
 * Used for H5P content shown outside an activity: in the content bank, or embedded in a
 * course, section or user text. The h5p table has no title, so the name is the file name.
 *
 * @param string $url The URL of the .h5p file.
 * @param string $lang The language.
 * @return array
 */
function h5p_content(string $url, string $lang) {
    // Core logs the URL HTML-escaped (moodle_url::out()), so query parameters are joined by &amp;.
    $url = get_h5p_content_url(str_replace('&amp;', '&', $url));
    $filename = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
    $name = preg_replace('/\.h5p$/i', '', $filename);
    return [
        ...base(),
        'id' => $url,
        'definition' => [
            'type' => 'http://adlnet.gov/expapi/activities/media',
            ...($name !== '' ? ['name' => [$lang => $name]] : []),
        ],
    ];
}

/**
 * The URL of an .h5p file, without a user's private access key.
 *
 * The Moodle app plays H5P content through tokenpluginfile.php, whose URL holds the user's
 * private access key: tokenpluginfile.php/<key>/<context>/... The key gives access to that
 * user's files, so it must not reach the LRS, and the same content must have one id for everyone.
 * The URL is rewritten to the matching pluginfile.php URL.
 *
 * @param string $url The URL of the .h5p file.
 * @return string
 */
function get_h5p_content_url(string $url): string {
    return preg_replace('#/tokenpluginfile\.php/[^/]+/#', '/pluginfile.php/', $url);
}
