<?php
require_once(dirname(__FILE__) . '/../../config.php');
require_once(dirname(__FILE__) . '/block_tmms_24.php');

require_login();

$courseid = required_param('cid', PARAM_INT);
$format = required_param('format', PARAM_ALPHA);

if ($courseid == SITEID || !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('block/tmms_24:viewallresults', $context);

$userid = optional_param('userid', 0, PARAM_INT);

// Fetch entries
$params = ['course' => $courseid];
if ($userid > 0) {
    $params['user'] = $userid;
}
$all_entries = $DB->get_records('tmms_24', $params, 'created_at DESC');

if (empty($all_entries)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]), get_string('no_results_yet', 'block_tmms_24'), null, 'error');
}

$facade = new TMMS24Facade();

// Build CSV/JSON rows
$export_rows = [];
foreach ($all_entries as $entry) {
    $user = $DB->get_record('user', ['id' => $entry->user]);
    if (!$user) {
        continue;
    }

    $responses = [];
    for ($i = 1; $i <= 24; $i++) {
        $field = 'item' . $i;
        $responses[] = isset($entry->$field) ? $entry->$field : '';
    }

    $scores = $facade->calculate_scores($responses);
    $interpretations = $facade->get_all_interpretations($scores, $entry->gender);

    $row = [
        'timestamp' => date('c', $entry->created_at),
        'user_id' => $entry->user,
        'course_id' => $entry->course,
        'name' => $user->firstname . ' ' . $user->lastname,
        'email' => $user->email,
        'age' => $entry->age,
        'gender' => $entry->gender
    ];

    // Responses
    for ($i = 1; $i <= 24; $i++) {
        $row['item_' . $i] = $responses[$i - 1];
    }

    // Scores (support both English and Spanish keys from facade)
    $row['perception_score'] = isset($scores['perception']) ? $scores['perception'] : (isset($scores['percepcion']) ? $scores['percepcion'] : '');
    $row['comprehension_score'] = isset($scores['comprehension']) ? $scores['comprehension'] : (isset($scores['comprension']) ? $scores['comprension'] : '');
    $row['regulation_score'] = isset($scores['regulation']) ? $scores['regulation'] : (isset($scores['regulacion']) ? $scores['regulacion'] : '');

    // Interpretations - support both key variants
    $row['perception_interpretation'] = isset($interpretations['perception']) ? $interpretations['perception'] : (isset($interpretations['percepcion']) ? $interpretations['percepcion'] : '');
    $row['comprehension_interpretation'] = isset($interpretations['comprehension']) ? $interpretations['comprehension'] : (isset($interpretations['comprension']) ? $interpretations['comprension'] : '');
    $row['regulation_interpretation'] = isset($interpretations['regulation']) ? $interpretations['regulation'] : (isset($interpretations['regulacion']) ? $interpretations['regulacion'] : '');

    $export_rows[] = $row;
}

// Filename
$filename = ($userid > 0)
    ? 'tmms24_results_user_' . $userid . '_' . time() . '.' . $format
    : 'tmms24_results_course_' . $courseid . '_' . time() . '.' . $format;

if ($format === 'csv') {
    // Send headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $output = fopen('php://output', 'w');
    // BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Build header row from keys of first row
    $headers = array_keys($export_rows[0]);
    fputcsv($output, $headers);

    // Write rows
    foreach ($export_rows as $r) {
        $line = [];
        foreach ($headers as $h) {
            $line[] = isset($r[$h]) ? $r[$h] : '';
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;

} elseif ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($export_rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;

} else {
    redirect(new moodle_url('/blocks/tmms_24/view.php', ['cid' => $courseid, 'view_results' => 1]), get_string('invalid_export_format', 'block_tmms_24'), null, 'error');
}

?>