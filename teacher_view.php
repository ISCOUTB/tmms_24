<?php
require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
if (!has_capability('block/tmms_24:viewallresults', $context)) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$action = optional_param('action', '', PARAM_ALPHA);
$entryid = optional_param('id', 0, PARAM_INT);

$PAGE->set_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('standard');
$title = get_string('tmms_24_dashboard', 'block_tmms_24');
$PAGE->set_title($title . ' : ' . $course->fullname);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('pluginname', 'block_tmms_24'));
$PAGE->navbar->add(get_string('all_results_title', 'block_tmms_24'));

$PAGE->requires->css('/blocks/tmms_24/styles.css');

// Generate tmms_24 icon for header (used in both normal view and delete confirmation screens).
$iconurl = new moodle_url('/blocks/tmms_24/pix/tmms_24_icon.svg');
$icon_html = '<img src="' . $iconurl . '" alt="TMMS-24 Icon" style="width: 50px; height: 50px; vertical-align: middle; margin-right: 15px;" />';

// Get enrolled students in this course via capability (avoid hardcoded role IDs).
$enrolled_users = get_enrolled_users($context, 'block/tmms_24:taketest', 0, 'u.id', null, 0, 0, true);
$student_ids = array();
foreach ($enrolled_users as $user) {
    $candidateid = (int)$user->id;
    // Defensive: never include teachers/managers/siteadmins in the student responses table.
    if (is_siteadmin($candidateid)) {
        continue;
    }
    if (has_capability('block/tmms_24:viewallresults', $context, $candidateid)) {
        continue;
    }
    $student_ids[] = $candidateid;
}

// Process deletion (CHASIDE-like confirmation flow).
if ($action === 'delete' && $entryid && confirm_sesskey()) {
    $confirm = optional_param('confirm', 0, PARAM_INT);
    if ($confirm) {
        if (!has_capability('moodle/course:manageactivities', $context)) {
            redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
        }

        $entry = $DB->get_record('tmms_24', array('id' => $entryid), '*', MUST_EXIST);

        // Safety: this table is global (one row per user). Ensure the user belongs to this course context.
        if (!is_enrolled($context, $entry->user, 'block/tmms_24:taketest', true)) {
            redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
        }

        $DB->delete_records('tmms_24', array('id' => $entryid));
        $deleteduser = $DB->get_record('user', array('id' => $entry->user));
        redirect(
            new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)),
            get_string('response_deleted_success', 'block_tmms_24', fullname($deleteduser))
        );
        exit;
    }
}

// Deletion confirmation screen.
if ($action === 'delete' && $entryid) {
    if (!has_capability('moodle/course:manageactivities', $context)) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    }

    $entry = $DB->get_record('tmms_24', array('id' => $entryid), '*', MUST_EXIST);
    if (!is_enrolled($context, $entry->user, 'block/tmms_24:taketest', true)) {
        redirect(new moodle_url('/course/view.php', array('id' => $courseid)));
    }
    $targetuser = $DB->get_record('user', array('id' => $entry->user), 'id, firstname, lastname, picture, imagealt, firstnamephonetic, lastnamephonetic, middlename, alternatename');

    // Render page header AFTER we've ensured no redirect will happen.
    echo $OUTPUT->header();
    echo "<div class='block_tmms_24_container'>";
    echo "<h1 class='mb-4 text-center'>" . $icon_html . s($title) . "</h1>";

    echo "<div class='alert alert-warning'>";
    echo "<h4>" . get_string('delete_response', 'block_tmms_24') . "</h4>";
    echo "<p>" . get_string('delete_response_confirm', 'block_tmms_24', fullname($targetuser)) . "</p>";
    echo "<div class='mt-3'>";
    echo "<a href='" . new moodle_url('/blocks/tmms_24/teacher_view.php', array(
        'courseid' => $courseid,
        'action' => 'delete',
        'id' => $entryid,
        'confirm' => 1,
        'sesskey' => sesskey(),
    )) . "' class='btn btn-danger'>" . get_string('delete') . "</a> ";
    echo "<a href='" . new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $courseid)) . "' class='btn btn-secondary text-white'>" . get_string('cancel') . "</a>";
    echo "</div>";
    echo "</div>";

    echo "</div>";
    echo $OUTPUT->footer();
    exit;
}

// From here on, we are not redirecting due to delete actions, so it's safe to output the page.
echo $OUTPUT->header();

// Contenedor para aislar estilos del tema (Cognitio)
echo "<div class='block_tmms_24_container'>";

echo "<h1 class='mb-4 text-center'>" . $icon_html . s($title) . "</h1>";

// Description banner
echo "<div class='alert alert-info mb-4'>" . format_text(get_string('admin_dashboard_description', 'block_tmms_24'), FORMAT_HTML) . "</div>";

