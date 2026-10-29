# Admin guide

Install, set up and run the plugin. [How it works](how-it-works.md) explains what happens to each event.

- [Install](#install)
- [Connect to the LRS](#connect-to-the-lrs)
- [Enable](#enable)
- [Which events are sent](#which-events-are-sent)
- [How users are identified](#how-users-are-identified)
- [What statements contain](#what-statements-contain)
- [Proxy](#proxy)
- [When the LRS is unavailable](#when-the-lrs-is-unavailable)
- [Failed events](#failed-events)
- [Send past events](#send-past-events)

## Install

The plugin supports Moodle 4.5 to 5.3. It is a log store, installed in `admin/tool/log/store/xapi` (`public/admin/tool/log/store/xapi` from Moodle 5.1). Install it one of these ways:

- **From CAMP:** download the ZIP file from [CAMP](https://camp-registry.org/plugin/logstore_xapi), the Moodle community plugin registry, where every release is published first. Upload it at Site administration > Plugins > Install plugins.
- **From the Moodle Marketplace,** once Moodle has reviewed the release: install "Logstore xAPI" at Site administration > Plugins > Install plugins, or download the ZIP file from [its page](https://marketplace.moodle.com/plugins/logstore_xapi).
- **By hand,** when installing plugins from Moodle is turned off: unzip the ZIP file into `admin/tool/log/store` (`public/admin/tool/log/store` from 5.1), so that the plugin is in its `xapi` directory.
- **With Git,** to work on the plugin: see [Develop the plugin](developing.md).

Then go to Site administration > Notifications to upgrade the database.

## Connect to the LRS

Go to Site administration > Plugins > Logging > Logstore xAPI > General Settings (`/admin/settings.php?section=logsettingxapi`).

1. Set "LRS endpoint" to your LRS's xAPI endpoint, for example `https://lrs.example.com/xapi`. Use `https://`: over plain `http://` the LRS credentials and learner data are not encrypted, and the security report warns about it.
2. Choose the "LRS authentication":
   - **Basic (key and secret)**, the default: set "LRS auth key" and "LRS auth secret" to the key and secret from your LRS.
   - **OAuth2 client credentials**: set "LRS auth key" and "LRS auth secret" to the client ID and client secret, and "OAuth2 token endpoint" to your identity provider's token endpoint. Set "OAuth2 scope" if your provider needs one.
3. Click "Save changes".

By default the plugin sends statements from a scheduled task every minute ("Send statements by scheduled task?"), so pages don't wait for the LRS. Under "Processing and batches", "Maximum batch size" is the number of events in each request to the LRS, and "Time limit for each run" stops a run that has a long queue to send.

## Enable

1. Go to Site administration > Plugins > Logging > Manage log stores (`/admin/settings.php?section=managelogging`) and enable "Logstore xAPI".
2. Make sure [cron](https://docs.moodle.org/en/Cron) is running: scheduled tasks send the statements.

To check that statements reach the LRS, look for "xAPI statements sent to the LRS" in Site administration > Reports > System status.

## Which events are sent

- **Include actions with these routes** lists every supported event (see [Supported events](supported-events.md)). New sites send all of them except private messages, notes and searches, whose statements carry the text of the message or note, or the search query. Sites that upgrade keep their choice, so events added in a new version are off until you turn them on.
- **Log guest actions** sends the actions of guests and of users who aren't logged in.
- **Anonymous events** decides what happens to events Moodle marks as anonymous, such as anonymous feedback, or assignments with blind marking: sent with an anonymous actor in place of the hidden user (the default), or not sent.

## How users are identified

By default the actor is an account whose name is the user's Moodle id, with the site URL as its homePage, and the user's full name. Under "Actor identification":

- **Identify users by email** sends the user's email address (`mbox`) instead, for users with a valid one.
- **Identify users by username** uses the username as the account name, when users aren't identified by email.
- **Actor Account HomePage** replaces the site URL as the accounts' homePage.
- **Set the actor name field** sends the user's full name; turn it off to leave names out.

## What statements contain

Two settings under "Statement content" decide how much of what learners write and answer reaches the LRS:

- **Send content**, on by default, sends what people write with the statements about it: forum post subjects and messages, private messages, wiki pages and comments, glossary definitions and comments, book chapters, notes, essay and short-answer text, a teacher's comment on a lesson essay, and feedback text answers. Turn it off if the LRS doesn't need the text, and the statements still say what happened. Feedback text answers are never sent from anonymous feedback.
- **Send response choices**, off by default, sends the possible and correct answers of quiz, lesson and feedback questions.

The name of a personal calendar event is never sent.

## Proxy

Statements are sent through the proxy set in Site administration > Server > HTTP. If your site has a proxy but reaches the LRS directly, for example on your internal network, add the LRS host to "Proxy bypass hosts".

## When the LRS is unavailable

Events wait in the queue and are sent once the LRS works again. The check "xAPI statements sent to the LRS", in Site administration > Reports > System status, warns when an event has been waiting more than an hour, and shows the last error from the LRS.

When the LRS has failed on the batch at the head of the queue with a server error (5xx) in five runs, the batch is split to find the events it fails on. An event the LRS fails on alone goes to the error report if the LRS took the rest, and to the end of the queue if it took nothing, as it may be down.

## Failed events

Events the LRS rejects, and events that fail to transform, go to the error report: Site administration > Plugins > Logging > Logstore xAPI > Logstore xAPI Error Log. Viewing it needs the "View xAPI error log" capability (`logstore/xapi:viewerrorlog`). Once the problem is fixed, "Resend" queues the matching events again; it needs "Replay failed statements" (`logstore/xapi:manageerrors`). Failed events are kept until you resend them, unless you set "Keep failed events for". The "Delete old xAPI log rows" task cleans up the plugin's other log tables.

Under "Notifications", the plugin can email the members of chosen cohorts, and "Additional email addresses", when at least "Error notification trigger" events have failed since the last email. The "Send failed notifications" task checks every two hours.

## Send past events

The plugin can send events from before it was installed, or from a time when it was disabled. It reads them from Moodle's standard log, through the historic report.

### Before you start

- The events must be in the standard log. The "Standard log" store must have been enabled when they happened, and must still hold them: its "Keep logs for" setting deletes older ones.
- Only events whose routes are selected in "Include actions with these routes" are listed and sent.
- Events from guests and users who aren't logged in are left out unless "Log guest actions" is on, and anonymous events follow the "Anonymous events" setting, as for live events.

### Send them

1. Go to Site administration > Plugins > Logging > Logstore xAPI > Logstore xAPI Historic Log. You need the "Manage historic data" capability (`logstore/xapi:managehistoric`).
2. Choose a start date, and optionally an end date, a course (and one of its activities), a user or event names. Select "Search". The report lists the matching events that haven't been sent.
3. Select "Resend". A background task, "Queue xAPI events to be sent again", moves them to the queue at the next cron run, a chunk at a time.

The "Emit historical records to LRS" scheduled task sends the queue every hour, in batches of "Maximum batch size for historical requests", until the queue is empty or "Time limit for each run" has passed. To send them sooner, run the task from the command line in the Moodle directory:

```bash
php admin/cli/scheduled_task.php --execute='\logstore_xapi\task\historical_task'
```

The plugin records each event it sends, live or historic, so sent events drop out of the report. Each statement's id is built from the event and the statement's content, so if the same statement is sent twice, the LRS keeps one copy.

### Large date ranges

Events are moved to the queue in the background, so a large date range doesn't time out. The queue still holds every event until it is sent, so on a busy site, send a few weeks or months at a time rather than several years at once.

### Don't copy the standard log into the queue

Earlier versions of this guide suggested copying rows from `logstore_standard_log` into `logstore_xapi_log` with SQL. Don't: the tables have different columns, and copied rows skip the checks above, the record of sent events and the link to the standard log row.
