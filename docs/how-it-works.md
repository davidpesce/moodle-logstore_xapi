# How it works

The plugin is a Moodle log store: Moodle gives it every event, as it does the standard log. It turns the events into xAPI statements and sends them to the LRS, in the background.

## From event to statement

1. **Capture.** The store keeps the events whose route is selected ("Include actions with these routes"), and leaves out guests and users who aren't logged in unless "Log guest actions" is on. It writes them to its queue, the `logstore_xapi_log` table, every 50 events and when the request ends ([`classes/log/store.php`](../classes/log/store.php)). With "Send statements by scheduled task?" off, it sends them during the request instead, and queues only the ones the LRS can't take.
2. **Send.** The "Emit records to LRS" task runs every minute ([`classes/task/emit_task.php`](../classes/task/emit_task.php)). It takes the queue a batch at a time, oldest first, until the queue is empty or "Time limit for each run" has passed (`logstore_xapi_send_queued_events()` in [`lib.php`](../lib.php)).
3. **Transform.** Each event has a transformer, found by event name in [`get_event_function_map()`](../src/transformer/get_event_function_map.php), one file per event in [`src/transformer/events`](../src/transformer/events). It reads what it needs from the database and returns the event's statements. The shared parts (actor, activities, context, results) come from [`src/transformer/utils`](../src/transformer/utils). Then every statement gets the platform, a timestamp, and an id made from its content, so that a statement sent twice is stored once. Statements about an attempt (a quiz attempt, a SCORM attempt, a lesson retake, an assignment submission) share a registration.
4. **Load.** The statements are posted to the LRS's statements resource, a batch per request, with Moodle's HTTP client, so the site's proxy settings apply ([`src/loader/moodle_curl_lrs.php`](../src/loader/moodle_curl_lrs.php)). The plugin authenticates with the key and secret, or with an OAuth2 client-credentials token.
5. **Record.** Sent events leave the queue, and their standard log rows are recorded in `logstore_xapi_sent_log`, so the historic report leaves them out.

## When something fails

- **The LRS can't take statements now:** no connection, refused credentials, a wrong endpoint, too many requests, or a server error. The batch stays in the queue and the run stops; the next run tries again. The check "xAPI statements sent to the LRS", in the system status report, warns when an event has waited more than an hour, and shows the last error. When the LRS has failed on the batch at the head of the queue with a server error in five runs, the batch is split to find the events it fails on (see [When the LRS is unavailable](admin-guide.md#when-the-lrs-is-unavailable)).
- **The LRS rejects statements:** a 409 (a statement id it already has) or a 413 (too large) splits the batch in half, down to the events at fault; with "Resend failed batches" on, so does a 400. Any other rejection fails the whole batch. The events that fail go to the failed log, `logstore_xapi_failed_log`, with the LRS's answer.
- **An event fails to transform:** it goes to the failed log.

The error report (Logstore xAPI Error Log) lists the failed log. "Resend" there queues the events again, and the "Emit failed records to LRS" task sends them every 30 minutes. The "Send failed notifications" task emails the chosen cohorts and addresses about new failures.

## Past events

The historic report (Logstore xAPI Historic Log) lists events from the standard log that haven't been sent. "Resend" there queues them as historic events, which the "Emit historical records to LRS" task sends every hour. See [Send past events](admin-guide.md#send-past-events).

On both reports, "Resend" queues the "Queue xAPI events to be sent again" ad-hoc task, which moves the matching events into the queue a chunk at a time.

## Scheduled tasks

| Task | Runs | Does |
|---|---|---|
| Emit records to LRS | Every minute | Sends live events |
| Emit failed records to LRS | Every 30 minutes | Sends failed events resent from the error report |
| Emit historical records to LRS | Every hour | Sends past events queued from the historic report |
| Send failed notifications | Every 2 hours | Emails about new failures |
| Delete old xAPI log rows | Daily | Deletes sent log rows for standard log rows that are gone, old notification rows, and failed events older than "Keep failed events for" |

## Tables

| Table | Holds |
|---|---|
| `logstore_xapi_log` | The queue: live, resent and historic events (`type`), and the runs in which the LRS failed on each with a server error (`attempts`) |
| `logstore_xapi_failed_log` | Failed events, with the error |
| `logstore_xapi_sent_log` | The standard log rows of sent events |
| `logstore_xapi_notif_sent_log` | The last failure that was notified |

## Code

| Path | Does |
|---|---|
| [`classes/log/store.php`](../classes/log/store.php) | The log store: captures events, sends batches through the handler |
| [`lib.php`](../lib.php) | The send loop, and recording sent, failed and waiting events |
| [`src/handler.php`](../src/handler.php) | Runs the transformer, then the loader |
| [`src/transformer`](../src/transformer) | The event map, the transformers and their utilities |
| [`src/transformer/repos`](../src/transformer/repos) | Database access for transformers: Moodle's database, or test fixtures |
| [`src/loader`](../src/loader) | Sends statements to the LRS, in batches |
| [`classes/task`](../classes/task) | The scheduled and ad-hoc tasks |
| [`report.php`](../report.php) | The error and historic reports |
| [`classes/check`](../classes/check) | The system status and security checks |