// Get results only for enrolled students (both completed and in progress)
$all_results = array();
$results_completed = array();
$results_in_progress = array();
$all_filtered_results = array();
if (!empty($student_ids)) {
    list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED);
    $all_results = $DB->get_records_select('tmms_24', "user $insql", $params);
    
    // Separate completed from in-progress (only count in-progress if has at least 1 answer)
    foreach ($all_results as $result) {
        if ($result->is_completed == 1) {
            $results_completed[] = $result;
        } else {
            // Count answered questions to determine if really in progress
            $answered_count = 0;
            for ($i = 1; $i <= 24; $i++) {
                $item = 'item' . $i;
                if (isset($result->$item) && $result->$item !== null) {
                    $answered_count++;
                }
            }
            // Only add to in_progress if at least 1 question answered
            if ($answered_count > 0) {
                $results_in_progress[] = $result;
            }
        }
    }
    
    // Merge both arrays and sort by last action date DESC (most recent first)
    $all_filtered_results = array_merge($results_completed, $results_in_progress);
    usort($all_filtered_results, function($a, $b) {
        // For completed tests, use created_at; for in-progress, use updated_at (or created_at if not set)
        $a_time = ($a->is_completed == 1) ? $a->created_at : (isset($a->updated_at) && $a->updated_at ? $a->updated_at : $a->created_at);
        $b_time = ($b->is_completed == 1) ? $b->created_at : (isset($b->updated_at) && $b->updated_at ? $b->updated_at : $b->created_at);
        return $b_time - $a_time;
    });
    
    // Update the arrays to use the sorted merged list
    $results_completed = array();
    $results_in_progress = array();
    foreach ($all_filtered_results as $result) {
        if ($result->is_completed == 1) {
            $results_completed[] = $result;
        } else {
            $results_in_progress[] = $result;
        }
    }
}

// Calculate statistics
$total_enrolled = count($student_ids);
$total_completed = count($results_completed);
$total_in_progress = count($results_in_progress);
$completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;

// Statistics cards
echo '<div class="row mb-4">';

// Total enrolled
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card" style="border-color: #f60 !important;">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-users text-primary" style="font-size: 2em; margin-bottom: 10px;"></i>';
echo '<h5 class="card-title">' . get_string('enrolled_students', 'block_tmms_24') . '</h5>';
echo '<h2 class="text-primary">' . $total_enrolled . '</h2>';
echo '</div>';
echo '</div>';
echo '</div>';

// Completed tests
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-success">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-check-circle text-success" style="font-size: 2em; margin-bottom: 10px;"></i>';
echo '<h5 class="card-title">' . get_string('total_completed', 'block_tmms_24') . '</h5>';
echo '<h2 class="text-success">' . $total_completed . '</h2>';
echo '</div>';
echo '</div>';
echo '</div>';

// In progress tests
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card border-warning">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-hourglass-half text-warning" style="font-size: 2em; margin-bottom: 10px;"></i>';
echo '<h5 class="card-title">' . get_string('in_progress', 'block_tmms_24') . '</h5>';
echo '<h2 class="text-warning">' . $total_in_progress . '</h2>';
echo '</div>';
echo '</div>';
echo '</div>';

// Completion rate
echo '<div class="col-md-3 col-sm-6 mb-3">';
echo '<div class="card" style="border-color: #f60 !important;">';
echo '<div class="card-body text-center">';
echo '<i class="fa fa-percent text-primary" style="font-size: 2em; margin-bottom: 10px;"></i>';
echo '<h5 class="card-title">' . get_string('completion_rate', 'block_tmms_24') . '</h5>';
echo '<h2 class="text-primary">' . number_format($completion_rate, 1) . '%</h2>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>';

