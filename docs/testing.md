# Testing

The tests run with Moodle's PHPUnit, from the root of a Moodle site with the plugin in `admin/tool/log/store/xapi` (`public/admin/tool/log/store/xapi` from Moodle 5.1). Initialise PHPUnit as [Moodle's PHPUnit guide](https://moodledev.io/general/development/tools/phpunit) describes, then run the plugin's tests:

```bash
vendor/bin/phpunit --testsuite logstore_xapi_testsuite
```

Each event test is a directory, `tests/<component>/<event>/<case>/`, structured like [the `src/transformer/events` directory](../src/transformer/events). For example, [`tests/mod_page/course_module_viewed/existing_module`](../tests/mod_page/course_module_viewed/existing_module) has four files:

- `existing_module_test.php` runs the test. It extends `\logstore_xapi\xapi_test_case`, and says which directory to read and which plugin the event needs (`get_plugin_type()` and `get_plugin_name()`). A test for a plugin that isn't installed is skipped. PHPUnit only runs files whose names end in `_test.php`.
- `data.json` holds the database records the transformer reads.
- `event.json` is the logged event.
- `statements.json` is the statements the transformer must produce. A property set to `null` must be absent.

The files in [`tests/common`](../tests/common) are merged into every test's data, event and statements.

Each test sends the event through the transformer, compares the result with `statements.json`, checks that responses use the ids their definition declares, and validates each statement against the xAPI specification. The validation needs the plugin's development dependencies (`composer install` in the plugin directory); without them it is skipped.
