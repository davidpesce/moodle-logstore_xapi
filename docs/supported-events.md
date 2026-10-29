# Supported events

The plugin sends statements for these events, when "Include actions with these routes" includes them and the plugin the event comes from is installed. The list is kept in step with [`get_event_function_map()`](../src/transformer/get_event_function_map.php) by a unit test.

## Core (`core`)

- `\core\event\badge_awarded`
- `\core\event\badge_revoked`
- `\core\event\badge_updated`
- `\core\event\badge_viewed`
- `\core\event\calendar_event_created`
- `\core\event\calendar_event_deleted`
- `\core\event\calendar_event_updated`
- `\core\event\calendar_subscription_created`
- `\core\event\calendar_subscription_deleted`
- `\core\event\calendar_subscription_updated`
- `\core\event\course_category_created`
- `\core\event\course_completed`
- `\core\event\course_completion_updated`
- `\core\event\course_created`
- `\core\event\course_module_completion_updated`
- `\core\event\course_module_created`
- `\core\event\course_resources_list_viewed`
- `\core\event\course_section_created`
- `\core\event\course_updated`
- `\core\event\course_viewed`
- `\core\event\group_created`
- `\core\event\group_deleted`
- `\core\event\group_member_added`
- `\core\event\group_member_removed`
- `\core\event\group_message_sent`
- `\core\event\message_sent`
- `\core\event\message_viewed`
- `\core\event\note_created`
- `\core\event\note_updated`
- `\core\event\notes_viewed`
- `\core\event\question_created`
- `\core\event\questions_imported`
- `\core\event\search_results_viewed`
- `\core\event\user_created`
- `\core\event\user_enrolment_created`
- `\core\event\user_enrolment_deleted`
- `\core\event\user_enrolment_updated`
- `\core\event\user_loggedin`
- `\core\event\user_loggedinas`
- `\core\event\user_loggedout`

## Assignment (`mod_assign`)

- `\mod_assign\event\assessable_submitted`
- `\mod_assign\event\course_module_viewed`
- `\mod_assign\event\feedback_viewed`
- `\mod_assign\event\submission_graded`
- `\mod_assign\event\submission_locked`
- `\mod_assign\event\submission_unlocked`
- `\mod_assign\event\submission_viewed`

## BigBlueButton (`mod_bigbluebuttonbn`)

- `\mod_bigbluebuttonbn\event\activity_management_viewed`
- `\mod_bigbluebuttonbn\event\course_module_viewed`
- `\mod_bigbluebuttonbn\event\meeting_created`
- `\mod_bigbluebuttonbn\event\meeting_ended`
- `\mod_bigbluebuttonbn\event\meeting_joined`
- `\mod_bigbluebuttonbn\event\meeting_left`
- `\mod_bigbluebuttonbn\event\recording_deleted`
- `\mod_bigbluebuttonbn\event\recording_edited`
- `\mod_bigbluebuttonbn\event\recording_imported`
- `\mod_bigbluebuttonbn\event\recording_protected`
- `\mod_bigbluebuttonbn\event\recording_published`
- `\mod_bigbluebuttonbn\event\recording_unprotected`
- `\mod_bigbluebuttonbn\event\recording_unpublished`
- `\mod_bigbluebuttonbn\event\recording_viewed`

## Book (`mod_book`)

- `\mod_book\event\chapter_created`
- `\mod_book\event\chapter_viewed`
- `\mod_book\event\course_module_viewed`

## Certificates (`tool_certificate`)