if (!empty($results_completed) || !empty($results_in_progress)) {
    // Dimension statistics are only meaningful for completed tests.
    if (!empty($results_completed)) {
        // Filter logic
        $gender_filter = optional_param('gender_filter', 'all', PARAM_ALPHA);
        
        // Average scores statistics (only for completed tests)
        $avg_scores = ['percepcion' => 0, 'comprension' => 0, 'regulacion' => 0];
        $score_distributions = [
            'percepcion' => ['difficulty' => 0, 'adequate' => 0, 'excellent_excessive' => 0],
            'comprension' => ['difficulty' => 0, 'adequate' => 0, 'excellent' => 0],
            'regulacion' => ['difficulty' => 0, 'adequate' => 0, 'excellent' => 0]
        ];
        
        $count_filtered = 0;
        foreach ($results_completed as $result) {
            // Apply filter
            if ($gender_filter !== 'all' && $result->gender !== $gender_filter) {
                continue;
            }
            $count_filtered++;

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
                // Get interpretation directly from the array
                $interp = $interpretations[$dimension];
                
                // Categorizar según las nuevas interpretaciones
                if (strpos($interp, get_string('perception_difficulty_feeling', 'block_tmms_24')) !== false ||
                    strpos($interp, get_string('comprehension_difficulty_understanding', 'block_tmms_24')) !== false ||
                    strpos($interp, get_string('regulation_difficulty_managing', 'block_tmms_24')) !== false) {
                    $score_distributions[$dimension]['difficulty']++;
                } elseif (strpos($interp, get_string('perception_excessive_attention', 'block_tmms_24')) !== false) {
                    $score_distributions[$dimension]['excellent_excessive']++; // Para percepción, excesiva atención
                } elseif (strpos($interp, get_string('comprehension_great_clarity', 'block_tmms_24')) !== false ||
                        strpos($interp, get_string('regulation_great_capacity', 'block_tmms_24')) !== false) {
                    $score_distributions[$dimension]['excellent']++;
                } else {
                    // Capacidad adecuada (todas las dimensiones)
                    $score_distributions[$dimension]['adequate']++;
                }
            }
        }
        
        if ($count_filtered > 0) {
            $avg_scores['percepcion'] /= $count_filtered;
            $avg_scores['comprension'] /= $count_filtered;
            $avg_scores['regulacion'] /= $count_filtered;
        }

        // Dimension averages (CHASIDE-like cards)
        echo "<div class='row mt-4'>";
        echo "<div class='col-12'>";
        echo "<div class='card'>";
        echo "<div class='card-header'>";
        echo "<h5 class='mb-0'><i class='fa fa-chart-bar'></i> " . get_string('dimension_statistics', 'block_tmms_24') . "</h5>";
        echo "</div>";
        echo "<div class='card-body' id='tmms-stats-ajax-wrapper' data-filter='" . $gender_filter . "'>";

        // Filter UI moved inside card
        echo '<div class="d-flex justify-content-end align-items-center mb-3">';
        echo '<span class="mr-2 font-weight-bold">' . get_string('filter_by_gender', 'block_tmms_24') . ': </span>';
        
        $url_all = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'gender_filter' => 'all']);
        $url_m = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'gender_filter' => 'M']);
        $url_f = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid, 'gender_filter' => 'F']);
        
        // Custom styles for orange theme
        $active_style = 'background-color: #ff6600; border-color: #ff6600; color: white;';
        $inactive_style = 'color: #ff6600; border-color: #ff6600; background-color: white;';

        $style_all = ($gender_filter === 'all') ? $active_style : $inactive_style;
        $style_m = ($gender_filter === 'M') ? $active_style : $inactive_style;
        $style_f = ($gender_filter === 'F') ? $active_style : $inactive_style;
        
        echo '<div class="btn-group" role="group">';
        echo '<a href="' . $url_all . '" class="btn btn-sm tmms-filter-btn" style="' . $style_all . '">' . get_string('all', 'block_tmms_24') . '</a>';
        echo '<a href="' . $url_m . '" class="btn btn-sm tmms-filter-btn" style="' . $style_m . '">' . get_string('gender_male', 'block_tmms_24') . '</a>';
        echo '<a href="' . $url_f . '" class="btn btn-sm tmms-filter-btn" style="' . $style_f . '">' . get_string('gender_female', 'block_tmms_24') . '</a>';
        echo '</div>';
        echo '</div>';

        if ($gender_filter === 'all') {
            echo '<div class="alert alert-warning py-2 mb-3 small"><i class="fa fa-exclamation-triangle"></i> ' . get_string('gender_filter_warning', 'block_tmms_24') . '</div>';
        }

        echo "<div class='row'>";
        $dimensions = [
            'percepcion' => get_string('perception', 'block_tmms_24'),
            'comprension' => get_string('comprehension', 'block_tmms_24'),
            'regulacion' => get_string('regulation', 'block_tmms_24')
        ];
        
        foreach ($dimensions as $dim_key => $dim_name) {
            $avg_score = $avg_scores[$dim_key];
            $distribution = $score_distributions[$dim_key];

            // Calculate Progress Bar Logic
            $progress_width = ($avg_score / 40) * 100;
            $bar_gradient_css = "";
            
            if ($gender_filter !== 'all') {
                // Colors
                $c_low = '#ff8e8e'; // Soft Red
                $c_mid = '#4cd137'; // Bright Green
                $c_high = '#48dbfb'; // Bright Blue
                $c_excess = '#ff6b6b'; // Red for excessive
                
                // Calculate background size to ensure gradient maps to 0-40 scale regardless of width
                $bg_size = ($progress_width > 0) ? (100 / $progress_width * 100) : 100;

                if ($dim_key === 'percepcion') {
                    // Perception: Low (Red) -> Adequate (Green) -> Excessive (Red)
                    // Men: <22 (Low), 22-32 (Adequate, Opt 27), >32 (Excessive)
                    // Women: <25 (Low), 25-35 (Adequate, Opt 30), >35 (Excessive)
                    
                    if ($gender_filter === 'M') { 
                        $p_start_green = (22/40)*100; 
                        $p_optimal = (27/40)*100; 
                        $p_end_green = (32/40)*100; 
                    } else { 
                        $p_start_green = (25/40)*100; 
                        $p_optimal = (30/40)*100; 
                        $p_end_green = (35/40)*100; 
                    }
                    
                    // Gradient: Red -> Green (at optimal) -> Red
                    // We ensure Green is dominant around the optimal point
                    // Adjusted to widen the transition area as requested
                    $bar_gradient_css = "background-image: linear-gradient(to right, 
                        $c_low 0%, 
                        $c_low " . ($p_start_green - 10) . "%, 
                        $c_mid " . $p_optimal . "%, 
                        $c_excess " . ($p_end_green + 10) . "%, 
                        $c_excess 100%) !important; background-size: {$bg_size}% 100% !important;";
                        
                } else {
                    // Linear: Low (Red) -> Adequate (Green) -> Excellent (Blue)
                    
                    if ($dim_key === 'comprension') {
                        // Men: <26, 26-35, >35
                        // Women: <24, 24-34, >34
                        if ($gender_filter === 'M') { $t1 = 26; $t2 = 35; }
                        else { $t1 = 24; $t2 = 34; }
                    } else { // regulacion
                        // Men: <23, 23-35, >35
                        // Women: <23, 23-34, >34
                        if ($gender_filter === 'M') { $t1 = 23; $t2 = 35; }
                        else { $t1 = 23; $t2 = 34; }
                    }
                    
                    $p_t1 = ($t1/40)*100;
                    $p_t2 = ($t2/40)*100;
                    
                    // Gradient: Red -> Green -> Blue
                    // Red ends approaching T1.
                    // Green is dominant between T1 and T2.
                    // Blue starts after T2 (e.g. 35) and reaches max at 40.
                    
                    $bar_gradient_css = "background-image: linear-gradient(to right, 
                        $c_low 0%, 
                        $c_low " . ($p_t1 - 5) . "%, 
                        $c_mid " . ($p_t1 + 5) . "%, 
                        $c_mid " . $p_t2 . "%, 
                        $c_high 100%) !important; background-size: {$bg_size}% 100% !important;";
                }
            }

            echo "<div class='col-lg-4 col-md-6 mb-3'>";
            echo "<div class='card tmms-dimension-avg-card h-100 shadow-sm' style='cursor: pointer; transition: all 0.3s; border-radius: 15px;' onclick='showRanges(\"" . $dim_key . "\")'>";
            echo "<div class='card-body d-flex flex-column p-4'>";
            
            // Header
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h5 class='font-weight-bold mb-0' style='color: #444;'>" . s($dim_name) . "</h5>";
            echo "</div>";

            // Average Score & Progress Bar (Conditional)
            if ($gender_filter !== 'all') {
                echo "<div class='mb-4'>";
                echo "<div class='d-flex justify-content-between align-items-end mb-1'>";
                echo "<span class='text-muted small'>" . get_string('average_score', 'block_tmms_24') . "</span>";
                echo "<h3 class='mb-0 font-weight-bold' style='color: #333;'>" . number_format($avg_score, 1) . "<span class='text-muted' style='font-size: 0.5em; font-weight: normal;'>/40</span></h3>";
                echo "</div>";
                
                echo "<div class='progress' style='height: 12px; border-radius: 6px; background-color: #e9ecef;'>";
                echo "<div class='progress-bar' role='progressbar' style='width: {$progress_width}%; {$bar_gradient_css} border-radius: 6px; transition: width 1s ease-in-out;' aria-valuenow='{$avg_score}' aria-valuemin='0' aria-valuemax='40'></div>";
                echo "</div>";
                echo "</div>";
            } else {
                // Placeholder for All Genders
                echo "<div class='text-center mb-4 py-3' style='background-color: #f8f9fa; border-radius: 10px; border: 1px dashed #dee2e6;'>";
                echo "<i class='fa fa-lock text-muted mb-2' style='font-size: 1.5em; opacity: 0.5;'></i>";
                echo "<div class='small text-muted'>" . get_string('select_gender_for_avg', 'block_tmms_24') . "</div>";
                echo "</div>";
            }

            // Distribution Stats (Icons)
            echo "<div class='mt-auto'>";
            echo "<div class='row text-center'>";
            
            // Helper for stats
            $print_stat = function($count, $label, $icon, $color) {
                echo "<div class='col-4 px-1'>";
                echo "<div class='mb-1'><i class='fa $icon' style='color: $color; font-size: 1.2em;'></i></div>";
                echo "<div class='font-weight-bold' style='font-size: 1.1em; color: #333;'>$count</div>";
                echo "<div class='text-muted' style='font-size: 0.65em; text-transform: uppercase; letter-spacing: 0.5px;'>$label</div>";
                echo "</div>";
            };

            if ($dim_key === 'percepcion') {
                $print_stat($distribution['difficulty'], get_string('difficulty_category', 'block_tmms_24'), 'fa-exclamation-circle', '#ffc107');
                $print_stat($distribution['adequate'], get_string('adequate_category', 'block_tmms_24'), 'fa-check-circle', '#28a745');
                $print_stat($distribution['excellent_excessive'], get_string('excessive_category', 'block_tmms_24'), 'fa-exclamation-triangle', '#dc3545');
            } else {
                $print_stat($distribution['difficulty'], get_string('difficulty_category', 'block_tmms_24'), 'fa-exclamation-circle', '#ffc107');
                $print_stat($distribution['adequate'], get_string('adequate_category', 'block_tmms_24'), 'fa-check-circle', '#28a745');
                $print_stat($distribution['excellent'], get_string('excellent_category', 'block_tmms_24'), 'fa-star', '#007bff');
            }
            
            echo "</div>"; // row

            // Click to view link
            echo "<div class='text-center mt-3 pt-3 border-top'>";
            echo "<small class='text-primary font-weight-bold' style='cursor: pointer;'><i class='fa fa-eye'></i> " . get_string('click_to_view_ranges', 'block_tmms_24') . "</small>";
            echo "</div>";

            echo "</div>"; // mt-auto
            
            // Mobile-only container for ranges
            echo "<div id='mobile-ranges-" . $dim_key . "' class='d-md-none mt-3' style='display: none;'></div>";
            
            echo "</div>"; // card-body
            echo "</div>"; // card
            echo "</div>"; // col
        }
        echo "</div>"; // row

        // Desktop-only container for ranges
        echo "<div id='desktop-ranges-container' class='d-none d-md-block mt-4'></div>";

        // Prepare data for JS
        $ranges_data = [
            'percepcion' => [
                'title' => get_string('perception', 'block_tmms_24'),
                'headers' => [get_string('range_low_perception', 'block_tmms_24'), get_string('range_adequate', 'block_tmms_24'), get_string('range_high_perception', 'block_tmms_24')],
                'rows' => [
                    'M' => ['< 22', '22 - 32', '> 32'],
                    'F' => ['< 25', '25 - 35', '> 35']
                ]
            ],
            'comprension' => [
                'title' => get_string('comprehension', 'block_tmms_24'),
                'headers' => [get_string('range_low_linear', 'block_tmms_24'), get_string('range_adequate', 'block_tmms_24'), get_string('range_high_linear', 'block_tmms_24')],
                'rows' => [
                    'M' => ['< 26', '26 - 35', '> 35'],
                    'F' => ['< 24', '24 - 34', '> 34']
                ]
            ],
            'regulacion' => [
                'title' => get_string('regulation', 'block_tmms_24'),
                'headers' => [get_string('range_low_linear', 'block_tmms_24'), get_string('range_adequate', 'block_tmms_24'), get_string('range_high_linear', 'block_tmms_24')],
                'rows' => [
                    'M' => ['< 23', '23 - 35', '> 35'],
                    'F' => ['< 23', '23 - 34', '> 34']
                ]
            ]
        ];
        
        $current_gender_filter = $gender_filter;
        $str_male = get_string('male', 'block_tmms_24');
        $str_female = get_string('gender_female', 'block_tmms_24'); // Using the inclusive string
        $str_gender = get_string('gender', 'block_tmms_24');
        $str_dimension = get_string('dimension', 'block_tmms_24');

        echo "<script>
        var tmmsRangesData = " . json_encode($ranges_data) . ";
        var tmmsCurrentGenderFilter = '" . $current_gender_filter . "';
        var tmmsStrMale = '" . $str_male . "';
        var tmmsStrFemale = '" . $str_female . "';
        var tmmsStrGender = '" . $str_gender . "';
        var tmmsStrDimension = '" . $str_dimension . "';

        function showRanges(dimKey) {
            var data = tmmsRangesData[dimKey];
            if (!data) return;

            var html = '<div class=\"card bg-light border-0 shadow-none\" style=\"box-shadow: none !important; border: none !important;\"><div class=\"card-body p-3\">';
            html += '<h6 class=\"card-title text-center mb-3\">' + data.title + ' - ' + '" . get_string('reference_ranges', 'block_tmms_24') . "' + '</h6>';
            html += '<div class=\"table-responsive\"><table class=\"table table-sm table-bordered text-center small bg-white mb-0\">';
            html += '<thead class=\"thead-light\"><tr>';
            html += '<th>' + tmmsStrGender + '</th>';
            data.headers.forEach(function(h) { html += '<th>' + h + '</th>'; });
            html += '</tr></thead><tbody>';

            // Rows logic
            var showM = (tmmsCurrentGenderFilter === 'all' || tmmsCurrentGenderFilter === 'M');
            var showF = (tmmsCurrentGenderFilter === 'all' || tmmsCurrentGenderFilter === 'F');

            if (showM) {
                html += '<tr><td>' + tmmsStrMale + '</td>';
                data.rows.M.forEach(function(val) { html += '<td>' + val + '</td>'; });
                html += '</tr>';
            }
            if (showF) {
                html += '<tr><td>' + tmmsStrFemale + '</td>';
                data.rows.F.forEach(function(val) { html += '<td>' + val + '</td>'; });
                html += '</tr>';
            }

            html += '</tbody></table></div></div></div>';

            // Mobile Logic: Toggle inside the card
            var mobileContainer = document.getElementById('mobile-ranges-' + dimKey);
            if (mobileContainer) {
                // Hide all other mobile containers first
                document.querySelectorAll('[id^=\"mobile-ranges-\"]').forEach(function(el) {
                    if (el.id !== 'mobile-ranges-' + dimKey) el.style.display = 'none';
                });
                
                if (mobileContainer.style.display === 'none' || mobileContainer.innerHTML === '') {
                    mobileContainer.innerHTML = html;
                    mobileContainer.style.display = 'block';
                } else {
                    mobileContainer.style.display = 'none';
                }
            }

            // Desktop Logic: Show in bottom container
            var desktopContainer = document.getElementById('desktop-ranges-container');
            if (desktopContainer) {
                // Check if we are clicking the same dimension to toggle
                var currentDim = desktopContainer.getAttribute('data-active-dim');
                
                // Check if currently visible. We check if we have an active dim set and if display is not explicitly none.
                // Note: d-md-block forces display:block !important, so we must use setProperty to override it when hiding.
                
                if (currentDim === dimKey && desktopContainer.style.display !== 'none') {
                    // Toggle OFF
                    desktopContainer.style.setProperty('display', 'none', 'important');
                    desktopContainer.removeAttribute('data-active-dim');
                    desktopContainer.innerHTML = '';
                } else {
                    // Show New
                    desktopContainer.innerHTML = html;
                    desktopContainer.style.setProperty('display', 'block', 'important');
                    desktopContainer.setAttribute('data-active-dim', dimKey);
                }
            }
        }

        // AJAX Filter Logic
        document.addEventListener('DOMContentLoaded', function() {
            document.body.addEventListener('click', function(e) {
                if (e.target.closest('.tmms-filter-btn')) {
                    e.preventDefault();
                    var btn = e.target.closest('.tmms-filter-btn');
                    var url = btn.getAttribute('href');
                    var wrapper = document.getElementById('tmms-stats-ajax-wrapper');
                    
                    if (wrapper) {
                        wrapper.style.opacity = '0.5';
                        wrapper.style.pointerEvents = 'none';
                        
                        fetch(url)
                            .then(response => response.text())
                            .then(html => {
                                var parser = new DOMParser();
                                var doc = parser.parseFromString(html, 'text/html');
                                var newContent = doc.getElementById('tmms-stats-ajax-wrapper');
                                
                                if (newContent) {
                                    wrapper.innerHTML = newContent.innerHTML;
                                    wrapper.setAttribute('data-filter', newContent.getAttribute('data-filter'));
                                    
                                    // Update global filter variable
                                    tmmsCurrentGenderFilter = newContent.getAttribute('data-filter');
                                    
                                    // Re-run any scripts if necessary (though we updated the global var directly)
                                    // The showRanges function relies on the global var we just updated.
                                }
                                
                                wrapper.style.opacity = '1';
                                wrapper.style.pointerEvents = 'auto';
                            })
                            .catch(err => {
                                console.error('Error fetching stats:', err);
                                wrapper.style.opacity = '1';
                                wrapper.style.pointerEvents = 'auto';
                            });
                    }
                }
            });
        });
        </script>";

            echo "</div>"; // card-body
            echo "</div>"; // card
            echo "</div>"; // col-12
            echo "</div>"; // row
    }

    // Participants list (CHASIDE-like)
    $download_csv_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'csv']);
    $download_json_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'json']);

    echo "<div class='card mt-5'>";
    echo "<div class='card-header'>";
    echo "<h5 class='mb-0'>" . s(get_string('student_responses', 'block_tmms_24')) . "</h5>";
    echo "</div>";
    echo "<div class='card-body'>";
    echo "<div class='row mb-3'>";
    echo "<div class='col-md-6'>";
    echo "<input type='text' id='searchInput' class='form-control' placeholder='" . s(get_string('search_student', 'block_tmms_24')) . "'>";
    echo "</div>";
    echo "<div class='col-md-6 d-flex justify-content-center justify-content-md-start mt-3 mt-md-0'>";
    echo "<a href='" . $download_csv_url . "' class='btn btn-success mr-2'><i class='fa fa-download'></i> " . s(get_string('download_csv', 'block_tmms_24')) . "</a> ";
    echo "<a href='" . $download_json_url . "' class='btn btn-primary'><i class='fa fa-download'></i> " . s(get_string('download_json', 'block_tmms_24')) . "</a>";
    echo "</div>";
    echo "</div>";

    // Combine completed and in-progress results for the table.
    $all_table_results = !empty($all_filtered_results)
        ? $all_filtered_results
        : array_merge($results_completed, $results_in_progress);

    // Prefetch users in one query to avoid N+1.
    $userids = array();
    foreach ($all_table_results as $result) {
        $userids[] = (int)$result->user;
    }
    $userids = array_values(array_unique($userids));
    $users_by_id = !empty($userids)
        ? $DB->get_records_list('user', 'id', $userids, '', 'id, firstname, lastname, picture, imagealt, firstnamephonetic, lastnamephonetic, middlename, alternatename, email')
        : array();

    if (empty($all_table_results)) {
        echo "<div class='alert alert-info mb-0'>" . get_string('no_results_yet', 'block_tmms_24') . "</div>";
    } else {
        echo "<div class='table-responsive'>";
        echo "<table class='table table-striped table-hover' id='participantsTable'>";
        echo "<thead>";
        echo "<tr>";
        echo "<th>" . get_string('student', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('email') . "</th>";
        echo "<th>" . get_string('status', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('perception', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('comprehension', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('regulation', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('date_last_action', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('download', 'block_tmms_24') . "</th>";
        echo "<th>" . get_string('actions', 'block_tmms_24') . "</th>";
        echo "</tr>";
        echo "</thead>";
        echo "<tbody>";

        foreach ($all_table_results as $result) {
            $user = isset($users_by_id[$result->user]) ? $users_by_id[$result->user] : null;
            if (!$user) {
                continue;
            }

            $is_completed = ((int)$result->is_completed === 1);

            // Count answered questions.
            $answered_count = 0;
            for ($i = 1; $i <= 24; $i++) {
                $item = 'item' . $i;
                if (isset($result->$item) && $result->$item !== null) {
                    $answered_count++;
                }
            }

            if ($is_completed) {
                $status_badge = "<span class='badge bg-success text-white'><i class='fa fa-check'></i> " . get_string('completed', 'block_tmms_24') . "</span>";
            } else {
                $status_badge = "<span class='badge bg-warning text-dark'><i class='fa fa-hourglass-half'></i> " . get_string('in_progress', 'block_tmms_24') . "</span>";
                $status_badge .= "<br><small class='text-muted'>" . $answered_count . "/24</small>";
            }

            $perception_cell = "<span class='text-muted'>-</span>";
            $comprehension_cell = "<span class='text-muted'>-</span>";
            $regulation_cell = "<span class='text-muted'>-</span>";
            if ($is_completed) {
                $responses = array();
                for ($i = 1; $i <= 24; $i++) {
                    $item = 'item' . $i;
                    $responses[] = $result->$item;
                }
                $scores = TMMS24Facade::calculate_scores($responses);
                $perception_cell = '<strong>' . s((string)$scores['percepcion']) . '</strong>';
                $comprehension_cell = '<strong>' . s((string)$scores['comprension']) . '</strong>';
                $regulation_cell = '<strong>' . s((string)$scores['regulacion']) . '</strong>';
            }

            $date_cell = '-';
            if ($is_completed) {
                $date_cell = userdate($result->created_at, get_string('strftimedatetimeshort'));
            } else if (!empty($result->updated_at)) {
                $date_cell = userdate($result->updated_at, get_string('strftimedatetimeshort'));
            }

            $usercell = "<div class='d-flex align-items-center'>";
            $usercell .= $OUTPUT->user_picture($user, array('size' => 35, 'courseid' => $courseid));
            $usercell .= "<span class='ms-2'><strong>" . fullname($user) . "</strong></span>";
            $usercell .= "</div>";

            $viewresultsurl = new moodle_url('/blocks/tmms_24/student_results.php', array(
                'courseid' => $courseid,
                'userid' => $result->user,
            ));
            $viewbutton = "<a href='" . $viewresultsurl . "' class='btn btn-sm tmms-view-results mr-2 mt-1 mb-1' title='" . s(get_string('view_results', 'block_tmms_24')) . "'>";
            $viewbutton .= "<i class='fa fa-eye'></i> " . get_string('view_results', 'block_tmms_24');
            $viewbutton .= "</a>";

            $deletebutton = '';
            if (has_capability('moodle/course:manageactivities', $context)) {
                $deleteurl = new moodle_url('/blocks/tmms_24/teacher_view.php', array(
                    'courseid' => $courseid,
                    'action' => 'delete',
                    'id' => $result->id,
                    'sesskey' => sesskey(),
                ));
                $deletebutton = "<a href='" . $deleteurl . "' class='btn btn-sm btn-danger mr-2 mt-1 mb-1' title='" . s(get_string('delete_response', 'block_tmms_24')) . "'>";
                $deletebutton .= "<i class='fa fa-trash'></i> " . get_string('delete');
                $deletebutton .= "</a>";
            }

            $downloadbuttons = '';
            if ($is_completed) {
                $downloadcsvurl = new moodle_url('/blocks/tmms_24/export.php', array(
                    'cid' => $courseid,
                    'userid' => $result->user,
                    'format' => 'csv',
                ));
                $downloadjsonurl = new moodle_url('/blocks/tmms_24/export.php', array(
                    'cid' => $courseid,
                    'userid' => $result->user,
                    'format' => 'json',
                ));
                $downloadbuttons .= "<a href='" . $downloadcsvurl . "' class='btn btn-sm btn-success mr-2 mt-1 mb-1' title='" . s(get_string('download_csv', 'block_tmms_24')) . "'><i class='fa fa-file-excel-o'></i> CSV</a>";
                $downloadbuttons .= "<a href='" . $downloadjsonurl . "' class='btn btn-sm btn-primary mt-1 mb-1' title='" . s(get_string('download_json', 'block_tmms_24')) . "'><i class='fa fa-file-code-o'></i> JSON</a>";
            } else {
                $downloadbuttons .= "<button class='btn btn-sm btn-secondary mr-2 mt-1 mb-1' disabled title='" . s(get_string('test_not_completed_yet', 'block_tmms_24')) . "'><i class='fa fa-file-excel-o'></i> CSV</button>";
                $downloadbuttons .= "<button class='btn btn-sm btn-secondary mt-1 mb-1' disabled title='" . s(get_string('test_not_completed_yet', 'block_tmms_24')) . "'><i class='fa fa-file-code-o'></i> JSON</button>";
            }

            echo "<tr class='participant-row'>";
            echo "<td>" . $usercell . "</td>";
            echo "<td>" . s($user->email) . "</td>";
            echo "<td>" . $status_badge . "</td>";
            echo "<td>" . $perception_cell . "</td>";
            echo "<td>" . $comprehension_cell . "</td>";
            echo "<td>" . $regulation_cell . "</td>";
            echo "<td>" . s($date_cell) . "</td>";
            echo "<td>" . $downloadbuttons . "</td>";
            echo "<td>" . $viewbutton . $deletebutton . "</td>";
            echo "</tr>";
        }

        echo "</tbody>";
        echo "</table>";
        echo "</div>";
    }

    echo "</div>"; // card-body
    echo "</div>"; // card

    echo "<script>
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('searchInput');
  function filterTable() {
    const filter = (searchInput && searchInput.value ? searchInput.value : '').toLowerCase();
    const rows = document.querySelectorAll('#participantsTable .participant-row');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(filter) ? '' : 'none';
    });
  }
  if (searchInput) {
    searchInput.addEventListener('input', filterTable);
  }
});
</script>";
} else {
        echo "<div class='alert alert-info mt-4'>";
        echo "<i class='fa fa-info-circle'></i> ";
        echo "<h5>" . get_string('no_results_yet', 'block_tmms_24') . "</h5>";
        echo "<p>" . get_string('no_participants_message', 'block_tmms_24') . "</p>";
        echo "</div>";
}

// Botón para regresar al curso
echo "<div class='mt-4 text-center'>";
echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>";
echo "<i class='fa fa-arrow-left'></i> " . get_string('back_to_course', 'block_tmms_24');
echo "</a>";
echo "</div>";

// Cerrar contenedor aislado
echo "</div>";

echo $OUTPUT->footer();
