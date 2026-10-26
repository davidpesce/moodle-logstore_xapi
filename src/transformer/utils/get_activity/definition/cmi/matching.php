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
 * Transformer utilities for creating CMI matching xAPI Activity object definitions.
 *
 * @package   logstore_xapi
 * @copyright Milt Reder <milt@yetanalytics.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils\get_activity\definition\cmi;

use src\transformer\utils;

/**
 * Transformer util for creating matching definitions
 *
 * Source and target ids are made from their text, as responses are. Items with no text are left
 * out, and an item listed twice (a choice shared by several stems or gaps) is sent once, so
 * every id is unique. Targets that are in no correct pair are distractors.
 *
 * @param array $config The transformer config settings.
 * @param string $name The activity name.
 * @param ?string $description The activity description.
 * @param array $source The source texts.
 * @param array $target The target texts, including distractors.
 * @param array $correctpairs The correct response: [source text, target text] pairs.
 * @param string $lang The language.
 * @return array
 */
function matching(
    array $config,
    string $name,
    ?string $description,
    array $source,
    array $target,
    array $correctpairs,
    string $lang
) {
    $definition = [
        ...common($config, $name, $description, $lang),
        'interactionType' => 'matching',
    ];

    // The source and target lists can give the answers away as much as the correct response
    // does. All three are only sent when the admin has chosen to.
    if (!$config['send_response_choices']) {
        return $definition;
    }

    $pairs = [];
    foreach ($correctpairs as [$sourcetext, $targettext]) {
        if ((string) $sourcetext !== '' && (string) $targettext !== '') {
            $pairs[] = utils\slugify($sourcetext) . '[.]' . utils\slugify($targettext);
        }
    }

    return [
        ...$definition,
        ...(!empty($pairs) ? ['correctResponsesPattern' => [implode('[,]', $pairs)]] : []),
        'source' => get_matching_items($source, $lang),
        'target' => get_matching_items($target, $lang),
    ];
}

/**
 * Build the source or target items of a matching interaction, with unique ids.
 *
 * @param array $texts The item texts.
 * @param string $lang The language.
 * @return array
 */
function get_matching_items(array $texts, string $lang): array {
    $items = [];
    foreach ($texts as $text) {
        $text = (string) $text;
        $id = utils\slugify($text);
        if ($text === '' || isset($items[$id])) {
            continue;
        }
        $items[$id] = [
            'id' => $id,
            'description' => [
                $lang => $text,
            ],
        ];
    }
    return array_values($items);
}