Moodle Workplace, or the [tool_certificate](https://moodle.org/plugins/tool_certificate) plugin.

- `\tool_certificate\event\certificate_issued`
- `\tool_certificate\event\certificate_revoked`
- `\tool_certificate\event\certificate_verified`

## Chat (`mod_chat`)

In Moodle 4.5; a separate plugin from Moodle 5.0.

- `\mod_chat\event\course_module_viewed`

## Choice (`mod_choice`)

- `\mod_choice\event\answer_created`
- `\mod_choice\event\course_module_viewed`

## Database (`mod_data`)

- `\mod_data\event\course_module_viewed`

## External tool (`mod_lti`)

- `\mod_lti\event\course_module_viewed`

## Face-to-face (`mod_facetoface`)

A third-party plugin, [mod_facetoface](https://github.com/catalyst/moodle-mod_facetoface).

- `\mod_facetoface\event\cancel_booking`
- `\mod_facetoface\event\course_module_viewed`
- `\mod_facetoface\event\signup_success`
- `\mod_facetoface\event\take_attendance`

## Feedback (`mod_feedback`)

- `\mod_feedback\event\course_module_viewed`
- `\mod_feedback\event\response_submitted`

## File (`mod_resource`)

- `\mod_resource\event\course_module_viewed`

## Folder (`mod_folder`)

- `\mod_folder\event\course_module_viewed`

## Forum (`mod_forum`)

- `\mod_forum\event\course_module_viewed`
- `\mod_forum\event\discussion_created`
- `\mod_forum\event\discussion_subscription_created`
- `\mod_forum\event\discussion_subscription_deleted`
- `\mod_forum\event\discussion_viewed`
- `\mod_forum\event\post_created`
- `\mod_forum\event\post_deleted`
- `\mod_forum\event\post_updated`
- `\mod_forum\event\subscription_created`
- `\mod_forum\event\subscription_deleted`
- `\mod_forum\event\user_report_viewed`

## Game (`mod_game`)

A third-party plugin, [mod_game](https://moodle.org/plugins/mod_game).

- `\mod_game\event\course_module_viewed`
- `\mod_game\event\game_played`

## Glossary (`mod_glossary`)

- `\mod_glossary\event\comment_created`
- `\mod_glossary\event\comment_deleted`
- `\mod_glossary\event\course_module_viewed`
- `\mod_glossary\event\entry_viewed`

## H5P (`core_h5p`)

- `\core_h5p\event\h5p_viewed`

## IMS content package (`mod_imscp`)

- `\mod_imscp\event\course_module_viewed`

## Lesson (`mod_lesson`)

- `\mod_lesson\event\content_page_viewed`
- `\mod_lesson\event\course_module_viewed`
- `\mod_lesson\event\essay_assessed`
- `\mod_lesson\event\lesson_ended`
- `\mod_lesson\event\lesson_restarted`
- `\mod_lesson\event\lesson_resumed`
- `\mod_lesson\event\lesson_started`
- `\mod_lesson\event\question_answered`
- `\mod_lesson\event\question_viewed`

## Page (`mod_page`)

- `\mod_page\event\course_module_viewed`

## Questionnaire (`mod_questionnaire`)

A third-party plugin, [mod_questionnaire](https://moodle.org/plugins/mod_questionnaire).

- `\mod_questionnaire\event\all_responses_viewed`
- `\mod_questionnaire\event\attempt_resumed`
- `\mod_questionnaire\event\attempt_saved`
- `\mod_questionnaire\event\attempt_submitted`
- `\mod_questionnaire\event\course_module_viewed`

## Quiz (`mod_quiz`)

- `\mod_quiz\event\attempt_abandoned`
- `\mod_quiz\event\attempt_becameoverdue`
- `\mod_quiz\event\attempt_reviewed`
- `\mod_quiz\event\attempt_started`
- `\mod_quiz\event\attempt_submitted`
- `\mod_quiz\event\attempt_viewed`
- `\mod_quiz\event\course_module_viewed`
- `\mod_quiz\event\question_manually_graded`

## SCORM package (`mod_scorm`)

- `\mod_scorm\event\course_module_viewed`
- `\mod_scorm\event\sco_launched`
- `\mod_scorm\event\scoreraw_submitted`
- `\mod_scorm\event\status_submitted`

## Survey (`mod_survey`)

In Moodle 4.5; a separate plugin from Moodle 5.0.

- `\mod_survey\event\course_module_viewed`
- `\mod_survey\event\report_viewed`
- `\mod_survey\event\response_submitted`

## URL (`mod_url`)

- `\mod_url\event\course_module_viewed`

## User tours (`tool_usertours`)

- `\tool_usertours\event\tour_ended`

## Wiki (`mod_wiki`)

- `\mod_wiki\event\comment_created`
- `\mod_wiki\event\comment_deleted`
- `\mod_wiki\event\comments_viewed`
- `\mod_wiki\event\course_module_viewed`
- `\mod_wiki\event\page_viewed`

## Workshop (`mod_workshop`)

- `\mod_workshop\event\course_module_viewed`
