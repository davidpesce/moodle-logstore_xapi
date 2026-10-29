# Configure the plugin

Go to Site administration > Plugins > Logging > Logstore xAPI > General Settings (`/admin/settings.php?section=logsettingxapi`).

1. Set "LRS endpoint" to your LRS's xAPI endpoint, for example `https://lrs.example.com/xapi`. Use `https://`: over plain `http://` the LRS credentials and learner data are not encrypted, and the security report warns about it.
1. Choose the "LRS authentication":
   - **Basic (key and secret)**, the default: set "LRS auth key" and "LRS auth secret" to the key and secret from your LRS.
   - **OAuth2 client credentials**: set "LRS auth key" and "LRS auth secret" to the client ID and client secret, and "OAuth2 token endpoint" to your identity provider's token endpoint. Set "OAuth2 scope" if your provider needs one.
1. Click "Save changes".

By default the plugin sends statements from a scheduled task every minute ("Send statements by scheduled task?"), so pages don't wait for the LRS. Make sure [cron](https://docs.moodle.org/en/Cron) is running.

## What statements contain

Two settings under "Statement content" decide how much of what learners write and answer reaches the LRS:

- **Send content**, on by default, sends the text people write with the statements about it: forum posts, private messages, wiki pages and comments, glossary definitions and comments, book chapters, notes, essay and short-answer text, a teacher's comment on a lesson essay, and feedback text answers. Turn it off if the LRS doesn't need the text, and the statements still say what happened. Feedback text answers are never sent from anonymous feedback.
- **Send response choices**, off by default, sends the possible and correct answers of quiz, lesson and feedback questions.

The name of a personal calendar event is never sent.

## Proxy

Statements are sent through the proxy set in Site administration > Server > HTTP. If your site has a proxy but reaches the LRS directly, for example on your internal network, add the LRS host to "Proxy bypass hosts".

## When the LRS is unavailable

Events wait in the queue and are sent once the LRS works again. The check "xAPI statements sent to the LRS", in Site administration > Reports > System status, warns when an event has been waiting more than an hour, and shows the last error from the LRS.
