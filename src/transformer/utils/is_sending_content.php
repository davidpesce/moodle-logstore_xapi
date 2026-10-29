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
 * Transformer utility for the "Send content" setting.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Whether statements carry content people wrote: forum posts, messages, comments, glossary
 * definitions, wiki pages, book chapters, notes, and free-text answers and feedback.
 *
 * On unless the admin turns "Send content" off, as statements carried it before the setting.
 *
 * @param array $config The transformer config settings.
 * @return bool
 */
function is_sending_content(array $config): bool {
    return !array_key_exists('send_content', $config) || !empty($config['send_content']);
}
