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
 * Redact personal details from text a person wrote, before it leaves Moodle.
 *
 * Emails, links, National Insurance numbers, UK postcodes and phone numbers,
 * and runs of six or more digits are replaced first. Then names: those of the
 * people named by the caller, of everyone enrolled on the course, and any
 * word that is some user's first or last name. Each becomes a bracketed
 * marker such as [name]. The rules are fixed, so the same text and the same
 * users give the same result: statement ids are built from the content.
 *
 * @package   logstore_xapi
 * @copyright 2026 Flavio <flavio@ferreira-michell.org>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer\utils;

/**
 * Redact free text.
 *
 * @param array $config The transformer config settings.
 * @param ?string $text The text as written, HTML or plain.
 * @param int $courseid The course whose participants' names are removed; 0 for none.
 * @param array $userids Users whose names are removed wherever they sit (author, subject).
 * @return string Plain text, at most 1000 characters.
 */
function redact_free_text(array $config, ?string $text, int $courseid = 0, array $userids = []): string {
    if ($text === null || $text === '') {
        return '';
    }
    // A tag becomes a space, so words either side of a <br> stay apart.
    $text = preg_replace('/<[^>]*>/u', ' ', $text) ?? '';
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if ($text === '') {
        return '';
    }
    $text = mb_substr($text, 0, 2000);

    if (array_key_exists('redact_free_text', $config) && empty($config['redact_free_text'])) {
        return mb_substr($text, 0, 1000);
    }

    $patterns = [
        '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.\p{L}{2,}/u' => '[email]',
        '~(?:https?://|www\.)\S+~iu' => '[url]',
        '/(?<![\p{L}\p{N}])[A-Z]{2} ?\d{2} ?\d{2} ?\d{2} ?[A-D](?![\p{L}\p{N}])/iu' => '[ni]',
        '/(?<![\p{L}\p{N}])(?:GIR ?0AA|[A-PR-UWYZ][A-HK-Y]?\d[A-Z\d]? ?\d[ABD-HJLNP-UW-Z]{2})(?![\p{L}\p{N}])/iu' => '[postcode]',
        '/(?<![\p{L}\p{N}+])(?:\+44 ?(?:\(0\) ?)?|0)\d(?:[ \-]?\d){8,9}(?!\p{N})/u' => '[phone]',
        '/(?<![\p{L}\p{N}])\d{6,}(?![\p{L}\p{N}])/u' => '[number]',
    ];
    foreach ($patterns as $pattern => $marker) {
        $text = preg_replace($pattern, $marker, $text) ?? '';
    }

    $names = redact_free_text_names($config, $text, $courseid, $userids);
    // Longest first, so "Mary Jane" goes before "Mary". A marker already in
    // the text is matched whole and kept, so it is never matched again; a name
    // a person put in brackets, "[Shinji]", is still a name.
    foreach (array_chunk($names, 150) as $chunk) {
        $alternatives = implode('|', array_map(function ($name) {
            return preg_quote($name, '/');
        }, $chunk));
        $text = preg_replace_callback(
            '/(\[(?:name|email|url|ni|postcode|phone|number)\])|(?<![\p{L}\p{N}])(?:' . $alternatives . ')(?![\p{L}\p{N}])/iu',
            function ($m) {
                return ($m[1] ?? '') !== '' ? $m[1] : '[name]';
            },
            $text
        ) ?? '';
    }

    return mb_substr($text, 0, 1000);
}

/**
 * The names to remove from one text, longest first.
 *
 * @param array $config The transformer config settings.
 * @param string $text The text, after the pattern pass.
 * @param int $courseid The course whose participants are included; 0 for none.
 * @param array $userids Users always included.
 * @return array Lower-case names.
 */
