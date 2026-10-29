# Changelog

A summary of each release. The full notes, including upgrade instructions, are on the
[GitHub releases page](https://github.com/davidpesce/moodle-logstore_xapi/releases), which is
the authoritative record.

## 5.4.0 (unreleased)

### Development

- **The code checker fails the CI build** on any error or warning (#1000); it only reported them.
  The copy/paste detector and mess detector still only report. The workflow checks the code out
  with `actions/checkout@v7`.
- **Forum test fixtures use the object tables core logs** (#1000): `forum_posts`,
  `forum_discussions`, `forum_subscriptions` and `forum_discussion_subs`, not `forum`.

### Statement fixes

- **Quiz question `success` comes from the grade the question engine gave** (#992). It was
  worked out by comparing the answer with the right answer, which went wrong for numerical
  questions with more than one answer that gets full marks, numerical answers in a unit with a
  multiplier ("5 km" when the unit is m), and multiple-choice answers that Moodle formats. It is
  now `true` when the question was graded right; `false` when it was wrong, partly right or not
  answered; and left out while it needs grading. Short-answer questions now send `success` too.
  Numerical definitions list every answer that gets full marks as a correct response.
- **Multiple-choice responses match the defined choices** (#993). Quiz responses were made from
  Moodle's summary of the answer, which formats it (spaces for `&nbsp;`, entities, filters), so
  "Option&nbsp;A" sent a response of `option-a` for a choice of `optiona`, and an answer
  containing "; " became two. They are now read from the attempt's step data, as are true/false
  responses. Choices whose text gives the same id ("Yes" and "yes") each get their own (`yes`,
  `yes-2`) in quizzes, lessons, feedback and Choice activities, and responses use them. In a
  single-answer quiz question every answer with full marks is a correct response; in a
  multiple-answer question, every answer with a positive grade, together.
- **Quiz attempt reviewed has the viewer as the actor** (#994). Moodle records it each time
  someone opens an attempt's review page. The statement said the attempt's owner "received" a
  review, with the viewer as instructor, so students who looked at their own attempt were sent as
  their own instructor. It is now "viewed", by the viewer, "for" the student when the viewer is
  someone else. LRS reports built on the old statement need updating.
- **Manually graded questions are scored in marks** (#996). A 10-mark question graded 5 was sent
  as raw 0.5, max 1; it is now raw 5, max 10, as attempt scores are. The scaled score is
  unchanged.
- **Lesson statements report the answer and the retake the event was about** (#995). A page that
  takes several answers in a retake sent the newest answer for each of them, so a wrong answer
  followed by a right one sent two right answers. "Completed" sent the gradebook grade, which
  follows the lesson's handling of retakes (mean or highest); it now sends the grade of the
  retake that ended.

## 5.3.0 (2026-10-29)

Feature release. Supports Moodle 4.5 to 5.3. Statements use the site proxy, can authenticate with
OAuth2, wait in the queue while the LRS is unavailable and carry ids. One database change (an
indexed column in the failed log); four new settings.
[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.3.0)

### New events

- **Game activities** (the third-party [mod_game](https://moodle.org/plugins/mod_game) plugin):
  "Game played" and "Course module viewed" are sent. Contributed by David Herney (#890).
  mod_game records "Game played" each time a game page loads, which is on every move, so one
  game sends many "Played" statements. Turn off the `\mod_game\event\game_played` route if
  you don't want them. On sites upgrading from an earlier version, the new routes are off until
  you turn them on, as the saved route list is kept.

### Privacy

- **Correct answers are only sent when "Send response choices" is on, for every question type.**
  Before, the setting only covered multiple-choice and sequencing questions. True/false,
  short-answer, numerical and matching questions in quizzes, lessons and feedback activities
  always sent their correct answer (`correctResponsesPattern`), and matching questions their
  pairs, even with the setting off. Sites with the setting off no longer send them; turn it on
  if your LRS reports rely on them.
- **Search events are off by default on new sites.** Their statements carry the user's search
  query, which can hold names, health or other personal details. Sites upgrading keep their
  routes; turn off `\core\event\search_results_viewed` if you don't want queries in the LRS.
- **Calendar subscription statements no longer send the subscription's URL** (#985). It was sent
  as a "Web Calendar" activity, but the URL of a private calendar (Google's secret address,
  Outlook published calendars, Moodle's own export link) gives access to the whole calendar, and
  it was sent again each time cron refreshed the subscription. A calendar imported from a file
  sent an activity with an empty id, which strict LRSs reject.

### Registration per attempt

- **Statements about an attempt carry a `context.registration` again**, one per attempt: quiz
  attempts (including each question answered and manually graded questions), SCORM attempts
  (status and score), lesson retakes (pages, start, resume, restart, end and essay grading) and
  assignment attempts (submitted and graded; a reopened submission is a new attempt, and a
  submission a teacher makes for a student is the student's attempt). Every
  statement with the same registration is about the same attempt. It is a version 5 UUID of a
  site-specific name for the attempt, so the historic report and retries give the same value.
  5.2.0 removed the old registration, which came from the session key and linked unrelated
  statements. Other statements, and anonymous events, have none. LRS reports that group by
  registration will start grouping these activities by attempt.

### Statement fixes

- **Lesson questions with several right answers** send each one as its own correct response.
  They were joined into one pattern, which in xAPI means "all of these together", for
  short-answer, numerical and single-answer multiple-choice pages. Multiple-answer pages still
  need all their right answers together. Short-answer pages that use regular expressions no
  longer send the expressions as correct responses, and answers with a `*` wildcard are left
  out, as they are patterns too.
- **Quiz numerical answers typed with a unit** ("5 m"), a decimal comma ("12,5") or an
  exponent as Moodle accepts it ("1.5x10^3") send the number alone as the response, which a
  numeric interaction requires. `success` is now right for them too; they were always reported
  as wrong. Answers in a unit with a multiplier ("5 km" when the unit is m) are still reported as
  wrong.
- **Scores with no range** (a maximum grade of 0, or min equal to max) send the raw score only.
  xAPI requires max to be greater than min, and strict LRSs rejected these statements. This
  applies to quiz attempts and manually graded questions, lessons, lesson essays, SCORM and
  assignment grades.
- **Quiz attempts are scored with the attempt's own grade.** The "completed" statement for an
  attempt sent the user's gradebook grade, which follows the quiz's grading method (highest,
  average, first or last attempt), so attempt 2 could report attempt 1's score. It is now the
  attempt's marks scaled to the quiz's maximum grade. An attempt that still needs manual
  grading is sent without a score or success.
- **Abandoned quiz attempts** (time ran out and the attempt was never submitted) are sent as
  "abandoned" and incomplete. They were sent as "completed", with an "answered" statement for
  every question.
- **`success` is left out when no pass grade is set.** Quiz attempts, lessons and assignment
  grades sent `success: true` for any grade, including 0, when the pass grade was 0.
- **Assignment feedback without a grade is sent without a score or success** (#984). When a
  teacher only gives feedback, Moodle saves the grade as -1 or leaves it empty. The statement
  said the student failed when the assignment has a pass grade, or sent a score of 0.
- **Feedback text answers are sent as typed** (#986). Moodle stores "Short text answer" and
  "Longer text answer" responses HTML-escaped, and they were sent that way
  (`Tom &amp; Jerry&#039;s`).
- **Grade items are looked up by item number**, so an activity with outcomes uses its own grade
  item rather than whichever item the database returned first.
- **SCORM statuses are no longer all sent as "completed".** Only `completed` is sent as
  completed; `incomplete` is sent as "progressed". `not attempted`, `unknown` and `browsed` say
  nothing about progress (a SCO writes them when it is launched), so no statement is sent for
  them. A raw score saved while the status is one of those is sent as "scored".
- **H5P content viewed outside an activity** (the content bank, or content embedded in a course,
  section or user text) is identified by its file URL, with the course as parent when there is
  one. It was transformed as a course module view, which read the wrong activity or failed. When
  the content is played in the Moodle app, the URL holds the user's private access key; it is
  removed, so the key doesn't reach the LRS and the content has one id for everyone.
- **Select missing words (gapselect) questions are sent as `matching`, not `sequencing`.** Each
  gap is a source and each choice a target, so the response says which choice went in which gap
  (`gap-1[.]spicy[,]gap-2[.]mango`). Sequencing could not describe a choice that is right for
  two gaps, distractors, or a gap left empty. The response extension is now
  `http://learninglocker.net/xapi/cmi/matching/response`. LRS reports built on the old
  sequencing statements need updating.
- **Matching questions with distractors** no longer send a source with an empty id and a pair
  like `[.]purple`; distractors are targets only. A choice shared by several stems is listed
  once, so target ids are unique. Lesson matching pages no longer send their correct and wrong
  answer feedback as sources.
- **Matching responses are read from the attempt, not its text summary.** Quiz matching and random
  short-answer matching responses were split out of Moodle's "stem -> choice; …" summary, which
  went wrong when a stem or choice contained "; " or " -> ". They are now read from the attempt's
  step data, as the question engine stores them. Targets are listed in the order the attempt
  showed them. A stem that is an image alone is a stem, as in Moodle, labelled with its alt text
  or file name.
- **Random short-answer matching (randomsamatch) questions are defined by their attempt.** Their
  stems are short-answer questions drawn for each attempt, but the definition was read from match
  question tables that randomsamatch never uses, so in production it had no stems or choices and a
  correct response of `""`. It now lists the stems and choices the attempt showed.
- **Lesson correct answers follow the lesson's scoring mode.** With custom scoring, an answer is
  correct when it scores more than 0, as before. Without it, an answer is correct when it moves
  the learner forward (to the next page, a later page or the end of the lesson), as in core.
  Those lessons sent no correct answers, or outdated ones from scores left over from custom
  scoring.
- **"Actor Account HomePage" applies to every actor account**, as its description says. It was
  only used with "Send username" on; accounts identified by user id, and the anonymous actor,
  always used the site URL. The setting must now be an absolute URL (or empty, for the site
  URL); a saved value that isn't one is ignored, as an invalid homePage made every statement
  fail.
- **Five events that failed every time are sent** (#938). Each went to the failed log, and a
  resend failed again:
  - Notes viewed for a whole course or the site is sent without a note subject.
  - Submission viewed for a group submission is "for" the group, or has no "for" when the
    submission is in the default group or the assignment uses blind marking (the group's
    members are the students to hide).
  - Feedback viewed for a grade with no grader in the assignment (one entered in the gradebook)
    is sent without an instructor.
  - Question created and questions imported in a system or course category question bank
    (Moodle 4.5) are sent without a course.
- **Badge viewed is sent** (#983). It read the badge from the event's object id, which Moodle
  doesn't set for this event, so every badge view went to the failed log.
- **Events from users who aren't logged in no longer fail every time** (#972). Moodle logs them
  with user id 0: mostly visits to the site home when users don't have to log in, and CLI
  scripts that set no user. "Log guest actions" now covers them, as it does in the standard log:
  with it off (the default), they are not sent, except from CLI scripts. With it on, they are
  sent with a "Not logged in" actor (account name `0`). Before, they were queued whatever the
  setting, and each one went to the failed log with "user not found".

### Connection to the LRS

- **The site's proxy settings are used** (#910). Statements are sent with Moodle's HTTP client
  (`\core\http_client`), which applies the proxy host, port, type, credentials and bypass list
  under Site administration > Server > HTTP. Before, the proxy settings were ignored, so a site
  that can only reach the internet through a proxy couldn't send at all.

  **Before you upgrade:** if your site has a proxy but reaches the LRS directly, for example on
  your internal network, add the LRS host to *Proxy bypass hosts*. Otherwise statements go
  through the proxy, and fail if it can't reach the LRS. The list is read as Moodle documents
  it: host names, `*.domain` patterns and IP ranges such as `192.168.0.0/16`. Sites without a
  proxy are not affected.
- Moodle's blocked hosts and allowed ports (Site administration > General > Security > HTTP
  security) are not applied to the LRS, as for core's own admin-configured services such as
  Solr. An LRS on an internal network or a port such as 8080 keeps working.
- The connection to the LRS is reused for all the batches of a run, a batch with no statements
  is no longer sent, and invalid UTF-8 in a statement is replaced instead of failing the batch.
- **OAuth2 client credentials** (#895). A new "LRS authentication" setting switches from Basic
  auth to OAuth2: the key and secret are used as client ID and client secret to get an access
  token from your identity provider's token endpoint (with an optional scope), and the token is
  sent to the LRS. Tokens are cached until shortly before they expire, and a new one is fetched
  once if the LRS refuses the current one. Basic stays the default, so nothing changes on
  upgrade. Based on a contribution by piptouque.
- **The security report warns about a plain `http://` endpoint**, and with OAuth2 a plain
  `http://` token endpoint, which send the LRS credentials and learner data unencrypted. It's a
  warning, not an error, as some sites run an LRS over HTTP on a private network.

### When the LRS can't take statements

- **Events wait in the queue while the LRS is unavailable** (#910). When the LRS can't be
  reached (no connection, a timeout, a TLS error, a missing CA bundle file), redirects (3xx),
  refuses the credentials (401, 403, or a failed OAuth2 token request), isn't at the endpoint
  (404), asks to wait (408, 429) or fails (5xx), the batch stays in the queue and is sent on a
  later run, and the rest of the run is not sent. Before, every run moved another batch to the
  failed log, to be resent by hand. Other errors, such as a 400 for bad data, still go to the
  failed log. In real-time mode, these events go to the queue for the emit task. An endpoint that
  isn't set is treated the same way.
- **Each run sends the whole queue** (#930). The live, failed and historic tasks used to send
  one batch per run, so the live task sent at most 30 events a minute (about 43,000 a day), and a
  busier site fell behind for good. Each run now sends batch after batch until the queue is
  empty, the LRS can't take statements, or the new "Time limit for each run" (default 5 minutes)
  passes. The oldest events are sent first. The batch size settings keep their meaning; the task
  prints one summary per run.
- **A batch that is too large (413) is split** until it fits; an event that is too large on its
  own goes to the failed log.
- **A new check in the system status report** (Site administration > Reports > System status),
  "xAPI statements sent to the LRS", warns when an event has been waiting more than an hour, and
  shows the last error from the LRS. Events that wait don't appear in the error report and don't
  trigger the failure notification emails, so this is where an outage shows.

### Resending without duplicates

- **Statements have ids, so the LRS ignores a resend** (#931). Each statement's id is a
  version 5 UUID of the site URL, the event's standard log id and the statement's content. A
  statement sent again unchanged (after a timeout where the LRS had stored the batch, from the
  failed log, or by replaying history) has the same id, and the LRS keeps one copy. A statement
  whose content has changed, for example after an upgrade that fixes it, gets a new id and is
  stored next to the old one: an LRS can't change a stored statement, and a correction is never
  dropped. Statements sent by earlier versions have ids the LRS chose, so replaying those events
  still stores them again.
- **A conflict (409) no longer fails the batch.** An LRS that already has a statement with the
  same id may answer 409 and reject the whole batch. The batch is now split, whatever "Resend
  failed batches" is set to, so the other events are stored, and the event the LRS already has
  counts as sent.
- **A batch is removed from the queue only once its failures are stored.** Both happen in one
  database transaction, so an error part-way leaves the batch queued, and the resend is ignored
  by the LRS instead of being stored twice.

### Performance

- **Fewer database reads per batch** (#934). Each batch keeps the records it has read, so the
  course, course module, activity, user and other records an event needs are read once, not
  again for every statement. A 50-question quiz attempt went from about 820 reads to about 110.
- **No standard log lookup in the user's request** (#933). In background mode, each event
  looked up its `logstore_standard_log` row when it was logged, and again when it was sent. It
  is now looked up once, when it is sent, using the standard log's user and module index. Each
  sent event is recorded once per batch, not twice per event.

### Error report

- **The response filter scales.** It listed every distinct response, read from the TEXT column on
  every page load, and transform errors included the event id, so each failure was its own option.
  The filter now lists the 100 most frequent responses, grouped by a hash of the response stored
  in a new indexed column, and transform errors leave out the event id (it has its own column).
  After upgrading, an ad-hoc task indexes existing failures in batches when cron runs; until it
  finishes, the filter doesn't list them, and the report says so.
- **The error type is indexed**, and both reports list events in a fixed order, so rows no longer
  move between pages.

### Historic report

- **Filter by course and activity, instead of by context.** The context filter listed every
  context in the standard log, which meant scanning the whole table each time the report opened,
  and it matched one context exactly, so choosing a course left out its activities' events. Now
  you choose a course (searched by name), which matches every event in it, and optionally one of
  its activities.
- **A start date is required**, and the report starts with the last 30 days. Without one, the
  report searched the whole standard log, which on a large site can take longer than a page
  request is allowed.
- **Sent events are no longer listed as unsent** (#933). When a historic event was sent, it was
  matched to the standard log again and could take the id of an identical event in the same
  second, so the report went on listing the other one. Historic events now keep their id, and
  identical live events in the same second are matched to different rows.
- **Events that are never sent are no longer listed.** Events from guests and users who aren't
  logged in (when "Log guest actions" is off) and anonymous events (when they are set not to be
  sent) were listed on every visit, as sending them skips them without recording them as sent.
- **A link with the error report's filters no longer breaks the historic report** (#990). Error
  type and response filters in the page's URL were applied to the standard log, which has
  neither column, and the page showed a database error.
- **The historical events guide describes the historic report** (#991). It said sending past
  events wasn't built in, and suggested copying the standard log into the queue with SQL, which
  fails and would skip the plugin's checks.

### Removed

- **Oracle is no longer supported.** Moodle 5.0 dropped it, and the plugin isn't tested on it.
  The error report compared the TEXT response column directly, which fails on Oracle (#916).

### Development

- **The plugin's PHPUnit tests no longer need `composer install`.** On a git checkout without
  the plugin's `vendor/` directory, loading the tests was a fatal error that stopped the whole
  site's PHPUnit run. Without it, only the xAPI schema check is now skipped. Release zips don't
  include the tests and are not affected.
- **Seven tests that had been skipped since 2025** are replaced with tests that run: a batch the
  LRS can't receive goes to the failed log, moving events back to the queue (failed and
  historic), and the report filter form.
- **The event tests run with "Send response choices" on**, so choices and correct responses are
  checked against every response by default.
- **The old release scripts are removed** (`scripts/`). They came from the Travis CI release
  process and would build a wrong `version.php` and an incomplete zip. Releases are built by the
  `moodle-release` and `camp-release` workflows when a version tag is pushed.

## 5.2.1 (2026-10-26)

Bug-fix and privacy release. Supports Moodle 4.5 to 5.3. No database changes; one new setting.
[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.2.1)

### Privacy

- **Anonymous events no longer identify the hidden user.** Moodle marks an event as anonymous
  when a user's identity must stay hidden, as with assignments while blind marking is on, and
  anonymous Feedback activities. Assignment statements still named the student under blind
  marking. That user is now replaced by an anonymous actor wherever they appear (actor,
  instructor, team or extensions); other people, such as the grader, are still identified, and
  assignment feedback comments are left out. A new setting, "Anonymous events", can instead keep
  these events out of the LRS entirely.
- **Routes and "Log guest actions" now apply to every event that is sent**, not only when an event is
  captured. Before, the historic report (whose event list offered every supported event, not only
  the enabled ones), a resend from the error report, and `moveback_to_log.php` could send events
  whose route is off, such as messages and notes, which 5.2.0 turned off by default. Events whose
  route is off are now dropped from the queue without being sent, and the historic report lists
  only enabled events.
- **The forum user report statement no longer names the viewed user when "Set the actor name field" is
  off.** Its activity name is now "forum posts of user 123".
- **LRS error responses are escaped** before being shown as debugging output, and stack traces
  are shown at developer debugging level only.

### Statement fixes

- **Choice activity `choices` are a JSON array.** With "Send response choices" on, they were sent
  as an object keyed by option id, which strict LRSs reject.
- **Unanswered numerical, short-answer and multiple-choice quiz questions** send `response: ""`
  and `completion: false`. Numerical and short-answer questions sent `response: null` (rejected by
  strict LRSs), and all three sent `completion: true`.
- **Manually graded quiz questions** report the graded question. The first question in the
  attempt was reported whichever one was graded.
- **Badges** send the badge's language as a language tag (`pt-br`, not `pt_br`). Empty badge,
  course category and message descriptions are left out instead of being sent as `null`.
- **Top-level course categories:** "category created" statements failed for every top-level
  category. They are now sent, without a parent.
- **Face-to-face attendance** sends `completion: true` for a fully attended session; it was
  always false.
- **Assignment submission unlocked** displays "Unlocked" (was "Unocked").

### Other fixes

- **The error report shows non-JSON responses as text.** cURL errors and plain-text or HTML
  error pages from the LRS were shown as `false`. Context and event names in the reports are
  escaped.
- **Failure notification emails** sent to an additional address no longer change Moodle's
  cached support user, which could send later support mail in the same cron run to that address.

### Known issue

- Requests to the LRS still do not use Moodle's proxy settings (see 5.2.0). The LRS client is
  moving to Moodle's `\curl` class in 5.3.0 (#910).

## 5.2.0 (2026-10-24)

xAPI compliance, privacy and bug-fix release. Supports Moodle 4.5 to 5.3.
[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.2.0)

This release changes some statement values to follow the xAPI specification. If your LRS
reports or dashboards filter on the values below, review them before upgrading. The upgrade
changes the database (see "Notification log" below).

### Statement changes

- **Scaled scores now read as a fraction of the maximum.** `result.score.scaled` is sent for quiz
  attempts and manually graded questions, assignment grades, lesson results and essays, and SCORM
  scores. Since 4.8.0 it mapped the score range onto -1 to 1, so 0% was sent as -1, 50% as 0 and
  75% as 0.5, and reports that show `scaled` as a percentage (as most do) showed a student who
  scored 50% as 0%. It is now `(raw - min) / (max - min)`: 50% is 0.5 and 100% is 1. Only scores
  that can go negative (some SCORM packages) use the range below 0, with 0 meaning no score.
  Statements already in your LRS keep the old values, so adjust any report or threshold that
  compares `scaled` across the upgrade, or use `raw`, `min` and `max`.
- **No more `context.registration`.** It was made from the Moodle session key, so it did not
  identify an attempt or enrolment as xAPI intends: in background mode, statements from different
  users could share one value, and in real-time mode it linked everything a user did in one
  login. Statements no longer carry it. If your LRS reports group or filter by registration, they
  will not match new statements.
- **Interaction ids** (choice, matching and sequencing questions) now keep accented and
  non-Latin letters, and a non-breaking space becomes a dash. For example `naïve` was `nave` and
  is now `naïve`, and a Japanese answer that used to give an empty id now gets one (#894, #897).
- **Matching responses:** the `http://learninglocker.net/xapi/cmi/matching/response` extension is
  keyed by the question's source ids rather than its text, whose dots broke Elasticsearch-backed
  LRSs (#892, #898).
- **Choice responses** are the selected choices' ids, as xAPI requires, for Choice activities,
  Feedback rated multiple-choice items and Lesson pages. The readable text stays in the choice
  response extension.
- **No made-up correct answers:** Feedback items and Choice activities no longer send a
  `correctResponsesPattern`, which claimed that selecting every option was the only correct
  answer and made LRSs mark real answers as wrong. Feedback numeric items no longer send one
  either: it was the item's allowed input range, and Feedback is not graded. Quiz and Lesson
  numeric questions keep theirs.
- **Lesson questions** described their choices and correct answers with the feedback text
  instead of the answer text. Numerical ranges are now sent as ranges.
- **Quiz true/false** responses are always `true` or `false`; on non-English sites they were the
  translated label, and the correct answer was recorded wrongly.
- **SCORM 2004** passed and failed statuses are recorded as such (they were all "completed"),
  SCORM 2004 score bounds are read, and decimal scores are kept. A score outside its own bounds
  no longer gets the LRS to reject the statement.
- **Quiz attempt names** use the attempt number ("Quiz Attempt 2") instead of the database id.
  The activity id is unchanged.

### Privacy

- **Private messages and notes are off by default on new installs.** Their statements include
  the full text of the message or note. On a new site, the message sent, message viewed, group
  message sent, note created, note updated and notes viewed events start unticked in "Include
  actions with these routes". Existing sites keep their current selection.
- **Notification log without email addresses.** `logstore_xapi_notif_sent_log` stored the
  address of every recipient of a failure notification, though only the last notified failure
  is ever read. The upgrade drops the `email` column, deleting the stored addresses, and each run
  is now logged once.
- The three report capabilities are flagged as giving access to personal data (`RISK_PERSONAL`),
  since the reports show users' names and events.

### Fixes

- **Message viewed** statements failed for every message read on a real site: the transformer
  read the read-receipt record instead of the message. They now describe the message, and their
  object id is the message's id. Message sent statements and Certificate revoked statements
  outside a course also failed, looking up a course with id 0.
- **BigBlueButton views** are sent as "viewed" statements. The mapping pointed at an event that
  does not exist, so they were never sent. On existing sites, tick "Course module viewed" under
  BigBlueButton in "Include actions with these routes" to send them; new installs send them by
  default.
- **Failure notification emails** are sent once per address. A user in two selected cohorts, or
  in a cohort and also listed as an additional address, used to get one email for each. Suspended
  and deleted users are no longer emailed, and the task's output goes to the task log. The emails
  load only the recipient fields needed to send them, and their `lang` attribute follows the site
  language instead of always being English.
- **Reports:** the filters carried in the report URL are checked, so a malformed link shows the
  unfiltered report instead of an error, and the page size is limited to 1 to 500 rows. A page
  size of 0 used to load every row at once.
- **Faster admin pages:** the plugin's settings no longer list every event on the site to work
  out which of its events are available, which ran on every admin page.
- The "Core" and "H5P" headings in the routes setting and the cohort remove button's
  screen-reader label can be translated.

### Removed

- **Totara support.** The `\totara_program\event\program_assigned` transformer and the Totara
  platform name in statements are gone. Totara reports a Moodle 3.4-era version, and this plugin
  has required Moodle 3.5 or later since 4.6.0 (March 2020), so 4.5.0 was the last release that
  could be installed on Totara.
- Code clean-up: the report filter form class is `\logstore_xapi\form\reportfilter_form`, the
  upgrade helpers are prefixed with `logstore_xapi_`, and an unused jQuery UI dependency, an empty
  helper and a mapping for a BigBlueButton event that does not exist are removed.

### Known issue

- Requests to the LRS do not use Moodle's proxy settings, so a site that can only reach its LRS
  through a proxy cannot send statements. This is not new in 5.2.0. The LRS client is moving to
  Moodle's `\curl` class in 5.3.0 (#910).

## 5.1.1 (2026-10-23)

Privacy and reliability release. Supports Moodle 4.5 to 5.3.
[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.1.1)

- **Privacy:** anonymous Feedback responses were sent to the LRS with the respondent's identity.
  They are now sent anonymously, and without the session-based registration that could link them
  to the same user's other statements in real-time mode. Statements already in your LRS may
  identify respondents.
- **Security:** the error report's Response filter displayed LRS error responses without escaping
  them, so HTML in a response could run as script for whoever opened the report.
- The reports now check permissions before running their queries, so users without access can no
  longer trigger a full scan of the standard log.
- SCORM score and status statements looked up the wrong attempt and SCO, so they failed to send or
  reported the wrong data.
- A user tour ending on a relative page URL produced a statement the LRS rejected, along with the
  rest of its batch. The object id is now always absolute.
- Quiz attempt view statements named the wrong attempt, or failed to send.
- Automatically awarded badges are now sent; manually awarded badges name the correct awarder.
- One failing event can no longer stall delivery for the whole site.
- In real-time mode, failed events are kept in the failed log so they can be replayed.
- LRS requests time out (10 s to connect, 60 s in total) instead of waiting indefinitely.
- Feedback multiple-choice answers other than the first option are no longer rejected by the LRS
  (#899). The response now holds the selected choices' ids, as the xAPI specification requires,
  with the readable text in the choice response extension.
- The privacy registry lists the LRS as an external location.
- Resending events from the report can no longer select every row when given an empty list.
- Failure notification emails are checked every two hours, instead of every minute in alternate
  hours.
- On Moodle 5.x, changing a report filter now disables Resend until the report is re-run, so the
  confirmed count matches what is resent. Report and settings markup works with Bootstrap 5.
- The historic report's username column was labelled "LRS auth key".
- Removed an unused development-only transformer.
- Moodle 5.3 support.

## 5.1.0 (2026-07-21)

Supports Moodle 4.5 to 5.2 (previously 4.3 to 5.1).
[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.1.0)

- Privacy API export and deletion now cover the plugin's event tables.
- Events no longer fail to transform when the standard log store's JSON format is enabled.
- Event data deserialisation and the report's filter column are restricted to allow-lists.

## 5.0.3 (2026-07-20)

Security release. [Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.0.3)

- The failed-event report required no capability.
- TLS certificate verification is enabled for the LRS connection, with a custom CA bundle option.
- Output in the admin reports is escaped.

## 5.0.2 (2026-07-20)

[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.0.2)

- JISC support removed; the platform has been discontinued (#889).
- The settings page no longer takes minutes to load on sites with many cohorts (#842).
- Null and empty matching question responses no longer cause errors (#891).
- Admin settings grouped under headings (#888).

## 5.0.1 (2026-02-23)

- Coding style fixes (#886).

## 5.0.0 (2026-02-17)

Supports Moodle 4.3 to 5.1.

- SCORM events work with the table changes in Moodle 4.3 (#853).

## 4.9.0 (2026-02-17)

- Fixes for lesson, quiz numerical and manually graded questions, book subchapters and operand
  type errors (#849, #866, #869, #870, #874, #875, #880, #883, #884).
- Removed test definitions that conflicted with core Moodle tests (#876).

## Earlier releases

See the [GitHub releases page](https://github.com/davidpesce/moodle-logstore_xapi/releases).
