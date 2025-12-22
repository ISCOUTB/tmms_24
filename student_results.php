<?php
require_once('../../config.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
if (!has_capability('block/tmms_24:viewallresults', $context)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

// Prevent looking up users outside this course.
if (!is_enrolled($context, $userid, 'block/tmms_24:taketest', true)) {
    redirect(new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]));
}

$user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

$PAGE->set_url('/blocks/tmms_24/student_results.php', ['courseid' => $courseid, 'userid' => $userid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('student_results', 'block_tmms_24'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'block_tmms_24'));
$PAGE->navbar->add(get_string('all_results_title', 'block_tmms_24'), new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]));
$PAGE->navbar->add(get_string('student_results', 'block_tmms_24'));

$PAGE->requires->css('/blocks/tmms_24/styles.css');

echo $OUTPUT->header();

// Contenedor para aislar estilos del tema (Cognitio)
echo "<div class='block_tmms_24_container'>";

// Student info header
echo '<div class="card mb-4">';
echo '<div class="card-header" style="background: linear-gradient(135deg, #fff5eb 0%, #f8f9fa 100%) !important;">';
echo '<h4 class="mb-0">' . get_string('results_for', 'block_tmms_24') . ' ' . fullname($user) . '</h4>';
echo '</div>';
echo '<div class="card-body">';

// Get student's test result (in any course) - both completed and in-progress
$result = $DB->get_record('tmms_24', ['user' => $userid]);

