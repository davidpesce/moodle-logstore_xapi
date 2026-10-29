# Sending past events

The plugin can send events from before it was installed, or from a time when it was disabled. It reads them from Moodle's standard log, through the historic report.

## Before you start

- The events must be in the standard log. The "Standard log" store must have been enabled when they happened, and must still hold them: its "Keep logs for" setting deletes older ones.
- Only events whose routes are selected in "Include actions with these routes" are listed and sent.
- Events from guests and users who aren't logged in are left out unless "Log guest actions" is on, and anonymous events follow the "Anonymous events" setting, as for live events.

## Send them

1. Go to Site administration > Plugins > Logging > Logstore xAPI > Logstore xAPI Historic Log. You need the "Manage historic data" capability (`logstore/xapi:managehistoric`).
2. Choose a start date, and optionally an end date, a course (and one of its activities), a user or event names. Select "Search". The report lists the matching events that haven't been sent.
3. Select "Resend" to move them to the queue.

The "Emit historical records to LRS" scheduled task sends the queue every hour, in batches of "Maximum batch size for historical requests", until the queue is empty or the "Time limit for each run" has passed. To send them sooner, run the task from the command line in the Moodle directory:

```bash
php admin/cli/scheduled_task.php --execute='\logstore_xapi\task\historical_task'
```

The plugin records each event it sends, live or historic, so sent events drop out of the report. Each statement's id is built from the event and the statement's content, so if the same statement is sent twice, the LRS keeps one copy.

## Large date ranges

Moving events to the queue happens in the page request. On a busy site, send a few weeks or months at a time rather than several years at once.

## Don't copy the standard log into the queue

Earlier versions of this guide suggested copying rows from `logstore_standard_log` into `logstore_xapi_log` with SQL. Don't: the tables have different columns, and copied rows skip the checks above, the record of sent events and the link to the standard log row.
