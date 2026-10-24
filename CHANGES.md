# Changelog

A summary of each release. The full notes, including upgrade instructions, are on the
[GitHub releases page](https://github.com/davidpesce/moodle-logstore_xapi/releases), which is
the authoritative record.

## 5.2.0 (unreleased)

This release changes some statement values to follow the xAPI specification. If your LRS
reports or dashboards filter on the values below, review them before upgrading.

- **Scaled scores now read as a fraction of the maximum.** `result.score.scaled` is sent for quiz
  attempts and manually graded questions, assignment grades, lesson results and essays, and SCORM
  scores. Since 4.8.0 it mapped the score range onto -1 to 1, so 0% was sent as -1, 50% as 0 and
  75% as 0.5, and reports that show `scaled` as a percentage (as most do) showed a student who
  scored 50% as 0%. It is now `(raw - min) / (max - min)`: 50% is 0.5 and 100% is 1. Only scores
  that can go negative (some SCORM packages) use the range below 0, with 0 meaning no score.
  Statements already in your LRS keep the old values, so adjust any report or threshold that
  compares `scaled` across the upgrade, or use `raw`, `min` and `max`.
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
  answer and made LRSs mark real answers as wrong.
- **Lesson questions** described their choices and correct answers with the feedback text
  instead of the answer text. Numerical ranges are now sent as ranges.
- **Quiz true/false** responses are always `true` or `false`; on non-English sites they were the
  translated label, and the correct answer was recorded wrongly.
- **SCORM 2004** passed and failed statuses are recorded as such (they were all "completed"),
  SCORM 2004 score bounds are read, and decimal scores are kept. A score outside its own bounds
  no longer gets the LRS to reject the statement.
- **Totara support removed.** The `\totara_program\event\program_assigned` transformer and the
  Totara platform name in statements are gone. Totara reports a Moodle 3.4-era version, and this
  plugin has required Moodle 3.5 or later since 4.6.0 (March 2020), so 4.5.0 was the last release
  that could be installed on Totara.
- **Message viewed** statements failed for every message read on a real site: the transformer
  read the read-receipt record instead of the message. They now describe the message, and their
  object id is the message's id. Message sent statements and Certificate revoked statements
  outside a course also failed, looking up a course with id 0.
- **Quiz attempt names** use the attempt number ("Quiz Attempt 2") instead of the database id.
  The activity id is unchanged.
- **Failure notification emails** are sent once per address. A user in two selected cohorts, or
  in a cohort and also listed as an additional address, used to get one email for each. Suspended
  and deleted users are no longer emailed, and the task's output goes to the task log.

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
