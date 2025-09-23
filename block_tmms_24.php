<?php

// Solo incluir config si no está ya incluido
if (!defined('MOODLE_INTERNAL')) {
    require_once(__DIR__ . '/../../config.php');
}

// Incluir la clase base de bloques si no está ya incluida
if (!class_exists('block_base')) {
    require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
}

// Fachada para la lógica de negocio del test TMMS-24
class TMMS24Facade {
    
    public static function calculate_scores($responses) {
        $percepcion = array_sum(array_slice($responses, 0, 8));
        $comprension = array_sum(array_slice($responses, 8, 8));
        $regulacion = array_sum(array_slice($responses, 16, 8));
        
        return [
            'percepcion' => $percepcion,
            'comprension' => $comprension,
            'regulacion' => $regulacion
        ];
    }
    
    public static function interpret_score($dimension, $score, $gender) {
        switch ($dimension) {
            case 'percepcion':
                if ($gender === 'M') {
                    if ($score < 21) return get_string('perception_needs_improvement_low', 'block_tmms_24');
                    if ($score >= 22 && $score <= 32) return get_string('adequate', 'block_tmms_24');
                    return get_string('perception_needs_improvement_high', 'block_tmms_24');
                } else { // Mujer
                    if ($score < 24) return get_string('perception_needs_improvement_low', 'block_tmms_24');
                    if ($score >= 25 && $score <= 35) return get_string('adequate', 'block_tmms_24');
                    return get_string('perception_needs_improvement_high', 'block_tmms_24');
                }
                break;
                
            case 'comprension':
                if ($gender === 'M') {
                    if ($score < 25) return get_string('needs_improvement', 'block_tmms_24');
                    if ($score >= 26 && $score <= 35) return get_string('adequate', 'block_tmms_24');
                    return get_string('excellent', 'block_tmms_24');
                } else { // Mujer
                    if ($score < 23) return get_string('needs_improvement', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('adequate', 'block_tmms_24');
                    return get_string('excellent', 'block_tmms_24');
                }
                break;
                
            case 'regulacion':
                if ($gender === 'M') {
                    if ($score < 23) return get_string('needs_improvement', 'block_tmms_24');
                    if ($score >= 24 && $score <= 35) return get_string('adequate', 'block_tmms_24');
                    return get_string('excellent', 'block_tmms_24');
                } else { // Mujer
                    if ($score < 23) return get_string('needs_improvement', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('adequate', 'block_tmms_24');
                    return get_string('excellent', 'block_tmms_24');
                }
                break;
        }
        return get_string('not_determined', 'block_tmms_24');
    }
    
    public static function get_all_interpretations($scores, $gender) {
        $interpretations = [];
        
        if ($gender === 'prefiero_no_decir') {
            // Mostrar ambas interpretaciones
            foreach (['M', 'F'] as $g) {
                $gender_label = ($g === 'M') ? 'Hombre' : 'Mujer';
                $interpretations[$gender_label] = [
                    'percepcion' => self::interpret_score('percepcion', $scores['percepcion'], $g),
                    'comprension' => self::interpret_score('comprension', $scores['comprension'], $g),
                    'regulacion' => self::interpret_score('regulacion', $scores['regulacion'], $g)
                ];
            }
        } else {
            $interpretations['result'] = [
                'percepcion' => self::interpret_score('percepcion', $scores['percepcion'], $gender),
                'comprension' => self::interpret_score('comprension', $scores['comprension'], $gender),
                'regulacion' => self::interpret_score('regulacion', $scores['regulacion'], $gender)
            ];
        }
        
        return $interpretations;
    }
    
    public static function get_tmms24_items() {
        $items = [];
        for ($i = 1; $i <= 24; $i++) {
            $items[$i] = get_string('item' . $i, 'block_tmms_24');
        }
        return $items;
    }
}

class block_tmms_24 extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_tmms_24');
    }

    public function get_content() {
        global $USER, $COURSE, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin()) {
            $this->content->text = get_string('not_logged_in', 'block_tmms_24');
            return $this->content;
        }

        $context = context_course::instance($COURSE->id);

