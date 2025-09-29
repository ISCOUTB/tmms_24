<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Fachada para la lógica de negocio del test TMMS-24
class TMMS24Facade {
    
    // Obtiene la interpretación de una puntuación según el baremo
    public static function get_interpretation($dimension, $score, $gender) {
        switch ($dimension) {
            case 'percepcion':
                if ($gender === 'M') {
                    if ($score < 21) return get_string('perception_needs_improvement_low', 'block_tmms_24');
                    if ($score >= 22 && $score <= 32) return get_string('adequate', 'block_tmms_24');
                    return get_string('perception_needs_improvement_high', 'block_tmms_24');
                } else {
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
                } else {
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
                } else {
                    if ($score < 23) return get_string('needs_improvement', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('adequate', 'block_tmms_24');
                    return get_string('excellent', 'block_tmms_24');
                }
                break;
        }
        
        return get_string('not_determined', 'block_tmms_24');
    }
    
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
    
    public static function get_all_interpretations($scores, $gender) {
        return [
            'percepcion' => self::get_interpretation('percepcion', $scores['percepcion'], $gender),
            'comprension' => self::get_interpretation('comprension', $scores['comprension'], $gender),
            'regulacion' => self::get_interpretation('regulacion', $scores['regulacion'], $gender)
        ];
    }
    
    public static function get_gender_label($gender) {
        if (!isset($gender) || empty($gender)) {
            return get_string('not_determined', 'block_tmms_24');
        }
        
        switch ($gender) {
            case 'M':
                return get_string('gender_male', 'block_tmms_24');
            case 'F':
                return get_string('gender_female', 'block_tmms_24');
            default:
                return get_string('gender_prefer_not_say', 'block_tmms_24');
        }
    }
    
    public static function get_tmms24_items() {
        $items = [];
        for ($i = 1; $i <= 24; $i++) {
            $item_key = 'item' . $i;
            // Validar que el item_key no esté vacío
            if (empty($item_key) || strlen($item_key) < 5) {
                $items[$i] = "Ítem $i (error en identificador)";
                continue;
            }
            try {
                if (get_string_manager()->string_exists($item_key, 'block_tmms_24')) {
                    $items[$i] = get_string($item_key, 'block_tmms_24');
                } else {
                    $items[$i] = "Ítem $i (cadena no encontrada)";
                }
            } catch (Exception $e) {
                $items[$i] = "Ítem $i (error: " . $e->getMessage() . ")";
            }
        }
        return $items;
    }
}

class block_tmms_24 extends block_base {
    
    function init() {
        $this->title = get_string('pluginname', 'block_tmms_24');
    }
    
    function get_content() {
        global $USER, $DB, $COURSE;
        
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
        
        if (has_capability('moodle/course:manageactivities', $context)) {
            $this->content->text = $this->get_management_summary();
        } else {
            global $DB;
            $entry = $DB->get_record('tmms_24', [
                'user' => $USER->id,
                'course' => $COURSE->id
            ]);
            
            if ($entry && isset($entry->item1) && $entry->item1 > 0) {
                // Show enhanced results directly in the block
                $this->content->text = '<div id="tmms-results-container">' . 
                                      $this->get_student_results($entry) . 
                                      '</div>';
            } else {
                // Show enhanced test invitation
                $this->content->text = '<div id="tmms-invitation-container">' . 
                                      $this->get_test_invitation() . 
                                      '</div>';
            }
        }
        
        return $this->content;
    }
    
    function has_config() {
        return false;
    }
    
