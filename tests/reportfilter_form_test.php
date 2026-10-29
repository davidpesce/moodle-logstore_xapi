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

namespace logstore_xapi;

use logstore_xapi\form\reportfilter_form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

/**
 * Tests for the filter form of the error and historic reports.
 *
 * Each test submits the form the way report.php builds it, then checks the submitted filters
 * come back from get_data() and survive the onpage round trip the report uses for paging.
 *
 * @package   logstore_xapi
 * @copyright 2026 David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \logstore_xapi\form\reportfilter_form
 */
final class reportfilter_form_test extends \advanced_testcase {
    /** @var string The LRS response stored with the failed event. */
    const RESPONSE = '{"errorMessage":"Bad statement"}';

    /**
     * The form defaults report.php starts from.
     *
     * @return array
     */
    private function get_defaults(): array {
        return [
            'datefrom' => XAPI_REPORT_DATEFROM_DEFAULT,
            'dateto' => XAPI_REPORT_DATETO_DEFAULT,
            'courseid' => XAPI_REPORT_COURSEID_DEFAULT,
            'activity' => XAPI_REPORT_ACTIVITY_DEFAULT,
            'eventnames' => XAPI_REPORT_EVENTNAMES_DEFAULT,
            'errortype' => XAPI_REPORT_ERROTYPE_DEFAULT,
            'resend' => XAPI_REPORT_RESEND_FALSE,
            'response' => XAPI_REPORT_RESPONSE_DEFAULT,
            'userfullname' => XAPI_REPORT_USERNAME_DEFAULT,
        ];
    }

    /**
     * Build the error report's form, with options read from the failed log.
     *
     * @return reportfilter_form
     */
    private function get_error_form(): reportfilter_form {
        return new reportfilter_form(null, [
            'defaults' => $this->get_defaults(),
            'reportid' => XAPI_REPORT_ID_ERROR,
            'eventnames' => logstore_xapi_get_event_names_array(),
            'errortypes' => logstore_xapi_get_distinct_options_from_failed_table('errortype'),
            'responses' => logstore_xapi_get_response_options(),
        ]);
    }

    /**
     * Build the historic report's form, with the activities of the chosen course.
     *
     * @param int $courseid The chosen course, or 0.
     * @return reportfilter_form
     */
    private function get_historic_form(int $courseid = 0): reportfilter_form {
        return new reportfilter_form(null, [
            'defaults' => $this->get_defaults(),
            'reportid' => XAPI_REPORT_ID_HISTORIC,
            'eventnames' => logstore_xapi_get_event_names_array(),
            'activities' => logstore_xapi_get_course_activity_options($courseid),
        ]);
    }

    /**
     * Store an event in the given log table.
     *
     * @param string $table logstore_xapi_failed_log or logstore_standard_log.
     * @param \context $context The event context.
     * @param array $extra Extra columns.
     * @return void
     */
    private function add_event(string $table, \context $context, array $extra = []): void {
        global $DB;
        $DB->insert_record($table, (object) array_merge([
            'eventname' => '\\core\\event\\course_viewed',
            'component' => 'core',
            'action' => 'viewed',
            'target' => 'course',
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => $context->id,
            'contextlevel' => $context->contextlevel,
            'contextinstanceid' => $context->instanceid,
            'userid' => 2,
            'anonymous' => 0,
            'timecreated' => 1433946701,
        ], $extra));
    }

    /**
     * Submitted date selector values.
     *
     * @param int $year The year.
     * @param int $month The month.
     * @param int $day The day.
     * @return array
     */
    private function date(int $year, int $month, int $day): array {
        return ['day' => $day, 'month' => $month, 'year' => $year, 'enabled' => 1];
    }

    /**
     * The error report accepts an error type and response from the failed log, event names
     * and dates, and the report can read them back from onpage.
     *
     * @return void
     */
    public function test_error_report_filters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $this->add_event('logstore_xapi_failed_log', \context_course::instance($course->id), [
            'courseid' => $course->id,
            'type' => XAPI_IMPORT_TYPE_LIVE,
            'errortype' => XAPI_REPORT_ERRORTYPE_RECIPE,
            'response' => self::RESPONSE,
            'responsehash' => sha1(self::RESPONSE),
        ]);

        reportfilter_form::mock_submit([
            'run' => 1,
            'resend' => XAPI_REPORT_RESEND_FALSE,
            'errortype' => XAPI_REPORT_ERRORTYPE_RECIPE,
            'eventnames' => ['\\core\\event\\course_viewed'],
            'response' => sha1(self::RESPONSE),
            'datefrom' => $this->date(2026, 10, 1),
            'dateto' => $this->date(2026, 10, 20),
            'submitbutton' => 'Search',
        ]);
        $form = $this->get_error_form();