if (!$result) {
    echo '<div class="alert alert-info">';
    echo get_string('student_not_completed', 'block_tmms_24');
    echo '</div>';
} else if ($result->is_completed == 0) {
    // Test is in progress - show progress similar to CHASIDE
    
    // Calculate progress
    $answered = 0;
    for ($i = 1; $i <= 24; $i++) {
        $field = "item{$i}";
        if (isset($result->$field) && $result->$field !== null) {
            $answered++;
        }
    }
    $progress_percentage = round(($answered / 24) * 100, 1);
    
    echo '<div class="alert alert-warning" role="alert">';
    echo '<h4 class="alert-heading"><i class="fa fa-clock-o"></i> ' . get_string('test_in_progress', 'block_tmms_24') . '</h4>';
    echo '<p>' . get_string('test_in_progress_message', 'block_tmms_24', fullname($user)) . '</p>';
    echo '<hr>';
    echo '<p class="mb-1"><strong>' . get_string('progress_label', 'block_tmms_24') . ':</strong></p>';
    echo '<div class="progress mb-2" style="height: 30px;">';
    echo '<div class="progress-bar bg-warning" role="progressbar" style="height: 30px !important; width: ' . $progress_percentage . '%" aria-valuenow="' . $progress_percentage . '" aria-valuemin="0" aria-valuemax="100">';
    echo '<strong>' . $progress_percentage . '%</strong>';
    echo '</div>';
    echo '</div>';
    echo '<p><strong>' . get_string('has_answered', 'block_tmms_24') . ':</strong> ' . $answered . '/24 ' . get_string('questions', 'block_tmms_24') . '</p>';
    
    // Special message if all questions answered but not submitted
    if ($answered == 24) {
        echo '<div class="alert mt-2" role="alert" style="background-color: #cce6ea !important; border-color: #b8dce2 !important; color: #00434e !important;">';
        echo '<i class="fa fa-info-circle"></i> ';
        echo '<strong>' . get_string('remind_submit_test', 'block_tmms_24') . '</strong>';
        echo '</div>';
    }
    
    echo '<p class="mb-0"><em>' . get_string('results_available_when_complete', 'block_tmms_24', fullname($user)) . '</em></p>';
    echo '</div>';
    
} else {
    // Calculate scores from individual item responses
    $responses = [];
    for ($i = 1; $i <= 24; $i++) {
        $item = 'item' . $i;
        $responses[] = $result->$item;
    }
    $scores = TMMS24Facade::calculate_scores($responses);
    $interpretations = TMMS24Facade::get_all_interpretations($scores, $result->gender);
    $interpretations_long = TMMS24Facade::get_all_interpretations_long($scores, $result->gender);

    // Get interpretations directly from the array
    $percepcion_interp = $interpretations['percepcion'];
    $comprension_interp = $interpretations['comprension'];
    $regulacion_interp = $interpretations['regulacion'];

    // Helper to get goal text
    $get_goal_text = function($dim, $gender) {
        if ($dim === 'percepcion') {
            $range = ($gender === 'M') ? '22-32' : '25-35';
            $optimal = ($gender === 'M') ? '27' : '30';
            $a = new stdClass();
            $a->range = $range;
            $a->optimal = $optimal;
            return get_string('goal_perception', 'block_tmms_24', $a);
        } else {
            // Comprension / Regulacion
            if ($dim === 'comprension') {
                 $min = ($gender === 'M') ? 36 : 35;
            } else {
                 $min = ($gender === 'M') ? 36 : 35;
            }
            $a = new stdClass();
            $a->range = $min . '-40';
            return get_string('goal_linear', 'block_tmms_24', $a);
        }
    };

    // Begin displaying results
    // Display results summary
    echo '<div class="block_tmms_24_results_summary">';
    echo '<div class="row mb-4">';
    echo '<div class="col-md-4 mb-4">';
    echo '<div class="card" style="border-color: #ff6600 !important;">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('perception', 'block_tmms_24') . '</h5>';
    echo '<h3 style="color: #ff6600;">' . $scores['percepcion'] . '/40</h3>';
    echo '<p class="mb-0">' . $percepcion_interp . '</p>';
    echo '<div class="small text-muted mt-2" style="font-size: 0.85em; color: #999 !important;"><i class="fa fa-bullseye"></i> ' . $get_goal_text('percepcion', $result->gender) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4 mb-4">';
    echo '<div class="card" style="border-color: #ff8533 !important;">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('comprehension', 'block_tmms_24') . '</h5>';
    echo '<h3 style="color: #ff8533;">' . $scores['comprension'] . '/40</h3>';
    echo '<p class="mb-0">' . $comprension_interp . '</p>';
    echo '<div class="small text-muted mt-2" style="font-size: 0.85em; color: #999 !important;"><i class="fa fa-bullseye"></i> ' . $get_goal_text('comprension', $result->gender) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4 mb-4">';
    echo '<div class="card" style="border-color: #ffaa66 !important;">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('regulation', 'block_tmms_24') . '</h5>';
    echo '<h3 style="color: #ffaa66;">' . $scores['regulacion'] . '/40</h3>';
    echo '<p class="mb-0">' . $regulacion_interp . '</p>';
    echo '<div class="small text-muted mt-2" style="font-size: 0.85em; color: #999 !important;"><i class="fa fa-bullseye"></i> ' . $get_goal_text('regulacion', $result->gender) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Test completion info
    echo '<div class="row mb-4">';
    echo '<div class="col-md-4">';
    echo '<strong>' . get_string('date_completed', 'block_tmms_24') . ':</strong> ';
    echo userdate($result->created_at, get_string('strftimedatetimeshort'));
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<strong>' . get_string('age', 'block_tmms_24') . ':</strong> ';
    echo $result->age ? $result->age : '-';
    echo '</div>';
    echo '<div class="col-md-4">';
    echo '<strong>' . get_string('gender', 'block_tmms_24') . ':</strong> ';
    
    // Convert gender code to display string
    $gender_display = '';
    switch($result->gender) {
        case 'M':
            $gender_display = get_string('gender_male', 'block_tmms_24');
            break;
        case 'F':
            $gender_display = get_string('female', 'block_tmms_24');
            break;
        case 'prefiero_no_decir':
            $gender_display = get_string('gender_other_genres', 'block_tmms_24');
            break;
        default:
            $gender_display = $result->gender; // fallback
    }
    echo $gender_display;
    echo '</div>';
    echo '</div>';

    // Results interpretation (detailed)
    echo '<div class="card mt-4 mb-4">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0">' . get_string('results_interpretation', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="row">';
    echo '<div class="col-md-4 mb-3 mb-md-0"><strong>' . get_string('perception', 'block_tmms_24') . ':</strong><br>' . s($interpretations['percepcion']) . '<div class="mt-2 text-muted">' . s($interpretations_long['percepcion']) . '</div></div>';
    echo '<div class="col-md-4 mb-3 mb-md-0"><strong>' . get_string('comprehension', 'block_tmms_24') . ':</strong><br>' . s($interpretations['comprension']) . '<div class="mt-2 text-muted">' . s($interpretations_long['comprension']) . '</div></div>';
    echo '<div class="col-md-4 mb-3 mb-md-0"><strong>' . get_string('regulation', 'block_tmms_24') . ':</strong><br>' . s($interpretations['regulacion']) . '<div class="mt-2 text-muted">' . s($interpretations_long['regulacion']) . '</div></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Detailed responses
    echo '<div class="card mt-4">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0">' . get_string('detailed_responses', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';

    // Display the legend for response scale
    echo '<div class="mb-3">';
    echo '<strong>' . get_string('response_scale_legend', 'block_tmms_24') . ':</strong> ';
    echo get_string('scale_1', 'block_tmms_24') . ', ';
    echo get_string('scale_2', 'block_tmms_24') . ', ';
    echo get_string('scale_3', 'block_tmms_24') . ', ';
    echo get_string('scale_4', 'block_tmms_24') . ', ';
    echo get_string('scale_5', 'block_tmms_24');
    echo '</div>';
    
    $dimensions = [
        'perception' => range(1, 8),
        'comprehension' => range(9, 16), 
        'regulation' => range(17, 24)
    ];

    foreach ($dimensions as $dimension => $items) {
        echo '<div class="card mb-3">';
        echo '<div class="card-header">';
        echo '<h6 class="mb-0">' . get_string($dimension, 'block_tmms_24') . '</h6>';
        echo '</div>';
        echo '<div class="card-body">';
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        
        foreach ($items as $item_num) {
            $item_key = 'item' . $item_num;
            $response_value = $result->$item_key;
            
            // Calculamos el porcentaje (suponiendo escala 1 a 5)
            $percent = ($response_value / 5) * 100;
            
            echo '<tr>';
            // Columna del número de pregunta
            echo '<td style="width: 40px;" class="text-center text-muted">' . $item_num . '.</td>';
            
            // Columna del texto de la pregunta
            echo '<td>' . get_string('item' . $item_num, 'block_tmms_24') . '</td>';
            
            // Columna visual (Barra + Número)
            echo '<td style="width: 180px; vertical-align: middle;">';
            echo '<div class="d-flex align-items-center">';
                
            // 1. La barra de progreso (ocupa el espacio sobrante con flex-grow-1)
            echo '<div class="progress flex-grow-1 mr-2" style="height: 8px; background-color: #ffebe0;">';
                echo '<div class="progress-bar" role="progressbar" style="width: ' . $percent . '%; background-color: #ff6600;" aria-valuenow="' . $response_value . '" aria-valuemin="0" aria-valuemax="5"></div>';
            echo '</div>';
            
            // 2. El número a la derecha
            echo '<span class="text-muted font-weight-bold" style="font-size: 0.9em; white-space: nowrap;">';
            echo $response_value . ' / 5';
            echo '</span>';
                
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>'; // End card-body
    echo '</div>'; // End card

}

echo '</div>';  // End card-body
echo '</div>';  // End card

echo '</div>';  // End block_tmms_24_container

// Navigation buttons (match personality_test pattern)
echo html_writer::start_div('mt-5 text-center d-flex gap-3 justify-content-center');
echo html_writer::link(
    new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
    '<i class="fa fa-arrow-left mr-2"></i>' . get_string('back_to_teacher_view', 'block_tmms_24'),
    array('class' => 'btn btn-secondary btn-modern mr-3')
);
echo html_writer::link(
    new moodle_url('/course/view.php', array('id' => $courseid)),
    '<i class="fa fa-home mr-2"></i>' . get_string('back_to_course', 'block_tmms_24'),
    array('class' => 'btn btn-modern', 'style' => 'background: linear-gradient(135deg, #ffaa66 0%, #ff6600 100%); border: none; color: white;')
);
echo html_writer::end_div();

echo $OUTPUT->footer();

