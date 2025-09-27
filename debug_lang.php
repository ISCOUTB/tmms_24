<?php
// Temporary debug script for TMMS-24 language strings
require_once('../../config.php');

$courseid = optional_param('courseid', 1, PARAM_INT);

require_login();

$PAGE->set_url('/blocks/tmms_24/debug_lang.php', ['courseid' => $courseid]);
$PAGE->set_context(context_system::instance());
$PAGE->set_title('TMMS-24 Language Debug');
echo $OUTPUT->header();

echo '<h2>TMMS-24 Language String Debug</h2>';

$strings_to_test = [
    'submit_test',
    'back_to_course', 
    'items_completed',
    'validation_age_required',
    'validation_gender_required',
    'validation_missing_items',
    '24_questions',
    'duration_5_minutes',
    'test_description_short'
];

echo '<table class="table table-bordered">';
echo '<tr><th>String Key</th><th>Value</th><th>Status</th></tr>';

foreach ($strings_to_test as $string_key) {
    echo '<tr>';
    echo '<td>' . $string_key . '</td>';
    
    try {
        $value = get_string($string_key, 'block_tmms_24');
        echo '<td>' . htmlspecialchars($value) . '</td>';
        echo '<td style="color: green;">✓ OK</td>';
    } catch (Exception $e) {
        echo '<td style="color: red;">ERROR: ' . htmlspecialchars($e->getMessage()) . '</td>';
        echo '<td style="color: red;">✗ FAIL</td>';
    }
    
    echo '</tr>';
}

echo '</table>';

// Test if language files exist
echo '<h3>Language Files Check</h3>';
$lang_files = [
    'EN' => '/home/ubuntu/savio_infra/moodle/blocks/tmms_24/lang/en/block_tmms_24.php',
    'ES' => '/home/ubuntu/savio_infra/moodle/blocks/tmms_24/lang/es/block_tmms_24.php'
];

foreach ($lang_files as $lang => $file) {
    echo '<p><strong>' . $lang . ':</strong> ';
    if (file_exists($file)) {
        echo '<span style="color: green;">File exists (' . filesize($file) . ' bytes)</span>';
    } else {
        echo '<span style="color: red;">File missing</span>';
    }
    echo '</p>';
}

echo '<p><a href="?courseid=' . $courseid . '" class="btn btn-primary">Refresh</a></p>';
echo '<p><a href="/course/view.php?id=' . $courseid . '" class="btn btn-secondary">Back to Course</a></p>';

echo $OUTPUT->footer();