        $this->assertTrue($form->is_submitted());
        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals(XAPI_REPORT_ERRORTYPE_RECIPE, $data->errortype);
        $this->assertSame(sha1(self::RESPONSE), $data->response);
        $this->assertSame(['\\core\\event\\course_viewed'], $data->eventnames);
        $this->assertEquals(make_timestamp(2026, 10, 1), $data->datefrom);
        $this->assertEquals(make_timestamp(2026, 10, 20), $data->dateto);

        // The report passes the filters to the next page as JSON in onpage.
        $filters = logstore_xapi_clean_report_filters(json_encode($data), logstore_xapi_get_event_names_array());
        $this->assertEquals(XAPI_REPORT_ERRORTYPE_RECIPE, $filters->errortype);
        $this->assertSame(sha1(self::RESPONSE), $filters->response);
        $this->assertSame(['\\core\\event\\course_viewed'], $filters->eventnames);
        $this->assertEquals($data->datefrom, $filters->datefrom);
        $this->assertEquals($data->dateto, $filters->dateto);
    }

    /**
     * The historic report accepts a context from the standard log and a user name.
     *
     * @return void
     */
    public function test_historic_report_filters(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => 'Final <b>quiz</b>']);
        $quizcontext = \context_module::instance($quiz->cmid);

        reportfilter_form::mock_submit([
            'run' => 1,
            'resend' => XAPI_REPORT_RESEND_FALSE,
            'eventnames' => ['\\core\\event\\course_viewed'],
            'userfullname' => 'Admin User',
            'courseid' => $course->id,
            'activity' => $quizcontext->id,
            'datefrom' => $this->date(2026, 10, 1),
            'submitbutton' => 'Search',
        ]);
        $form = $this->get_historic_form($course->id);

        $this->assertTrue($form->is_validated());
        $data = $form->get_data();
        $this->assertEquals($course->id, $data->courseid);
        $this->assertEquals($quizcontext->id, $data->activity);
        $this->assertSame('Admin User', $data->userfullname);
        $this->assertSame(['\\core\\event\\course_viewed'], $data->eventnames);
        $this->assertEquals(make_timestamp(2026, 10, 1), $data->datefrom);

        $filters = logstore_xapi_clean_report_filters(json_encode($data), logstore_xapi_get_event_names_array());
        $this->assertEquals($course->id, $filters->courseid);
        $this->assertEquals($quizcontext->id, $filters->activity);
        $this->assertSame('Admin User', $filters->userfullname);
    }

    /**
     * The activity filter lists the chosen course's activities, keyed by context and escaped.
     *
     * @return void
     */
    public function test_activity_options(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'name' => 'Final <b>quiz</b>']);
        $other = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('forum', ['course' => $other->id]);

        $options = logstore_xapi_get_course_activity_options($course->id);

        $quizcontext = \context_module::instance($quiz->cmid);
        $this->assertSame([$quizcontext->id], array_keys($options));
        $this->assertStringNotContainsString('<b>', $options[$quizcontext->id]);
        $this->assertSame([], logstore_xapi_get_course_activity_options(0));

        // Escaped once: "Q&A" shows as "Q&A", not "Q&amp;A".
        $this->getDataGenerator()->create_module('quiz', ['course' => $other->id, 'name' => 'Q&A']);
        $labels = array_values(logstore_xapi_get_course_activity_options($other->id));
        $this->assertContains('Quiz: Q&amp;A', $labels);
        $this->assertSame([], logstore_xapi_get_course_activity_options(-5));
    }

    /**
     * The historic report needs a start date; it starts with the last 30 days.
     *
     * @return void
     */
    public function test_historic_report_needs_start_date(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        reportfilter_form::mock_submit([
            'run' => 1,
            'resend' => XAPI_REPORT_RESEND_FALSE,
            'eventnames' => ['\\core\\event\\course_viewed'],
            'submitbutton' => 'Search',
        ]);
        $form = $this->get_historic_form();

        $this->assertTrue($form->is_submitted());
        $this->assertFalse($form->is_validated());

        // The error report has no such requirement.
        reportfilter_form::mock_submit(['run' => 1, 'resend' => XAPI_REPORT_RESEND_FALSE, 'submitbutton' => 'Search']);
        $this->assertTrue($this->get_error_form()->is_validated());
    }

    /**
     * A start date after the end date is rejected.
     *
     * @return void
     */
    public function test_date_range_must_be_in_order(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        reportfilter_form::mock_submit([
            'run' => 1,
            'resend' => XAPI_REPORT_RESEND_FALSE,
            'datefrom' => $this->date(2026, 10, 20),
            'dateto' => $this->date(2026, 10, 1),
            'submitbutton' => 'Search',
        ]);
        $form = $this->get_historic_form();

        $this->assertTrue($form->is_submitted());
        $this->assertFalse($form->is_validated());
        $this->assertNull($form->get_data());
    }
}