    private function get_student_results($entry) {
        global $COURSE, $USER;
        
        $output = '';
        
        // Build responses array from individual item fields in the database
        $responsesArray = [];
        for ($i = 1; $i <= 24; $i++) {
            $field_name = 'item' . $i;
            if (isset($entry->{$field_name}) && $entry->{$field_name} > 0) {
                $responsesArray[] = (int)$entry->{$field_name};
            }
        }
        
        // If we don't have all 24 responses, show error
        if (count($responsesArray) < 24) {
            return '<div class="alert alert-warning">' . get_string('incomplete_data', 'block_tmms_24') . '</div>';
        }
        
        $scores = TMMS24Facade::calculate_scores($responsesArray);
        
        if (!isset($entry->gender) || empty($entry->gender)) {
            $entry->gender = 'M'; // Default value
        }
        
        $interpretations = TMMS24Facade::get_all_interpretations($scores, $entry->gender);
        
        // Completion date
        $completion_date = '';
        if (isset($entry->updated_at) && $entry->updated_at > 0) {
            $completion_date = userdate($entry->updated_at, get_string('strftimedatefullshort', 'core'));
        } else if (isset($entry->created_at) && $entry->created_at > 0) {
            $completion_date = userdate($entry->created_at, get_string('strftimedatefullshort', 'core'));
        } else {
            $completion_date = get_string('date_not_available', 'block_tmms_24');
        }
        
        $output .= '<div class="tmms-results-block">';
        
        // Header with success icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-brain text-success" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('test_completed', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . $completion_date . '</small>';
        $output .= '</div>';
        
        // Your emotional intelligence
        $output .= '<div class="tmms-top-section mb-3">';
        $output .= '<h6 class="mb-2">' . get_string('your_emotional_intelligence', 'block_tmms_24') . '</h6>';
        
        // Dimension names mapping
        $dimension_names = [
            'percepcion' => get_string('perception', 'block_tmms_24'),
            'comprension' => get_string('comprehension', 'block_tmms_24'),
            'regulacion' => get_string('regulation', 'block_tmms_24')
        ];
        
        // Find top dimension
        $max_dimension = array_search(max($scores), $scores);
        $max_score = $scores[$max_dimension];
        
        $output .= '<div class="d-flex justify-content-between align-items-center">';
        $output .= '<span><i class="fa fa-star text-warning"></i> <strong>' . get_string('your_top_dimension', 'block_tmms_24') . '</strong></span>';
        $output .= '</div>';
        $output .= '<div class="text-center mt-1">';
        $output .= '<div class="text-primary font-weight-bold">' . $dimension_names[$max_dimension] . '</div>';
        $output .= '<div class="small text-primary">' . $max_score . '/40 ' . get_string('points', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        
        // Other dimensions summary
        $output .= '<h6 class="small mb-2">' . get_string('other_dimensions', 'block_tmms_24') . '</h6>';
        foreach ($scores as $dimension => $score) {
            if ($dimension !== $max_dimension) {
                $interpretation = $interpretations[$dimension];
                $badge_class = $this->get_score_badge_class($score);
                
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<span class="small">' . $dimension_names[$dimension] . '</span>';
                $output .= '<div>';
                $output .= '<span class="small text-muted mr-1">' . $score . '/40</span>';
                $output .= '<span class="badge ' . $badge_class . ' small">' . $interpretation . '</span>';
                $output .= '</div>';
                $output .= '</div>';
            }
        }
        $output .= '</div>';
        
        // View detailed results button
        $url = new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id, 'uid' => $USER->id]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-outline-primary btn-sm">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_detailed_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function get_test_invitation() {
        global $COURSE;
        
        $output = '';
        $output .= '<div class="tmms-invitation-block">';
        
        // Header with brain icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-brain text-primary" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('emotional_intelligence_test', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">TMMS-24</small>';
        $output .= '</div>';
        
        // Test description
        $output .= '<div class="test-description mb-3">';
        $output .= '<p class="small mb-2">' . get_string('test_description_short', 'block_tmms_24') . '</p>';
        
        $output .= '<div class="test-features">';
        $output .= '<div class="feature-item d-flex align-items-center mb-1">';
        $output .= '<i class="fa fa-clock text-info mr-2"></i>';
        $output .= '<span class="small">' . get_string('duration_5_minutes', 'block_tmms_24') . '</span>';
        $output .= '</div>';
        
        $output .= '<div class="feature-item d-flex align-items-center mb-1">';
        $output .= '<i class="fa fa-list-ol text-info mr-2"></i>';
        try {
            $questions_text = get_string('24_questions', 'block_tmms_24');
            $output .= '<span class="small">' . $questions_text . '</span>';
        } catch (Exception $e) {
            $output .= '<span class="small">24 preguntas (fallback)</span>';
        }
        $output .= '</div>';
        
        $output .= '<div class="feature-item d-flex align-items-center mb-1">';
        $output .= '<i class="fa fa-chart-pie text-info mr-2"></i>';
        try {
            $dimensions_text = get_string('3_dimensions', 'block_tmms_24');
            $output .= '<span class="small">' . $dimensions_text . '</span>';
        } catch (Exception $e) {
            $output .= '<span class="small">3 dimensiones evaluadas (fallback)</span>';
        }
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // What you will discover
        $output .= '<div class="discover-section mb-3">';
        $output .= '<h6 class="small mb-2">' . get_string('what_you_will_discover', 'block_tmms_24') . '</h6>';
        $output .= '<ul class="list-unstyled small">';
        $output .= '<li><i class="fa fa-eye text-success mr-1"></i> ' . get_string('perception_ability', 'block_tmms_24') . '</li>';
        $output .= '<li><i class="fa fa-lightbulb text-warning mr-1"></i> ' . get_string('comprehension_ability', 'block_tmms_24') . '</li>';
        $output .= '<li><i class="fa fa-balance-scale text-info mr-1"></i> ' . get_string('regulation_ability', 'block_tmms_24') . '</li>';
        $output .= '</ul>';
        $output .= '</div>';
        
        // Action button
        $output .= '<div class="tmms-actions text-center">';
        $url = new moodle_url('/blocks/tmms_24/view.php', array('cid' => $COURSE->id));
        $output .= '<a href="' . $url . '" class="btn btn-primary btn-block">';
        $output .= '<i class="fa fa-play"></i> ' . get_string('start_test', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function get_management_summary() {
        global $DB, $COURSE;
        
        $output = '';
        $output .= '<div class="tmms-management-block">';
        
        // Header with brain icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-brain text-success" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('management_title', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('course_overview', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Get course statistics
        $context = context_course::instance($COURSE->id);
        $total_enrolled = count_enrolled_users($context, 'block/tmms_24:taketest');
        $total_completed = $DB->count_records('tmms_24', ['course' => $COURSE->id]);
        $completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;
        
        // Statistics row
        $output .= '<div class="row text-center mb-3">';
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-number text-success">' . number_format($completion_rate, 1) . '%</div>';
        $output .= '<div class="stat-label">' . get_string('completion_rate', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-number text-primary">' . $total_completed . '</div>';
        $output .= '<div class="stat-label">' . get_string('completed', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-number text-warning">' . ($total_enrolled - $total_completed) . '</div>';
        $output .= '<div class="stat-label">' . get_string('pending', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Progress bar
        $output .= '<div class="progress mb-2" style="height: 8px;">';
        $output .= '<div class="progress-bar bg-success" style="width: ' . ($completion_rate) . '%"></div>';
        $output .= '</div>';
        $output .= '<small class="text-muted">' . $total_completed . ' ' . get_string('of', 'block_tmms_24') . ' ' . $total_enrolled . ' ' . get_string('students_completed', 'block_tmms_24') . '</small>';
        
        // Recent completions
        $recent_completions = $DB->get_records_sql("
            SELECT u.firstname, u.lastname, tr.created_at 
            FROM {tmms_24} tr 
            JOIN {user} u ON tr.user = u.id 
            WHERE tr.course = ? 
            ORDER BY tr.created_at DESC 
            LIMIT 3", [$COURSE->id]);
        
        if ($recent_completions) {
            $output .= '<div class="recent-completions mt-3">';
            $output .= '<h6 class="small mb-2">' . get_string('recent_completions', 'block_tmms_24') . '</h6>';
            foreach ($recent_completions as $completion) {
                $completion_date = $completion->created_at ? $completion->created_at : time();
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<span class="small">' . $completion->firstname . ' ' . $completion->lastname . '</span>';
                $output .= '<span class="badge badge-success small">' . userdate($completion_date, '%d/%m') . '</span>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }
        
        // Management actions
        $url = new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id, 'action' => 'dashboard']);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-outline-primary btn-sm">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_all_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function get_score_badge_class($score) {
        if ($score >= 30) {
            return 'badge-success';
        } elseif ($score >= 24) {
            return 'badge-primary';
        } else {
            return 'badge-warning';
        }
    }
}