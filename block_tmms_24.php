<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Fachada para la lógica de negocio del test TMMS-24
class TMMS24Facade {
    
    // Obtiene la interpretación de una puntuación según el baremo actualizado
    public static function get_interpretation($dimension, $score, $gender) {
        switch ($dimension) {
            case 'percepcion':
                if ($gender === 'M') {
                    if ($score < 21) return get_string('perception_difficulty_feeling', 'block_tmms_24');
                    if ($score >= 22 && $score <= 32) return get_string('perception_adequate_feeling', 'block_tmms_24');
                    if ($score >= 33) return get_string('perception_excessive_attention', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score < 24) return get_string('perception_difficulty_feeling', 'block_tmms_24');
                    if ($score >= 25 && $score <= 35) return get_string('perception_adequate_feeling', 'block_tmms_24');
                    if ($score >= 36) return get_string('perception_excessive_attention', 'block_tmms_24');
                }
                break;
                
            case 'comprension':
                if ($gender === 'M') {
                    if ($score < 25) return get_string('comprehension_difficulty_understanding', 'block_tmms_24');
                    if ($score >= 26 && $score <= 35) return get_string('comprehension_adequate_with_difficulties', 'block_tmms_24');
                    if ($score >= 36) return get_string('comprehension_great_clarity', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score < 23) return get_string('comprehension_difficulty_understanding', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('comprehension_adequate_with_difficulties', 'block_tmms_24');
                    if ($score >= 35) return get_string('comprehension_great_clarity', 'block_tmms_24');
                }
                break;
                
            case 'regulacion':
                if ($gender === 'M') {
                    if ($score < 23) return get_string('regulation_difficulty_managing', 'block_tmms_24');
                    if ($score >= 24 && $score <= 35) return get_string('regulation_adequate_balance', 'block_tmms_24');
                    if ($score >= 36) return get_string('regulation_great_capacity', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score < 23) return get_string('regulation_difficulty_managing', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('regulation_adequate_balance', 'block_tmms_24');
                    if ($score >= 35) return get_string('regulation_great_capacity', 'block_tmms_24');
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
            // Verificar que el item_key no esté vacío y que exista la cadena
            if (!empty($item_key) && get_string_manager()->string_exists($item_key, 'block_tmms_24')) {
                $items[$i] = get_string($item_key, 'block_tmms_24');
            } else {
                // Fallback en caso de que no exista la cadena
                $items[$i] = get_string('item_not_found', 'block_tmms_24', $i);
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
        
        if (has_capability('block/tmms_24:viewallresults', $context)) {
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
        
        $output .= '<div class="tmms-results-block">';
        
        // Header with success icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-check-circle text-success" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('test_completed', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('emotional_intelligence_results', 'block_tmms_24') . '</small>';
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
        
        // Top dimension card
        $output .= '<div class="card border-primary mb-3" style="border-left: 4px solid #007bff !important;">';
        $output .= '<div class="card-body p-3">';
        $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $output .= '<div>';
        $output .= '<strong><i class="fa fa-star text-warning"></i> ' . $dimension_names[$max_dimension] . '</strong><br>';
        $output .= '<small class="text-muted">' . $max_score . '/40 ' . get_string('points', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        $output .= '</div>';
        // Add interpretation for top dimension
        $max_interpretation = isset($interpretations[$max_dimension]) ? $interpretations[$max_dimension] : get_string('not_determined', 'block_tmms_24');
        $output .= '<div class="small text-muted mt-2" style="font-style: italic; line-height: 1.3;">' . $max_interpretation . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Other dimensions summary
        $output .= '<div class="tmms-other-dimensions mb-3">';
        $output .= '<h6 class="mb-2">' . get_string('other_dimensions', 'block_tmms_24') . '</h6>';
        foreach ($scores as $dimension => $score) {
            if ($dimension !== $max_dimension) {
                $interpretation = isset($interpretations[$dimension]) ? $interpretations[$dimension] : get_string('not_determined', 'block_tmms_24');
                
                $output .= '<div class="card border-secondary mb-2">';
                $output .= '<div class="card-body p-2">';
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<strong class="small">' . $dimension_names[$dimension] . '</strong>';
                $output .= '<span class="small text-muted">' . $score . '/40</span>';
                $output .= '</div>';
                $output .= '<div class="small text-muted" style="line-height: 1.2;">' . $interpretation . '</div>';
                $output .= '</div>';
                $output .= '</div>';
            }
        }
        $output .= '</div>';
        
        // View detailed results button
        $url = new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id, 'view_results' => 1]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-primary btn-sm btn-block">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_detailed_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';

        $output .= '</div>';
        
        // Add custom CSS in the style of chaside
        $output .= '<style>
        .tmms-results-block {
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .tmms-header i {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .tmms-results-block .card {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .tmms-results-block .card:hover {
            transform: translateY(-2px);
        }
        .tmms-other-dimensions {
            background: white;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
            font-weight: 500;
        }
        .tmms-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        </style>';        return $output;
    }
    
    private function get_test_invitation() {
        global $COURSE, $USER;
        
        $output = '';
        $output .= '<div class="tmms-invitation-block">';
        
        // Header with heart icon (emotional intelligence)
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-smile-o" style="font-size: 2em; color: #e91e63;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('emotional_intelligence_test', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('discover_your_emotional_skills', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Test description card
        $output .= '<div class="tmms-description mb-3">';
        $output .= '<div class="card border-info">';
        $output .= '<div class="card-body p-3">';
        $output .= '<h6 class="card-title">';
        $output .= '<i class="fa fa-info-circle text-info"></i> ';
        $output .= get_string('what_is_tmms24', 'block_tmms_24');
        $output .= '</h6>';
        $output .= '<p class="card-text small mb-2">' . get_string('test_description_short', 'block_tmms_24') . '</p>';
        $output .= '<ul class="list-unstyled small mb-0">';
        $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_24_questions', 'block_tmms_24') . '</li>';
        $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_3_dimensions', 'block_tmms_24') . '</li>';
        $output .= '<li><i class="fa fa-check text-success"></i> ' . get_string('feature_instant_results', 'block_tmms_24') . '</li>';
        $output .= '</ul>';
        $output .= '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Action button - detectar si hay borrador
        $output .= '<div class="tmms-actions text-center">';
        $output .= '<div id="tmms-button-container">';
        $url = new moodle_url('/blocks/tmms_24/view.php', array('cid' => $COURSE->id));
        $output .= '<a href="' . $url . '" class="btn btn-primary btn-block" id="tmms-start-btn" style="background-color: #e91e63; border-color: #e91e63;">';
        $output .= '<i class="fa fa-rocket"></i> <span id="tmms-btn-text">' . get_string('start_test', 'block_tmms_24') . '</span>';
        $output .= '</a>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // JavaScript para detectar borrador y cambiar texto del botón
        $output .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            var draftKey = "tmms24_draft_' . $COURSE->id . '";
            var savedDraft = localStorage.getItem(draftKey);
            
            if (savedDraft) {
                try {
                    var data = JSON.parse(savedDraft);
                    var hasData = Object.keys(data).length > 0;
                    
                    if (hasData) {
                        // Cambiar texto del botón a "Continuar Test"
                        var btnText = document.getElementById("tmms-btn-text");
                        var btnIcon = document.querySelector("#tmms-start-btn i");
                        
                        if (btnText) {
                            btnText.textContent = "' . get_string('continue_test', 'block_tmms_24') . '";
                        }
                        if (btnIcon) {
                            btnIcon.className = "fa fa-play-circle";
                        }
                        
                        // Opcional: cambiar color del botón
                        var btn = document.getElementById("tmms-start-btn");
                        if (btn) {
                            btn.classList.remove("btn-primary");
                            btn.classList.add("btn-success");
                        }
                    }
                } catch(e) {
                    // Si hay error parseando, ignorar
                }
            }
        });
        </script>';
        
        // Add custom CSS for invitation
        $output .= '<style>
        .tmms-invitation-block {
            padding: 15px;
            background: linear-gradient(135deg, #fce4ec 0%, #f8f9fa 100%);
            border-radius: 8px;
            border: 1px solid #f8bbd0;
        }
        .tmms-header i {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .tmms-description .card {
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .tmms-actions .btn-primary,
        #tmms-start-btn {
            background-color: #e91e63;
            border-color: #e91e63;
        }
        .tmms-actions .btn-primary:hover,
        #tmms-start-btn:hover {
            background-color: #d81b60;
            border-color: #c2185b;
        }
        .tmms-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .tmms-actions .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .tmms-actions .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        </style>';
        
        return $output;
    }
    
    private function get_management_summary() {
        global $DB, $COURSE;
        
        $output = '';
        $output .= '<div class="tmms-management-block">';
        
        // Header with icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= '<i class="fa fa-chart-line text-success" style="font-size: 1.5em;"></i>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('management_title', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('course_overview', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Get course statistics
        $context = context_course::instance($COURSE->id);
        $total_enrolled = count_enrolled_users($context, 'block/tmms_24:taketest');
        $total_completed = $DB->count_records('tmms_24', ['course' => $COURSE->id]);
        $completion_rate = $total_enrolled > 0 ? ($total_completed / $total_enrolled) * 100 : 0;
        
        // Quick stats with chaside style
        $output .= '<div class="tmms-stats mb-3">';
        $output .= '<div class="row text-center">';
        
        // Completion rate
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-success">' . number_format($completion_rate, 1) . '%</div>';
        $output .= '<div class="stat-label">' . get_string('completion_rate', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Completed tests
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-primary">' . $total_completed . '</div>';
        $output .= '<div class="stat-label">' . get_string('completed', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // Pending
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-warning">' . ($total_enrolled - $total_completed) . '</div>';
        $output .= '<div class="stat-label">' . get_string('pending', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';
        
        // Progress bar overview
        $output .= '<div class="tmms-progress-overview mb-3">';
        $output .= '<div class="progress" style="height: 10px;">';
        $output .= '<div class="progress-bar bg-success" style="width: ' . ($completion_rate) . '%"></div>';
        $output .= '</div>';
        $output .= '<small class="text-muted">' . $total_completed . ' ' . get_string('of', 'block_tmms_24') . ' ' . $total_enrolled . ' ' . get_string('students_completed', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
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
        $url = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $COURSE->id]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-primary btn-sm btn-block">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_all_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        // Add custom CSS for management view
        $output .= '<style>
        .tmms-management-block {
            padding: 15px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .tmms-header i {
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .stat-card {
            padding: 8px;
            background: white;
            border-radius: 4px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 1.2em;
            font-weight: bold;
        }
        .stat-label {
            font-size: 0.75em;
            color: #6c757d;
        }
        .tmms-progress-overview {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .recent-completions {
            background: white;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .tmms-actions .btn {
            box-shadow: 0 2px 4px rgba(0,123,255,0.2);
            font-weight: 500;
        }
        .tmms-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,123,255,0.3);
        }
        </style>';
        
        return $output;
    }
    
    private function get_score_badge_class($interpretation) {
        // Asignar clases CSS según el tipo de interpretación
        if (strpos($interpretation, get_string('regulation_great_capacity', 'block_tmms_24')) !== false ||
            strpos($interpretation, get_string('comprehension_great_clarity', 'block_tmms_24')) !== false) {
            return 'badge-success'; // Verde para resultados excelentes
        } elseif (strpos($interpretation, get_string('perception_adequate_feeling', 'block_tmms_24')) !== false ||
                  strpos($interpretation, get_string('comprehension_adequate_with_difficulties', 'block_tmms_24')) !== false ||
                  strpos($interpretation, get_string('regulation_adequate_balance', 'block_tmms_24')) !== false) {
            return 'badge-primary'; // Azul para resultados adecuados
        } elseif (strpos($interpretation, get_string('perception_excessive_attention', 'block_tmms_24')) !== false) {
            return 'badge-warning'; // Amarillo para atención excesiva
        } else {
            return 'badge-danger'; // Rojo para dificultades
        }
    }
}
