# Changelog

A summary of each release. The full notes, including upgrade instructions, are on the
[GitHub releases page](https://github.com/davidpesce/moodle-logstore_xapi/releases), which is
the authoritative record.

## 5.1.1 (2026-10-23)

Privacy and reliability release. Supports Moodle 4.5 to 5.3.
[Release notes](https://github.com/davidpesce/moodle-logstore_xapi/releases/tag/v5.1.1)

- **Privacy:** anonymous Feedback responses were sent to the LRS with the respondent's identity.
  They are now sent anonymously. Statements already in your LRS may identify respondents.
- Automatically awarded badges are now sent; manually awarded badges name the correct awarder.
- One failing event can no longer stall delivery for the whole site.
- In real-time mode, failed events are kept in the failed log so they can be replayed.
- LRS requests time out (10 s to connect, 60 s in total) instead of waiting indefinitely.
- Feedback multiple-choice answers other than the first option are no longer rejected by the LRS
  (#899).
- The privacy registry lists the LRS as an external location.
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
