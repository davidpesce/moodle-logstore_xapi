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
 * Settings file.
 *
 * @package   logstore_xapi
 * @copyright Jerret Fowler <jerrett.fowler@gmail.com>
 *            Ryan Smith <https://www.linkedin.com/in/ryan-smith-uk/>
 *            David Pesce <david.pesce@exputo.com>
 * @license   https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/src/autoload.php');
require_once($CFG->dirroot . '/admin/tool/log/store/xapi/lib.php');

if ($hassiteconfig) {
    // Create a subcategory under Logging to group all xAPI pages together.
    $ADMIN->add('logging', new admin_category('logstorexapi', get_string('pluginname', 'logstore_xapi')));

    // Rename the settings page so it shows as "Settings" rather than repeating "Logstore xAPI".
    // Also force it visible — the core loader hides it when the plugin is disabled, but inside
    // our own subcategory it should always be accessible.
    $settings->visiblename = get_string('settings', 'logstore_xapi');
    $settings->hidden = false;

    // Show a warning if the logstore is not enabled.
    $enabledstores = get_config('tool_log', 'enabled_stores');
    if (empty($enabledstores) || !in_array('logstore_xapi', explode(',', $enabledstores))) {
        $manageurl = new moodle_url('/admin/settings.php', ['section' => 'managelogging']);
        $settings->add(new admin_setting_heading(
            'logstore_xapi_notenabled',
            '',
            html_writer::div(
                get_string('logstore_not_enabled', 'logstore_xapi', $manageurl->out()),
                'alert alert-warning'
            )
        ));
    }

    // LRS Connection.
    $settings->add(new admin_setting_heading(
        'lrsconnection',
        get_string('heading_lrsconnection', 'logstore_xapi'),
        get_string('heading_lrsconnection_desc', 'logstore_xapi')
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/endpoint',
        get_string('endpoint', 'logstore_xapi'),
        '',
        'https://example.com/endpoint/location/',
        PARAM_URL,
        60
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/username',
        get_string('username', 'logstore_xapi'),
        '',
        '',
        PARAM_TEXT,
        40
    ));

    $passwordsetting = new admin_setting_configpasswordunmask(
        'logstore_xapi/password',
        get_string('password', 'logstore_xapi'),
        '',
        ''
    );
    $passwordsetting->size = 40;
    $settings->add($passwordsetting);

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/oauth2_enabled',
        get_string('oauth2_enabled', 'logstore_xapi'),
        get_string('oauth2_enabled_desc', 'logstore_xapi'), 1)
    );
    $settings->add(new admin_setting_configtext(
        'logstore_xapi/oauth2_token_endpoint',
        get_string('oauth2_token_endpoint', 'logstore_xapi'), '',
        'https://auth.example.com/oauth/token', PARAM_URL)
    );

    // Processing and batches.
    $settings->add(new admin_setting_heading(
        'processingbatches',
        get_string('heading_processingbatches', 'logstore_xapi'),
        get_string('heading_processingbatches_desc', 'logstore_xapi')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/backgroundmode',
        get_string('backgroundmode', 'logstore_xapi'),
        get_string('backgroundmode_desc', 'logstore_xapi'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/maxbatchsize',
        get_string('maxbatchsize', 'logstore_xapi'),
        get_string('maxbatchsize_desc', 'logstore_xapi'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/maxbatchsizeforfailed',
        get_string('maxbatchsizeforfailed', 'logstore_xapi'),
        get_string('maxbatchsizeforfailed_desc', 'logstore_xapi'),
        15,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/maxbatchsizeforhistorical',
        get_string('maxbatchsizeforhistorical', 'logstore_xapi'),
        get_string('maxbatchsizeforhistorical_desc', 'logstore_xapi'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/resendfailedbatches',
        get_string('resendfailedbatches', 'logstore_xapi'),
        get_string('resendfailedbatches_desc', 'logstore_xapi'),
        0
    ));

    // Actor identification.
    $settings->add(new admin_setting_heading(
        'actoridentification',
        get_string('heading_actoridentification', 'logstore_xapi'),
        get_string('heading_actoridentification_desc', 'logstore_xapi')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/mbox',
        get_string('mbox', 'logstore_xapi'),
        get_string('mbox_desc', 'logstore_xapi'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/send_name',
        get_string('send_name', 'logstore_xapi'),
        get_string('send_name_desc', 'logstore_xapi'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/send_username',
        get_string('send_username', 'logstore_xapi'),
        get_string('send_username_desc', 'logstore_xapi'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/account_homepage',
        get_string('account_homepage', 'logstore_xapi'),
        get_string('account_homepage_desc', 'logstore_xapi'),
        $CFG->wwwroot,
        PARAM_TEXT
    ));

    // Statement content.
    $settings->add(new admin_setting_heading(
        'statementcontent',
        get_string('heading_statementcontent', 'logstore_xapi'),
        get_string('heading_statementcontent_desc', 'logstore_xapi')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/shortcourseid',
        get_string('shortcourseid', 'logstore_xapi'),
        get_string('shortcourseid_desc', 'logstore_xapi'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/sendidnumber',
        get_string('sendidnumber', 'logstore_xapi'),
        get_string('sendidnumber_desc', 'logstore_xapi'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/context_platform',
        get_string('context_platform', 'logstore_xapi'),
        get_string('context_platform_desc', 'logstore_xapi'),
        'Moodle',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/sendresponsechoices',
        get_string('send_response_choices', 'logstore_xapi'),
        get_string('send_response_choices_desc', 'logstore_xapi'),
        0
    ));

    // Notifications.
    $settings->add(new admin_setting_heading(
        'notifications',
        get_string('heading_notifications', 'logstore_xapi'),
        get_string('heading_notifications_desc', 'logstore_xapi')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/enablesendingnotifications',
        get_string('enablesendingnotifications', 'logstore_xapi'),
        get_string('enablesendingnotifications_desc', 'logstore_xapi'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/errornotificationtrigger',
        get_string('errornotificationtrigger', 'logstore_xapi'),
        get_string('errornotificationtrigger_desc', 'logstore_xapi'),
        10,
        PARAM_INT
    ));

    $settings->add(new \logstore_xapi\admin\setting_cohort_selector(
        'logstore_xapi/cohorts',
        get_string('includecohorts', 'logstore_xapi'),
        '',
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'logstore_xapi/send_additional_email_addresses',
        get_string('send_additional_email_addresses', 'logstore_xapi'),
        get_string('send_additional_email_addresses_desc', 'logstore_xapi'),
        '',
        PARAM_TEXT
    ));

    // Filters.
    $settings->add(new admin_setting_heading(
        'filters',
        get_string('filters', 'logstore_xapi'),
        get_string('filters_help', 'logstore_xapi')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'logstore_xapi/logguests',
        get_string('logguests', 'logstore_xapi'),
        '',
        '0'
    ));

    $menuroutes = [];
    $eventfunctionmap = \src\transformer\get_event_function_map();
    foreach (array_keys($eventfunctionmap) as $eventname) {
        $menuroutes[$eventname] = $eventname;
    }

    $settings->add(new \logstore_xapi\admin_setting_configroutes(
        'logstore_xapi/routes',
        get_string('routes', 'logstore_xapi'),
        '',
        $menuroutes,
        $menuroutes
    ));

    // Add settings page and report pages to the subcategory.
    $ADMIN->add('logstorexapi', $settings);

    $ADMIN->add('logstorexapi', new admin_externalpage(
        'logstorexapierrorlog',
        get_string('logstorexapierrorlog', 'logstore_xapi'),
        new moodle_url('/admin/tool/log/store/xapi/report.php', ['id' => XAPI_REPORT_ID_ERROR]),
        ['logstore/xapi:viewerrorlog']
    ));

    $ADMIN->add('logstorexapi', new admin_externalpage(
        'logstorexapihistoriclog',
        get_string('logstorexapihistoriclog', 'logstore_xapi'),
        new moodle_url('/admin/tool/log/store/xapi/report.php', ['id' => XAPI_REPORT_ID_HISTORIC]),
        ['logstore/xapi:managehistoric']
    ));

    // Prevent the core plugin loader from adding the settings page directly to 'logging' again.
    $settings = null;
}