function redact_free_text_names(array $config, string $text, int $courseid, array $userids): array {
    // Cached per repository, so one emit batch reads a course's people once.
    static $cache = [];
    $repo = $config['repo'];
    $key = spl_object_id($repo);
    $cache[$key] = $cache[$key] ?? ['user' => [], 'course' => [], 'word' => []];
    $c = &$cache[$key];

    $readuser = function (int $id) use ($repo, &$c) {
        if (!array_key_exists($id, $c['user'])) {
            $found = $id > 0 ? $repo->read_records('user', ['id' => $id]) : [];
            $c['user'][$id] = empty($found) ? null : reset($found);
        }
        return $c['user'][$id];
    };

    $people = [];
    foreach ($userids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            $people[$id] = $id;
        }
    }
    if ($courseid > 1) {
        if (!array_key_exists($courseid, $c['course'])) {
            $ids = [];
            foreach ($repo->read_records('enrol', ['courseid' => $courseid]) as $enrol) {
                foreach ($repo->read_records('user_enrolments', ['enrolid' => $enrol->id]) as $ue) {
                    $ids[(int) $ue->userid] = (int) $ue->userid;
                }
            }
            $c['course'][$courseid] = $ids;
        }
        $people += $c['course'][$courseid];
    }

    $names = [];
    $add = function ($value) use (&$names) {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }
        foreach (array_merge([$value], preg_split('/[\s\-._@]+/u', $value) ?: []) as $part) {
            $part = mb_strtolower(trim($part));
            if (mb_strlen($part) >= 2) {
                $names[$part] = true;
            }
        }
    };
    $fields = ['firstname', 'lastname', 'middlename', 'alternatename', 'firstnamephonetic', 'lastnamephonetic'];
    foreach ($people as $id) {
        $user = $readuser($id);
        if (!$user) {
            continue;
        }
        foreach ($fields as $field) {
            $add($user->$field ?? '');
        }
        if (!in_array($user->username ?? '', ['', 'admin', 'guest'], true)) {
            $add($user->username);
        }
    }

    // Anyone else named in the text: a word that is some user's first or last
    // name. Capped, so a long text costs a bounded number of lookups. Common
    // words are skipped here: service accounts are called "Test User" and
    // "Course template Service user", and the first names Will, May and Mark
    // are also ordinary words. People on the course are matched above anyway.
    $common = array_flip(redact_free_text_common_words());
    preg_match_all('/\p{L}[\p{L}\'’]*/u', $text, $matches);
    $words = [];
    foreach ($matches[0] as $word) {
        $word = mb_strtolower($word);
        if (mb_strlen($word) >= 3 && !isset($names[$word]) && !isset($common[$word])) {
            $words[$word] = true;
        }
        if (count($words) >= 200) {
            break;
        }
    }
    foreach (array_keys($words) as $word) {
        if (!array_key_exists($word, $c['word'])) {
            $c['word'][$word] = !empty($repo->read_records('user', ['firstname' => $word]))
                || !empty($repo->read_records('user', ['lastname' => $word]));
        }
        if ($c['word'][$word]) {
            $names[$word] = true;
        }
    }

    // The markers themselves are never names.
    foreach (['name', 'email', 'url', 'phone', 'postcode', 'ni', 'number'] as $marker) {
        unset($names[$marker]);
    }
    $names = array_keys($names);
    usort($names, function ($a, $b) {
        return mb_strlen($b) <=> mb_strlen($a) ?: strcmp($a, $b);
    });
    return $names;
}

/**
 * Words never treated as a name by the site-wide lookup.
 *
 * Frequent English words, the words of a course, and first names that are
 * also everyday words. A name in this list is still removed when it belongs
 * to the author or to someone on the course.
 *
 * @return array Lower-case words.
 */
function redact_free_text_common_words(): array {
    return [
        'the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'any', 'can', 'had', 'her', 'was', 'one',
        'our', 'out', 'day', 'get', 'has', 'him', 'his', 'how', 'man', 'new', 'now', 'old', 'see', 'two',
        'way', 'who', 'boy', 'did', 'its', 'let', 'put', 'say', 'she', 'too', 'use', 'yes', 'why', 'ask',
        'off', 'own', 'end', 'few', 'far', 'big', 'try', 'lot', 'yet', 'set', 'run', 'top', 'job', 'pay',
        'that', 'with', 'have', 'this', 'will', 'your', 'from', 'they', 'know', 'want', 'been', 'good',
        'much', 'some', 'time', 'very', 'when', 'come', 'here', 'just', 'like', 'long', 'make', 'many',
        'more', 'only', 'over', 'such', 'take', 'than', 'them', 'well', 'were', 'what', 'work', 'year',
        'also', 'back', 'call', 'came', 'each', 'even', 'find', 'give', 'hand', 'help', 'high', 'keep',
        'last', 'late', 'left', 'life', 'line', 'look', 'made', 'most', 'move', 'must', 'name', 'need',
        'next', 'open', 'part', 'play', 'read', 'real', 'same', 'seem', 'show', 'side', 'tell', 'then',
        'turn', 'used', 'want', 'week', 'word', 'done', 'sure', 'okay', 'thanks', 'thank', 'please',
        'hello', 'about', 'after', 'again', 'below', 'could', 'every', 'first', 'found', 'great', 'house',
        'large', 'learn', 'never', 'other', 'place', 'right', 'small', 'sound', 'spell', 'still', 'study',
        'their', 'there', 'these', 'thing', 'think', 'three', 'water', 'where', 'which', 'world', 'would',
        'write', 'before', 'little', 'people', 'should', 'answer', 'because', 'between', 'through',
        'course', 'user', 'users', 'test', 'tests', 'service', 'template', 'student', 'students',
        'teacher', 'tutor', 'learner', 'learners', 'class', 'lesson', 'module', 'unit', 'page', 'quiz',
        'question', 'questions', 'level', 'topic', 'task', 'grade', 'score', 'today', 'tomorrow',
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'january',
        'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november',
        'december', 'spring', 'summer', 'autumn', 'winter', 'admin', 'guest', 'manager', 'support',
        'mark', 'bill', 'rose', 'grace', 'hope', 'faith', 'joy', 'dawn', 'rob', 'pat', 'sue', 'art',
        'max', 'ray', 'guy', 'jack', 'frank', 'gene', 'drew', 'chase', 'miles', 'penny',
        'holly', 'ivy', 'iris', 'lily', 'daisy', 'heather', 'amber', 'ruby', 'pearl', 'crystal', 'sky',
        'brook', 'river', 'storm', 'summer', 'king', 'young', 'white', 'black', 'brown',
        'green', 'gray', 'grey', 'long', 'short', 'little', 'small', 'hall', 'wood', 'hill', 'field',
        'church', 'cook', 'bell', 'rich', 'price', 'page',
        'day', 'case', 'love', 'cross', 'lane', 'moon', 'star', 'sun', 'unit', 'scenario', 'awareness',
        'synchronisation', 'synthetic',
    ];
}