        if (has_capability('block/tmms_24:viewallresults', $context)) {
            // Teacher/Admin view: enhanced management interface
            ob_start();
            $this->show_management_summary();
            $this->content->text = ob_get_clean();
        } else {
            // Student view: check if test is completed
            $entry = $DB->get_record('tmms_24', ['user' => $USER->id, 'course' => $COURSE->id]);

            if ($entry) {
                // Show enhanced results directly in the block
                ob_start();
                $this->show_student_results($entry);
                $this->content->text = ob_get_clean();
            } else {
                // Show enhanced test invitation
                ob_start();
                $this->show_test_invitation();
                $this->content->text = ob_get_clean();
            }
        }

        return $this->content;
    }

    public function instance_allow_multiple() {
        return false;
    }

    public function has_config() {
        return false;
    }

    public function instance_allow_config() {
        return false;
    }
    
    private function show_student_results($entry) {
        global $COURSE;
        
        // Parse responses and calculate scores
        $responses = json_decode($entry->responses, true);
        $scores = TMMS24Facade::calculate_scores($responses);
        $interpretations = TMMS24Facade::get_all_interpretations($scores, $entry->gender);
        
        // Completion date
        $completion_date = '';
        if (isset($entry->timemodified) && $entry->timemodified > 0) {
            $completion_date = userdate($entry->timemodified, get_string('strftimedatefullshort'));
        } else {
            $completion_date = get_string('date_not_available', 'block_tmms_24');
        }
        
        echo '<div class="tmms-results-block">';
        
        // Header with success icon
        echo '<div class="tmms-header text-center mb-3">';
        echo '<i class="fa fa-brain text-success" style="font-size: 1.5em;"></i>';
        echo '<h6 class="mt-2 mb-1">' . get_string('test_completed', 'block_tmms_24') . '</h6>';
        echo '<small class="text-muted">' . $completion_date . '</small>';
        echo '</div>';
        
        // Emotional Intelligence Overview
        echo '<div class="tmms-overview mb-3">';
        echo '<h6 class="mb-2">' . get_string('your_emotional_intelligence', 'block_tmms_24') . '</h6>';
        
        // Get the main interpretation (result)
        $main_interpretations = isset($interpretations['result']) ? $interpretations['result'] : array_values($interpretations)[0];
        
        // Top dimension
        $max_score = max($scores);
        $top_dimension = array_search($max_score, $scores);
        $dimension_names = [
            'percepcion' => get_string('perception', 'block_tmms_24'),
            'comprension' => get_string('comprehension', 'block_tmms_24'),
            'regulacion' => get_string('regulation', 'block_tmms_24')
        ];
        
        echo '<div class="top-dimension mb-2">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<span><i class="fa fa-star text-warning"></i> <strong>' . get_string('your_top_dimension', 'block_tmms_24') . '</strong></span>';
        echo '<span class="badge badge-primary">' . $dimension_names[$top_dimension] . '</span>';
        echo '</div>';
        echo '<div class="small text-muted">' . $main_interpretations[$top_dimension] . '</div>';
        echo '<div class="small text-primary">' . $max_score . '/40 ' . get_string('points', 'block_tmms_24') . '</div>';
        echo '</div>';
        
        // Other dimensions summary
        echo '<div class="other-dimensions">';
        echo '<h6 class="small mb-2">' . get_string('other_dimensions', 'block_tmms_24') . '</h6>';
        
        $counter = 1;
        foreach ($scores as $dimension => $score) {
            if ($dimension !== $top_dimension) {
                $interpretation = $main_interpretations[$dimension];
                $badge_class = $this->get_interpretation_badge_class($interpretation);
                
                echo '<div class="d-flex justify-content-between align-items-center mb-1">';
                echo '<span class="small">' . $counter . '. ' . $dimension_names[$dimension] . '</span>';
                echo '<span class="small">' . $score . '/40</span>';
                echo '</div>';
                $counter++;
            }
        }
        echo '</div>';
        echo '</div>';
        
        // Action buttons
        echo '<div class="tmms-actions text-center">';
        $url = new moodle_url('/blocks/tmms_24/view.php', array(
            'cid' => $COURSE->id,
            'view_results' => 1
        ));
        echo '<a href="' . $url . '" class="btn btn-outline-primary btn-sm btn-block mb-2">';
        echo '<i class="fa fa-chart-bar"></i> ' . get_string('view_detailed_results', 'block_tmms_24');
        echo '</a>';
        
        $retake_url = new moodle_url('/blocks/tmms_24/view.php', array('cid' => $COURSE->id));
        echo '<a href="' . $retake_url . '" class="btn btn-link btn-sm">';
        echo '<i class="fa fa-redo"></i> ' . get_string('retake_test', 'block_tmms_24');
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
        
        // Add custom CSS
        echo '<style>
        .tmms-results-block {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e8f4fd 100%);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .tmms-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        .top-dimension {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #007bff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .other-dimensions {
            background: rgba(255,255,255,0.7);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
        }
        </style>';
    }
    
    private function show_test_invitation() {
        global $COURSE;
        
        echo '<div class="tmms-invitation-block">';
        
        // Header with brain icon
        echo '<div class="tmms-header text-center mb-3">';
        echo '<i class="fa fa-brain text-primary" style="font-size: 1.5em;"></i>';
        echo '<h6 class="mt-2 mb-1">' . get_string('emotional_intelligence_test', 'block_tmms_24') . '</h6>';
        echo '<small class="text-muted">TMMS-24</small>';
        echo '</div>';
        
        // Test description
        echo '<div class="test-description mb-3">';
        echo '<p class="small mb-2">' . get_string('test_description_short', 'block_tmms_24') . '</p>';
        
        echo '<div class="test-features">';
        echo '<div class="feature-item d-flex align-items-center mb-1">';
        echo '<i class="fa fa-clock text-info mr-2"></i>';
        echo '<span class="small">' . get_string('duration_5_minutes', 'block_tmms_24') . '</span>';
        echo '</div>';
        
        echo '<div class="feature-item d-flex align-items-center mb-1">';
        echo '<i class="fa fa-list-ol text-info mr-2"></i>';
        echo '<span class="small">' . get_string('24_questions', 'block_tmms_24') . '</span>';
        echo '</div>';
        
        echo '<div class="feature-item d-flex align-items-center mb-1">';
        echo '<i class="fa fa-chart-pie text-info mr-2"></i>';
        echo '<span class="small">' . get_string('3_dimensions', 'block_tmms_24') . '</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // What you will discover
        echo '<div class="discover-section mb-3">';
        echo '<h6 class="small mb-2">' . get_string('what_you_will_discover', 'block_tmms_24') . '</h6>';
        echo '<ul class="list-unstyled small">';
        echo '<li><i class="fa fa-eye text-success mr-1"></i> ' . get_string('perception_ability', 'block_tmms_24') . '</li>';
        echo '<li><i class="fa fa-lightbulb text-warning mr-1"></i> ' . get_string('comprehension_ability', 'block_tmms_24') . '</li>';
        echo '<li><i class="fa fa-balance-scale text-info mr-1"></i> ' . get_string('regulation_ability', 'block_tmms_24') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        // Action button
        echo '<div class="tmms-actions text-center">';
        $url = new moodle_url('/blocks/tmms_24/view.php', array('cid' => $COURSE->id));
        echo '<a href="' . $url . '" class="btn btn-primary btn-block">';
        echo '<i class="fa fa-play"></i> ' . get_string('start_test', 'block_tmms_24');
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
        
        // Add custom CSS
        echo '<style>
        .tmms-invitation-block {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #fff3cd 100%);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .test-features {
            background: rgba(255,255,255,0.8);
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .discover-section {
            background: rgba(255,255,255,0.7);
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
        }
        </style>';
    }
    
    private function show_management_summary() {
        global $COURSE, $DB;
        
        // Get statistics
        $context = context_course::instance($COURSE->id);
        $enrolled_students = get_enrolled_users($context, 'block/tmms_24:taketest');
        $total_enrolled = count($enrolled_students);
        
        $responses = $DB->get_records('tmms_24', array('course' => $COURSE->id));
        $total_completed = count($responses);
        $total_pending = $total_enrolled - $total_completed;
        
        $completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;
        
        echo '<div class="tmms-management-block">';
        
        // Header
        echo '<div class="tmms-header text-center mb-3">';
        echo '<i class="fa fa-brain text-success" style="font-size: 1.5em;"></i>';
        echo '<h6 class="mt-2 mb-1">' . get_string('management_title', 'block_tmms_24') . '</h6>';
        echo '<small class="text-muted">' . get_string('course_overview', 'block_tmms_24') . '</small>';
        echo '</div>';
        
        // Quick stats
        echo '<div class="tmms-stats mb-3">';
        echo '<div class="row text-center">';
        
        // Completion rate
        echo '<div class="col-4">';
        echo '<div class="stat-card">';
        echo '<div class="stat-number text-success">' . number_format($completion_rate, 1) . '%</div>';
        echo '<div class="stat-label">' . get_string('completion_rate', 'block_tmms_24') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Completed tests
        echo '<div class="col-4">';
        echo '<div class="stat-card">';
        echo '<div class="stat-number text-primary">' . $total_completed . '</div>';
        echo '<div class="stat-label">' . get_string('completed', 'block_tmms_24') . '</div>';
        echo '</div>';
        echo '</div>';
        
        // Pending
        echo '<div class="col-4">';
        echo '<div class="stat-card">';
        echo '<div class="stat-number text-warning">' . $total_pending . '</div>';
        echo '<div class="stat-label">' . get_string('pending', 'block_tmms_24') . '</div>';
        echo '</div>';
        echo '</div>';
        
        echo '</div>';
        echo '</div>';
        
        // Progress bar
        echo '<div class="tmms-progress-overview mb-3">';
        echo '<div class="progress" style="height: 10px;">';
        echo '<div class="progress-bar bg-success" style="width: ' . ($completion_rate) . '%"></div>';
        echo '</div>';
        echo '<small class="text-muted">' . $total_completed . ' ' . get_string('of', 'block_tmms_24') . ' ' . $total_enrolled . ' ' . get_string('students_completed', 'block_tmms_24') . '</small>';
        echo '</div>';
        
        // Recent activity (if any)
        if ($total_completed > 0) {
            $recent_responses = $DB->get_records('tmms_24', 
                array('course' => $COURSE->id), 
                'timemodified DESC', '*', 0, 3);
                
            echo '<div class="tmms-recent mb-3">';
            echo '<h6 class="mb-2">' . get_string('recent_completions', 'block_tmms_24') . '</h6>';
            foreach ($recent_responses as $response) {
                $user = $DB->get_record('user', array('id' => $response->user));
                echo '<div class="d-flex justify-content-between align-items-center mb-1">';
                echo '<span class="small">' . fullname($user) . '</span>';
                echo '<span class="badge badge-success small">' . userdate($response->timemodified, '%d/%m') . '</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        
        // Action buttons
        echo '<div class="tmms-actions text-center">';
        $url = new moodle_url('/blocks/tmms_24/teacher_view.php', array('courseid' => $COURSE->id));
        echo '<a href="' . $url . '" class="btn btn-primary btn-sm btn-block">';
        echo '<i class="fa fa-chart-bar"></i> ' . get_string('view_all_results', 'block_tmms_24');
        echo '</a>';
        echo '</div>';
        
        echo '</div>';
        
        // Add custom CSS
        echo '<style>
        .tmms-management-block {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e3f2fd 100%);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .stat-card {
            padding: 8px;
            background: white;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 1.2em;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.75em;
            color: #6c757d;
        }
        .tmms-recent {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
        }
        </style>';
    }
    
    private function get_interpretation_badge_class($interpretation) {
        if (strpos(strtolower($interpretation), 'excelente') !== false) {
            return 'badge-success';
        } else if (strpos(strtolower($interpretation), 'adecuad') !== false) {
            return 'badge-primary';
        } else {
            return 'badge-warning';
        }
    }
}