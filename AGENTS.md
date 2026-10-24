# logstore_xapi

<!-- Split out of the former _MoodleDEV/CLAUDE.md (1039 lines) during the
     2026-10 ~/git restructure. Harness commands live in
     ~/git/exputo/moodle/dev/AGENTS.md. -->

## Plugin: logstore_xapi

This is a Moodle logstore plugin (`logstore_xapi`) that intercepts Moodle events and transforms them into [xAPI statements](https://github.com/adlnet/xAPI-Spec/blob/master/xAPI.md), sending them to a Learning Record Store (LRS). When installed in Moodle, the plugin lives at `admin/tool/log/store/xapi/` relative to the Moodle root.

Current version: **5.1.0** (supports Moodle 4.5–5.3)

Every change is verified against all supported branches before merging:
`make mpci-matrix PLUGIN=moodle-logstore_xapi` from the harness, plus green GitHub CI.

### Plugin Commands

All dev tooling (Docker, PHPUnit, phpcs, phpmd) runs from the `exputo/moodle/dev` harness. See the Commands section above and the `Makefile`. Plugin-specific commands:

```bash
make phpcs PLUGIN=moodle-logstore_xapi                  # Moodle code style checker
make phpcbf PLUGIN=moodle-logstore_xapi                 # auto-fix phpcs violations
make phpmd PLUGIN=moodle-logstore_xapi                  # PHP Mess Detector
make phpunit SUITE=logstore_xapi_testsuite              # run all plugin tests
make phpunit-filter FILTER=multichoice_test             # run a single test class
```

#### Release Scripts

```bash
./scripts/generateVersionFile.sh   # Generate version.php from template
./scripts/generateZipFile.sh       # Package plugin for distribution
```

### Architecture

#### Event Processing Pipeline

The core flow is: **Moodle Event → Logstore Queue → Scheduled Task → Transformer → xAPI Statement → LRS**

1. Moodle fires an event (e.g., `\mod_quiz\event\attempt_submitted`)
2. The logstore captures it into `logstore_xapi_log` table
3. A scheduled task (`classes/task/emit_task.php`) batches and processes events
4. `src/handler.php` (the top-level handler) calls `src/transformer/handler.php`
5. The transformer looks up the event in `src/transformer/get_event_function_map.php`
6. The mapped transformer function in `src/transformer/events/` converts the event to xAPI statements
7. `src/transformer/utils/apply_statement_defaults.php` merges in context defaults (platform, timestamp)
8. Statements are sent to the configured LRS; failures go to `logstore_xapi_failed_log`

#### Key Files

| File | Purpose |
|------|---------|
| `src/transformer/get_event_function_map.php` | Maps Moodle event class names → transformer function paths; result is statically cached per process |
| `src/transformer/handler.php` | Iterates events, dispatches to transformers, catches errors |
| `src/transformer/events/` | One PHP file per supported event (grouped by module) |
| `src/transformer/utils/` | Shared helper functions for building statement parts |
| `src/transformer/utils/get_activity/` | Activity object builders for each module type |
| `src/transformer/repos/Repository.php` | Abstract DB interface; `MoodleRepository.php` for production, `TestRepository.php` for tests |
| `classes/task/emit_task.php` | Scheduled task: live event emission |
| `classes/task/historical_task.php` | Scheduled task: historical event backfill |
| `classes/task/failed_task.php` | Scheduled task: retry failed events |
| `classes/log/store.php` | Core logstore integration with Moodle |

#### Adding Support for a New Event

1. Create a transformer function in `src/transformer/events/<module>/<event_name>.php`
   - Namespace must match path from `src/`: e.g., `namespace src\transformer\events\mod_foo\bar_created;`
   - Function name must match the event name (e.g., `function bar_created(...)`)
2. Add the mapping to `src/transformer/get_event_function_map.php`:
   ```php
   '\mod_foo\event\bar_created' => 'mod_foo\bar_created',
   ```
3. Create a test directory `tests/<module>/<event_name>/` with 4 files:
   - `*_test.php` — extends `\logstore_xapi\xapi_test_case`, implements `get_test_dir()`, `get_plugin_type()`, `get_plugin_name()`
   - `data.json` — mocked Moodle DB records (merged with `tests/common/data.json`)
   - `event.json` — mocked event data (merged with `tests/common/event.json`)
   - `statements.json` — expected xAPI statements output (merged with `tests/common/statement.json`)

**`get_plugin_type()` / `get_plugin_name()` values** control whether the test runs or is skipped when the required plugin is not installed. Use the correct Moodle plugin type — getting this wrong silently skips the test permanently:

| Event source | `get_plugin_type()` | `get_plugin_name()` |
|---|---|---|
| Moodle core (e.g. `\core\event\*`) | `"core"` | `"core"` (or any string — skip check is bypassed) |
| Bundled activity module (forum, quiz, assign…) | `"mod"` | e.g. `"forum"` |
| Bundled admin tool (usertours, certificate…) | `"tool"` | e.g. `"usertours"` |
| Third-party mod (facetoface, questionnaire…) | `"mod"` | e.g. `"questionnaire"` |
| Third-party tool (tool_certificate…) | `"tool"` | e.g. `"certificate"` |

Tests for plugins not installed locally will be marked **Skipped** — this is expected. They run in CI where the plugin is installed (see `.github/workflows/moodle-plugin-ci.yml`).

#### Test Structure

Each test inherits `test_create_event()` from `xapi_test_case`, which:
- Builds a config with a `TestRepository` seeded from `data.json`
- Runs the event through the full transformer pipeline
- Asserts the output matches `statements.json`
- Validates each statement against the xAPI spec via `yetanalytics/statementfactory`

The `tests/common/` directory holds shared base data merged into every test's data, event, and statement files.

#### Statement Anatomy

Transformers return PHP arrays shaped as xAPI statements. The `statement.context.extensions` object always includes an `http://lrs.learninglocker.net/define/extensions/info` property with `event_function` pointing back to the transformer file path — this is used for debugging/tracing statements back to their transformer.

#### Third-party and Deprecated Plugin Notes

- **`mod_survey`** — deprecated in Moodle 4.x, removed in 5.x. Transformers and tests remain but will be skipped automatically once the module is gone from Moodle's plugin registry. Remove the transformer and tests when dropping support for older Moodle versions.
- **Totara** — support was removed in 5.2.0. Totara is a separate fork that reports a Moodle 3.4-era version, and this plugin has required Moodle 3.5 or later since 4.6.0, so it could not be installed on Totara and the `totara_program` transformer could never run.
- **Third-party plugins in CI** — `moodle-tool_certificate` and `moodle-mod_facetoface` are installed as additional plugins in the CI workflow (`.github/workflows/moodle-plugin-ci.yml`). Other third-party plugin tests (questionnaire) are always skipped in CI.

#### Config Options Passed to Transformers

Key config flags that control statement content:
- `send_mbox` — include actor email as mbox
- `send_name` — include actor display name
- `send_response_choices` — include answer choices in responses
- `send_short_course_id` / `send_course_and_module_idnumber` — control activity ID format
- `send_username` — include username in actor

---

