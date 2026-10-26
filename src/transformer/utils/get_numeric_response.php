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
 * Transformer utility for reading the number out of a numerical answer.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Read the number from a numerical answer as the learner typed it.
 *
 * A numeric interaction's response must be a number, but Moodle keeps the answer as typed:
 * with a unit before or after it ("5 m", "$5"), and with the decimal separator of the
 * learner's language ("5,5"). The unit is dropped and the number is returned with a "." decimal
 * point. A "," is read as a thousands separator when it groups digits in threes ("1,000"), and
 * as the decimal separator otherwise; with both "," and ".", the last one is the decimal point.
 *
 * @param ?string $answer The answer as typed.
 * @return string The number, or '' when the answer holds no number.
 */
function get_numeric_response(?string $answer): string {
    if (!preg_match('/[+-]?(?:\d[\d,.]*|[.,]\d+)(?:[eE][+-]?\d+)?/', (string) $answer, $matches)) {
        return '';
    }
    $number = normalise_number_separators(rtrim($matches[0], ',.'));
    return is_numeric($number) ? $number : '';
}

/**
 * Remove thousands separators from a number and make its decimal separator a ".".
 *
 * @param string $number Digits with "," and "." separators.
 * @return string
 */
function normalise_number_separators(string $number): string {
    $lastcomma = strrpos($number, ',');
    $lastpoint = strrpos($number, '.');
    if ($lastcomma !== false && $lastpoint !== false) {
        // Both separators: the last one is the decimal point.
        return $lastcomma > $lastpoint
            ? str_replace(['.', ','], ['', '.'], $number)
            : str_replace(',', '', $number);
    }
    if ($lastcomma !== false) {
        return preg_match('/^[+-]?\d{1,3}(,\d{3})+$/', $number)
            ? str_replace(',', '', $number)
            : str_replace(',', '.', $number);
    }
    // Several points can only be thousands separators.
    return substr_count($number, '.') > 1 ? str_replace('.', '', $number) : $number;
}
