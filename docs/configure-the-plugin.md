# Configure the plugin

Go to Site administration > Plugins > Logging > Logstore xAPI > General Settings (`/admin/settings.php?section=logsettingxapi`).

1. Set "LRS endpoint" to your LRS's xAPI endpoint, for example `https://lrs.example.com/xapi`. Use `https://`: over plain `http://` the LRS credentials and learner data are not encrypted, and the security report warns about it.
1. Choose the "LRS authentication":
   - **Basic (key and secret)**, the default: set "LRS auth key" and "LRS auth secret" to the key and secret from your LRS.
   - **OAuth2 client credentials**: set "LRS auth key" and "LRS auth secret" to the client ID and client secret, and "OAuth2 token endpoint" to your identity provider's token endpoint. Set "OAuth2 scope" if your provider needs one.
1. Click "Save changes".

By default the plugin sends statements from a scheduled task every minute ("Send statements by scheduled task?"), so pages don't wait for the LRS. Make sure [cron](https://docs.moodle.org/en/Cron) is running.

## Proxy

Statements are sent through the proxy set in Site administration > Server > HTTP. If your site has a proxy but reaches the LRS directly, for example on your internal network, add the LRS host to "Proxy bypass hosts".

## When the LRS is unavailable

Events wait in the queue and are sent once the LRS works again. The check "xAPI statements sent to the LRS", in Site administration > Reports > System status, warns when an event has been waiting more than an hour, and shows the last error from the LRS.

When the LRS fails on the same batch with a server error (5xx) five runs in a row, the batch is split to find the events it fails on. An event the LRS fails on alone goes to the error report if the LRS took the rest, and to the end of the queue if it took nothing, as it may be down.

## Failed events

Events the LRS rejects go to the error report (Logstore xAPI Error Log), where you can resend them once the problem is fixed. They are kept until you resend them, unless you set "Keep failed events for". The plugin's other log tables are cleaned up by the "Delete old xAPI log rows" task.
