# Develop the plugin

To ask for a change instead, open an issue in the [issue tracker](https://github.com/davidpesce/moodle-logstore_xapi/issues). [How it works](how-it-works.md) explains how the code fits together.

## Set up

1. Fork the repository, and clone your fork into a Moodle site's `admin/tool/log/store` directory (`public/admin/tool/log/store` from Moodle 5.1) as `xapi`: `git clone git@github.com:YOUR_USERNAME/moodle-logstore_xapi.git xapi`. Remove any copy installed from a ZIP file first.
2. In the `xapi` directory, run `composer install`. The tests validate statements with its development dependencies.
3. Install the plugin as an admin (Site administration > Notifications), and initialise Moodle's PHPUnit as [Moodle's PHPUnit guide](https://moodledev.io/general/development/tools/phpunit) describes.

Make each change on its own branch from `master`.

## Find a statement's transformer

Every statement says which function made it: in `context.extensions`, the `http://lrs.learninglocker.net/define/extensions/info` extension has an `event_function`, such as `\src\transformer\events\core\course_completed`. That is the namespace and name of the function, and the path of its file under `src`: [`src/transformer/events/core/course_completed.php`](../src/transformer/events/core/course_completed.php). [`get_event_function_map()`](../src/transformer/get_event_function_map.php) maps each event to its transformer.

## Change a statement

Change the transformer, or the utility in [`src/transformer/utils`](../src/transformer/utils) that builds the part you want to change, then update the expected statements of the tests it affects (see [Tests](#tests)).

## Support a new event

1. Create the transformer in `src/transformer/events/<component>/<event>.php`. Its namespace follows the path from `src` (`namespace src\transformer\events\mod_foo;`), and its function is named after the event (`function bar_created(array $config, \stdClass $event)`). It returns an array of statements. [`course_completed`](../src/transformer/events/core/course_completed.php) is a short example.
2. Map the event to it in [`get_event_function_map()`](../src/transformer/get_event_function_map.php): `'\mod_foo\event\bar_created' => 'mod_foo\bar_created'`.
3. Add a test (see [Tests](#tests)), with the event and data as core stores them.
4. Add the event to [Supported events](supported-events.md).

New sites send the new event. Sites that upgrade keep their saved "Include actions with these routes", so it is off there until the admin turns it on; say so in `CHANGES.md`.

## Tests

The tests run with Moodle's PHPUnit, from the root of the Moodle site:

```bash
vendor/bin/phpunit --testsuite logstore_xapi_testsuite
```

Each event test is a directory, `tests/<component>/<event>/<case>/`, structured like [`src/transformer/events`](../src/transformer/events). For example, [`tests/mod_page/course_module_viewed/existing_module`](../tests/mod_page/course_module_viewed/existing_module) has four files:

- `existing_module_test.php` runs the test. It extends `\logstore_xapi\xapi_test_case`, and says which directory to read and which plugin the event needs (`get_plugin_type()` and `get_plugin_name()`: `core`, or for example `mod` and `quiz`). A test for a plugin that isn't installed is skipped. PHPUnit only runs files whose names end in `_test.php`.
- `data.json` holds the database records the transformer reads. Store them as core does: the same tables, columns and values.
- `event.json` is the logged event.
- `statements.json` is the statements the transformer must produce. A property set to `null` must be absent.

The files in [`tests/common`](../tests/common) are merged into every test's data, event and statements; a table in a test's `data.json` replaces the common one.

Each test sends the event through the transformer, compares the result with `statements.json`, checks that responses use the ids their definition declares, and validates each statement against the xAPI specification. The validation needs the development dependencies; without them it is skipped. The base configuration has "Send response choices" on and "Send content" on; a test can override `get_transformer_config()` to turn them off.

Statements from third-party plugins are tested when the plugin is installed. GitHub CI installs mod_facetoface, mod_game and tool_certificate.

## Style and pull requests

The code follows the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle). GitHub CI runs [moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/) on every supported Moodle version, with PostgreSQL and MariaDB. The code checker must pass with no warnings, and so must the PHPDoc checker, the plugin validation, the upgrade savepoint, Mustache and Grunt checks, and the tests. The copy/paste and mess detectors only report.

In your pull request, say which issue it fixes ("Fixes #123"), and add an entry to the unreleased section of [`CHANGES.md`](../CHANGES.md), written for the site admins who will read it.
