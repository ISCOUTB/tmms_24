<?php
require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_ALPHANUM);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('block/tmms_24:viewallresults', $context);
require_capability('moodle/course:manageactivities', $context);

if (!confirm_sesskey($sesskey)) {
    print_error('invalidsesskey');
}

$response = $DB->get_record('tmms_24', array('id' => $id), '*', MUST_EXIST);

if ($response->course == $courseid) {
    if ($DB->delete_records('tmms_24', array('id' => $id))) {
        // Get user info for notification
        $user = $DB->get_record('user', array('id' => $response->user));
        $message = get_string('response_deleted_success', 'block_tmms_24', fullname($user));
        redirect(
            new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
            $message,
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } else {
        redirect(
            new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
            get_string('error_deleting_response', 'block_tmms_24'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
} else {
    print_error('invalidaccess');
}