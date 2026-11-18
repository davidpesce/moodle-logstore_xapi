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
 * Client-side (H5P) xAPI verb policy.
 *
 * @package   logstore_xapi
 * @copyright 2026 Lachlan Keown <lachlankeown@gmail.com>, NZ ADL
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\client;

defined('MOODLE_INTERNAL') || die();

/**
 * Decides which client-side verbs are accepted.
 */
class verb_policy {
    /**
     * Canonical client-side (H5P) xAPI verbs.
     *
     * Keys are short names, values are full ADL verb IRIs. Mirrors
     * H5P.XAPIEvent.allowedXAPIVerbs.
     *
     * @return array Short name => full verb IRI.
     */
    public static function get_verb_map(): array {
        $base = 'http://adlnet.gov/expapi/verbs/';
        $shorts = [
            'answered',
            'asked',
            'attempted',
            'attended',
            'commented',
            'completed',
            'exited',
            'experienced',
            'failed',
            'imported',
            'initialized',
            'interacted',
            'launched',
            'mastered',
            'passed',
            'preferred',
            'progressed',
            'registered',
            'responded',
            'resumed',
            'scored',
            'shared',
            'suspended',
            'terminated',
            'voided',
            'downloaded',
            'copied',
            'accessed-reuse',
            'accessed-embed',
            'accessed-copyright',
        ];
        $map = [];
        foreach ($shorts as $short) {
            $map[$short] = $base . $short;
        }
        return $map;
    }

    /**
     * Get the short names of enabled client verbs.
     *
     * A `false` config (never saved) means all verbs enabled for backwards
     * compatibility. An empty string means all disabled.
     *
     * @return array Enabled short names.
     */
    public static function get_enabled_verbs(): array {
        $map = self::get_verb_map();
        $raw = get_config('logstore_xapi', 'clientverbs');
        if ($raw === false) {
            return array_keys($map);
        }
        if (is_array($raw)) {
            $selected = array_keys(array_filter($raw));
        } else {
            $raw = trim((string)$raw);
            if ($raw === '') {
                return [];
            }
            $selected = array_map('trim', explode(',', $raw));
        }
        return array_values(array_intersect($selected, array_keys($map)));
    }

    /**
     * Get the full IRIs of enabled client verbs, for browser pre-filtering.
     *
     * @return array Enabled verb IRIs.
     */
    public static function get_enabled_verb_ids(): array {
        $map = self::get_verb_map();
        $enabled = self::get_enabled_verbs();
        $ids = [];
        foreach ($enabled as $short) {
            if (isset($map[$short])) {
                $ids[] = $map[$short];
            }
        }
        return $ids;
    }

    /**
     * Whether statements with unknown (non-H5P-list) verbs should be allowed.
     *
     * Separate setting so custom verbs fail open by default without forcing
     * admins to enumerate them.
     *
     * @return bool
     */
    public static function allow_unknown(): bool {
        $val = get_config('logstore_xapi', 'clientverbs_allow_unknown');
        if ($val === false) {
            return true;
        }
        return (bool)$val;
    }

    /**
     * Check whether a client verb IRI is enabled.
     *
     * @param string $verbid Full verb IRI from the statement.
     * @return bool
     */
    public static function is_enabled($verbid): bool {
        $verbid = trim((string)$verbid);
        if ($verbid === '') {
            return false;
        }
        $map = self::get_verb_map();
        $flipped = array_flip($map);
        if (isset($flipped[$verbid])) {
            return in_array($flipped[$verbid], self::get_enabled_verbs(), true);
        }
        return self::allow_unknown();
    }
}
