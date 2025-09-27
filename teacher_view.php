<?php
require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('block/tmms_24:viewallresults', $context);

$PAGE->set_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('all_results_title', 'block_tmms_24'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'block_tmms_24'));
$PAGE->navbar->add(get_string('all_results_title', 'block_tmms_24'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tmms_24_dashboard', 'block_tmms_24'));

// Add description
echo $OUTPUT->box(
    get_string('admin_dashboard_description', 'block_tmms_24'),
    'generalbox'
);

$results = $DB->get_records('tmms_24', ['course' => $courseid]);

// Calculate statistics
$enrolled_students = get_enrolled_users($context, 'block/tmms_24:taketest');
$total_enrolled = count($enrolled_students);
$total_completed = count($results);
$completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;

// Statistics cards
echo '<div class="row mb-4">';
echo '<div class="col-12">';
echo '<h4>' . get_string('statistics', 'block_tmms_24') . '</h4>';
echo '</div>';
echo '</div>';

echo '<div class="row mb-4">';

// Total enrolled
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-primary">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-users text-primary" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . $total_enrolled . '</h3>';
echo '<p class="text-muted mb-0">' . get_string('enrolled_students', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Completed tests
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-success">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-check-circle text-success" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . $total_completed . '</h3>';
echo '<p class="text-muted mb-0">' . get_string('total_completed', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Pending tests
$total_pending = $total_enrolled - $total_completed;
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-warning">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-clock text-warning" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . $total_pending . '</h3>';
echo '<p class="text-muted mb-0">' . get_string('pending', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

// Completion rate
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-info">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-chart-pie text-info" style="font-size: 2em;"></i>';
echo '<h3 class="mt-2 mb-1">' . number_format($completion_rate, 1) . '%</h3>';
echo '<p class="text-muted mb-0">' . get_string('completion_rate', 'block_tmms_24') . '</p>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

if (!empty($results)) {
    // Average scores statistics
    $avg_scores = ['percepcion' => 0, 'comprension' => 0, 'regulacion' => 0];
    $score_distributions = [
        'percepcion' => ['must_improve' => 0, 'adequate' => 0, 'excellent' => 0],
        'comprension' => ['must_improve' => 0, 'adequate' => 0, 'excellent' => 0],
        'regulacion' => ['must_improve' => 0, 'adequate' => 0, 'excellent' => 0]
    ];
    
    foreach ($results as $result) {
        // Calculate scores from individual item responses
        $responses = [];
        for ($i = 1; $i <= 24; $i++) {
            $item = 'item' . $i;
            $responses[] = $result->$item;
        }
        $scores = TMMS24Facade::calculate_scores($responses);
        $interpretations = TMMS24Facade::get_all_interpretations($scores, $result->gender);
        
        $avg_scores['percepcion'] += $scores['percepcion'];
        $avg_scores['comprension'] += $scores['comprension'];
        $avg_scores['regulacion'] += $scores['regulacion'];
        
        // Count interpretations for distribution
        foreach (['percepcion', 'comprension', 'regulacion'] as $dimension) {
            // Handle different gender interpretation structures
            $interp = '';
            if (isset($interpretations['result'])) {
                $interp = $interpretations['result'][$dimension];
            } else {
                // For 'prefiero_no_decir', use the first available interpretation
                $first_key = array_keys($interpretations)[0];
                $interp = $interpretations[$first_key][$dimension];
            }
            
            if (strpos($interp, get_string('needs_improvement', 'block_tmms_24')) !== false || 
                strpos($interp, 'debe mejorar') !== false) {
                $score_distributions[$dimension]['must_improve']++;
            } elseif (strpos($interp, get_string('excellent', 'block_tmms_24')) !== false ||
                     strpos($interp, 'excelente') !== false) {
                $score_distributions[$dimension]['excellent']++;
            } else {
                $score_distributions[$dimension]['adequate']++;
            }
        }
    }
    
    $avg_scores['percepcion'] /= $total_completed;
    $avg_scores['comprension'] /= $total_completed;
    $avg_scores['regulacion'] /= $total_completed;

    // Dimension statistics
    echo '<div class="row mb-4">';
    echo '<div class="col-12">';
    echo '<h5>' . get_string('dimension_statistics', 'block_tmms_24') . '</h5>';
    echo '<div class="card">';
    echo '<div class="card-body">';
    
    echo '<div class="row">';
    $dimensions = [
        'percepcion' => get_string('perception', 'block_tmms_24'),
        'comprension' => get_string('comprehension', 'block_tmms_24'),
        'regulacion' => get_string('regulation', 'block_tmms_24')
    ];
    
    foreach ($dimensions as $dim_key => $dim_name) {
        $avg_score = $avg_scores[$dim_key];
        $distribution = $score_distributions[$dim_key];
        
        echo '<div class="col-md-4 mb-3">';
        echo '<div class="border rounded p-3">';
        echo '<h6 class="mb-2">' . $dim_name . '</h6>';
        echo '<div class="mb-2">';
        echo '<small class="text-muted">' . get_string('average_score', 'block_tmms_24') . ':</small> ';
        echo '<strong>' . number_format($avg_score, 1) . '/40</strong>';
        echo '</div>';
        echo '<div class="progress mb-1" style="height: 8px;">';
        $progress_width = ($avg_score / 40) * 100;
        echo '<div class="progress-bar bg-primary" style="width: ' . $progress_width . '%"></div>';
        echo '</div>';
        echo '<small class="text-muted">';
        echo get_string('excellent', 'block_tmms_24') . ': ' . $distribution['excellent'] . ' | ';
        echo get_string('adequate', 'block_tmms_24') . ': ' . $distribution['adequate'] . ' | ';
        echo get_string('must_improve', 'block_tmms_24') . ': ' . $distribution['must_improve'];
        echo '</small>';
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Export buttons
    echo '<div class="row mb-3">';
    echo '<div class="col-12">';
    echo '<div class="btn-group" role="group">';
    $download_csv_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'csv']);
    $download_json_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'json']);
    echo '<a href="' . $download_csv_url . '" class="btn btn-success">';
    echo '<i class="fa fa-download"></i> ' . get_string('download_csv', 'block_tmms_24');
    echo '</a>';
    echo '<a href="' . $download_json_url . '" class="btn btn-info">';
    echo '<i class="fa fa-download"></i> ' . get_string('download_json', 'block_tmms_24');
    echo '</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Students table section
    echo '<div class="row">';
    echo '<div class="col-12">';
    echo '<h5>' . get_string('student_responses', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '</div>';

    // Results table with management capabilities
    $table = new flexible_table('block_tmms_24_report');
    $table->define_columns(array('user', 'perception', 'comprehension', 'regulation', 'completed', 'viewresults', 'download', 'actions'));
    $table->define_headers(array(
        get_string('student', 'block_tmms_24'), 
        get_string('perception', 'block_tmms_24'),
        get_string('comprehension', 'block_tmms_24'),
        get_string('regulation', 'block_tmms_24'),
        get_string('date_completed', 'block_tmms_24'),
        get_string('view_results', 'block_tmms_24'),
        get_string('download', 'block_tmms_24'),
        get_string('actions', 'block_tmms_24')
    ));
    $table->set_attribute('class', 'admintable');
    $table->define_baseurl($PAGE->url);
    $table->setup();

    foreach ($results as $result) {
        $user = $DB->get_record('user', ['id' => $result->user], 'id, firstname, lastname, picture, imagealt, firstnamephonetic, lastnamephonetic, middlename, alternatename, email');
        if (!$user) {
            continue;
        }
        
        // Calculate scores
        $responses = [];
        for ($i = 1; $i <= 24; $i++) {
            $item = 'item' . $i;
            $responses[] = $result->$item;
        }
        $scores = TMMS24Facade::calculate_scores($responses);
        
        $usercell = $OUTPUT->user_picture($user, array('size' => 35, 'courseid' => $courseid)) . ' ' . fullname($user);
        
        // View results button
        $viewresultsurl = new moodle_url('/blocks/tmms_24/student_results.php', array(
            'courseid' => $courseid,
            'userid' => $result->user
        ));
        
        $viewresultsbutton = html_writer::link(
            $viewresultsurl, 
            $OUTPUT->pix_icon('i/report', get_string('view_results', 'block_tmms_24')),
            array(
                'class' => 'btn btn-sm btn-outline-primary',
                'title' => get_string('view_results', 'block_tmms_24')
            )
        );

        // Delete button (if user has permission)
        $deletebutton = '';
        if (has_capability('block/tmms_24:viewallresults', $context) && has_capability('moodle/course:manageactivities', $context)) {
            $deleteurl = new moodle_url('/blocks/tmms_24/delete_response.php', array(
                'id' => $result->id,
                'courseid' => $courseid,
                'sesskey' => sesskey()
            ));
            
            $deletebutton = html_writer::link(
                $deleteurl, 
                $OUTPUT->pix_icon('t/delete', get_string('delete_response', 'block_tmms_24')),
                array(
                    'class' => 'btn btn-sm btn-outline-danger',
                    'title' => get_string('delete_response', 'block_tmms_24'),
                    'onclick' => 'return confirm("' . get_string('delete_response_confirm', 'block_tmms_24', fullname($user)) . '");'
                )
            );
        }

        // Download buttons for individual student
        $downloadcsvurl = new moodle_url('/blocks/tmms_24/export.php', array(
            'cid' => $courseid,
            'userid' => $result->user,
            'format' => 'csv'
        ));
        
        $downloadjsonurl = new moodle_url('/blocks/tmms_24/export.php', array(
            'cid' => $courseid,
            'userid' => $result->user,
            'format' => 'json'
        ));
        
        $downloadbuttons = html_writer::link(
            $downloadcsvurl, 
            $OUTPUT->pix_icon('i/export', 'CSV'),
            array(
                'class' => 'btn btn-sm btn-outline-success me-1',
                'title' => 'Download CSV'
            )
        );
        
        $downloadbuttons .= html_writer::link(
            $downloadjsonurl, 
            $OUTPUT->pix_icon('i/export', 'JSON'),
            array(
                'class' => 'btn btn-sm btn-outline-success',
                'title' => 'Download JSON'
            )
        );

        $row = array(
            $usercell,
            $scores['percepcion'],
            $scores['comprension'],
            $scores['regulacion'],
            userdate($result->created_at, get_string('strftimedatetimeshort')),
            $viewresultsbutton,
            $downloadbuttons,
            $deletebutton
        );
        $table->add_data($row);
    }
    
    $table->print_html();
} else {
    echo '<div class="alert alert-info">';
    echo get_string('no_results_yet', 'block_tmms_24');
    echo '</div>';
}

echo $OUTPUT->footer();
