<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Map the Moodle events to transformers.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace src\transformer;

/**
 * Return a map of the Moodle events to their transformers.
 *
 * @return array
 */
function get_event_function_map() {
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $availableevents = [
        '\core\event\course_category_created' => 'core\course_category_created',
        '\core\event\badge_awarded' => 'core\badge_awarded',
        '\core\event\badge_updated' => 'core\badge_updated',
        '\core\event\badge_revoked' => 'core\badge_revoked',
        '\core\event\badge_viewed' => 'core\badge_viewed',
        '\core\event\message_sent' => 'core\message_sent',
        '\core\event\message_viewed' => 'core\message_viewed',
        '\core\event\course_created' => 'core\course_created',
        '\core\event\course_updated' => 'core\course_updated',
        '\core\event\calendar_event_created' => 'core\calendar_event_created',
        '\core\event\calendar_event_updated' => 'core\calendar_event_updated',
        '\core\event\calendar_event_deleted' => 'core\calendar_event_deleted',
        '\core\event\calendar_subscription_created' => 'core\calendar_subscription_created',
        '\core\event\calendar_subscription_updated' => 'core\calendar_subscription_updated',
        '\core\event\calendar_subscription_deleted' => 'core\calendar_subscription_deleted',
        '\core\event\course_completed' => 'core\course_completed',
        '\core\event\course_completion_updated' => 'core\course_completion_updated',
        '\core\event\course_viewed' => 'core\course_viewed',
        '\core\event\course_section_created' => 'core\course_section_created',
        '\core\event\course_resources_list_viewed' => 'core\course_resources_list_viewed',
        '\core\event\group_created' => 'core\group_created',
        '\core\event\group_deleted' => 'core\group_deleted',
        '\core\event\group_message_sent' => 'core\group_message_sent',
        '\core\event\group_member_added' => 'core\group_member_added',
        '\core\event\group_member_removed' => 'core\group_member_removed',
        '\core\event\note_created' => 'core\note_created',
        '\core\event\note_updated' => 'core\note_updated',
        '\core\event\notes_viewed' => 'core\notes_viewed',
        '\core\event\user_created' => 'core\user_created',
        '\core\event\user_enrolment_created' => 'core\user_enrolment_created',
        '\core\event\user_enrolment_deleted' => 'core\user_enrolment_deleted',
        '\core\event\user_enrolment_updated' => 'core\user_enrolment_updated',
        '\core\event\user_loggedin' => 'core\user_loggedin',
        '\core\event\user_loggedinas' => 'core\user_loggedin',
        '\core\event\user_loggedout' => 'core\user_loggedout',
        '\core\event\course_module_created' => 'core\course_module_created',
        '\core\event\course_module_completion_updated' => 'core\course_module_completion_updated',
        '\core_h5p\event\h5p_viewed' => 'all\course_module_viewed',
        '\core\event\search_results_viewed' => 'core\search_results_viewed',
        '\core\event\questions_imported' => 'core\questions_imported',
        '\core\event\question_created' => 'core\question_created',
        '\mod_assign\event\assessable_submitted' => 'mod_assign\assignment_submitted',
        '\mod_assign\event\submission_graded' => 'mod_assign\assignment_graded',
        '\mod_assign\event\feedback_viewed' => 'mod_assign\feedback_viewed',
        '\mod_assign\event\submission_locked' => 'mod_assign\submission_locked',
        '\mod_assign\event\submission_unlocked' => 'mod_assign\submission_locked',
        '\mod_assign\event\submission_viewed' => 'mod_assign\submission_viewed',
        '\mod_assign\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_bigbluebuttonbn\event\activity_viewed' => 'mod_bigbluebuttonbn\activity_viewed',
        '\mod_bigbluebuttonbn\event\activity_management_viewed' => 'mod_bigbluebuttonbn\activity_management_viewed',
        '\mod_bigbluebuttonbn\event\live_session_event' => 'mod_bigbluebuttonbn\live_session',
        '\mod_bigbluebuttonbn\event\meeting_created' => 'mod_bigbluebuttonbn\meeting_created',
        '\mod_bigbluebuttonbn\event\meeting_ended' => 'mod_bigbluebuttonbn\meeting_ended',
        '\mod_bigbluebuttonbn\event\meeting_joined' => 'mod_bigbluebuttonbn\meeting_joined',
        '\mod_bigbluebuttonbn\event\meeting_left' => 'mod_bigbluebuttonbn\meeting_left',
        '\mod_bigbluebuttonbn\event\recording_deleted' => 'mod_bigbluebuttonbn\recording_deleted',
        '\mod_bigbluebuttonbn\event\recording_edited' => 'mod_bigbluebuttonbn\recording_edited',
        '\mod_bigbluebuttonbn\event\recording_imported' => 'mod_bigbluebuttonbn\recording_imported',
        '\mod_bigbluebuttonbn\event\recording_protected' => 'mod_bigbluebuttonbn\recording_protected',
        '\mod_bigbluebuttonbn\event\recording_published' => 'mod_bigbluebuttonbn\recording_published',
        '\mod_bigbluebuttonbn\event\recording_unprotected' => 'mod_bigbluebuttonbn\recording_unprotected',
        '\mod_bigbluebuttonbn\event\recording_unpublished' => 'mod_bigbluebuttonbn\recording_unpublished',
        '\mod_bigbluebuttonbn\event\recording_viewed' => 'mod_bigbluebuttonbn\recording_viewed',
        '\mod_book\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_book\event\chapter_viewed' => 'mod_book\chapter_viewed',
        '\mod_book\event\chapter_created' => 'mod_book\chapter_created',
        '\mod_chat\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_choice\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_choice\event\answer_created' => 'mod_choice\answer_created',
        '\mod_data\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_facetoface\event\cancel_booking' => 'mod_facetoface\cancel_booking',
        '\mod_facetoface\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_facetoface\event\signup_success' => 'mod_facetoface\signup_success',
        '\mod_facetoface\event\take_attendance' => 'mod_facetoface\take_attendance',
        '\mod_feedback\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_feedback\event\response_submitted' => 'mod_feedback\response_submitted\handler',
        '\mod_folder\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_forum\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_forum\event\discussion_created' => 'mod_forum\discussion_created',
        '\mod_forum\event\discussion_viewed' => 'mod_forum\discussion_viewed',
        '\mod_forum\event\post_created' => 'mod_forum\post_created',
        '\mod_forum\event\post_deleted' => 'mod_forum\post_deleted',
        '\mod_forum\event\post_updated' => 'mod_forum\post_updated',
        '\mod_forum\event\subscription_created' => 'mod_forum\subscription_created',
        '\mod_forum\event\subscription_deleted' => 'mod_forum\subscription_deleted',
        '\mod_forum\event\discussion_subscription_created' => 'mod_forum\discussion_subscription_created',
        '\mod_forum\event\discussion_subscription_deleted' => 'mod_forum\discussion_subscription_deleted',
        '\mod_forum\event\user_report_viewed' => 'mod_forum\user_report_viewed',
        '\mod_game\event\course_module_instance_list_viewed' => 'mod_game\course_module_instance_list_viewed',
        '\mod_game\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_game\event\game_played' => 'mod_game\game_played',
        '\mod_glossary\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_glossary\event\entry_viewed' => 'mod_glossary\entry_viewed',
        '\mod_glossary\event\comment_created' => 'mod_glossary\comment_created',
        '\mod_glossary\event\comment_deleted' => 'mod_glossary\comment_deleted',
        '\mod_lesson\event\lesson_started' => 'mod_lesson\lesson_started',
        '\mod_lesson\event\lesson_resumed' => 'mod_lesson\lesson_resumed',
        '\mod_lesson\event\lesson_restarted' => 'mod_lesson\lesson_restarted',
        '\mod_lesson\event\lesson_ended' => 'mod_lesson\lesson_ended',
        '\mod_lesson\event\question_viewed' => 'mod_lesson\question_viewed',
        '\mod_lesson\event\content_page_viewed' => 'mod_lesson\content_page_viewed',
        '\mod_lesson\event\question_answered' => 'mod_lesson\question_answered',
        '\mod_lesson\event\essay_assessed' => 'mod_lesson\essay_assessed',
        '\mod_imscp\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_lesson\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_lti\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_page\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_quiz\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_quiz\event\attempt_abandoned' => 'mod_quiz\attempt_submitted\handler',
        '\mod_quiz\event\attempt_becameoverdue' => 'mod_quiz\attempt_becameoverdue',
        '\mod_quiz\event\attempt_started' => 'mod_quiz\attempt_started',
        '\mod_quiz\event\question_manually_graded' => 'mod_quiz\question_manually_graded',
        '\mod_quiz\event\attempt_reviewed' => 'mod_quiz\attempt_reviewed',
        '\mod_quiz\event\attempt_submitted' => 'mod_quiz\attempt_submitted\handler',
        '\mod_quiz\event\attempt_viewed' => 'mod_quiz\attempt_viewed',
        '\mod_questionnaire\event\attempt_resumed' => 'mod_questionnaire\attempt_resumed',
        '\mod_questionnaire\event\attempt_saved' => 'mod_questionnaire\attempt_saved',
        '\mod_questionnaire\event\all_responses_viewed' => 'mod_questionnaire\all_responses_viewed',
        '\mod_questionnaire\event\attempt_submitted' => 'mod_questionnaire\attempt_submitted',
        '\mod_questionnaire\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_resource\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_scorm\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_scorm\event\sco_launched' => 'mod_scorm\sco_launched',
        '\mod_scorm\event\scoreraw_submitted' => 'mod_scorm\scoreraw_submitted',
        '\mod_scorm\event\status_submitted' => 'mod_scorm\status_submitted',
        '\mod_survey\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_survey\event\report_viewed' => 'mod_survey\report_viewed',
        '\mod_survey\event\response_submitted' => 'mod_survey\response_submitted',
        '\mod_url\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_wiki\event\course_module_viewed' => 'all\course_module_viewed',
        '\mod_wiki\event\comment_created' => 'mod_wiki\comment_created',
        '\mod_wiki\event\comment_deleted' => 'mod_wiki\comment_deleted',
        '\mod_wiki\event\comments_viewed' => 'mod_wiki\comments_viewed',
        '\mod_wiki\event\page_viewed' => 'mod_wiki\page_viewed',
        '\mod_workshop\event\course_module_viewed' => 'all\course_module_viewed',
        '\totara_program\event\program_assigned' => 'totara_program\program_assigned',
        '\tool_certificate\event\certificate_issued' => 'tool_certificate\certificate_issued',
        '\tool_certificate\event\certificate_verified' => 'tool_certificate\certificate_verified',
        '\tool_certificate\event\certificate_revoked' => 'tool_certificate\certificate_revoked',
        '\tool_usertours\event\tour_ended' => 'tool_usertours\tour_ended',
    ];

    $environmentevents = class_exists("report_eventlist_list_generator") ?
        array_keys(\report_eventlist_list_generator::get_all_events_list(false)) : array_keys($availableevents);

    $map = array_filter($availableevents, function ($k) use ($environmentevents) {
        return in_array($k, $environmentevents);
    }, ARRAY_FILTER_USE_KEY);
    return $map;
}
