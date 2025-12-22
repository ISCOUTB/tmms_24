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
                    if ($score <= 21) return get_string('perception_difficulty_feeling', 'block_tmms_24');
                    if ($score >= 22 && $score <= 32) return get_string('perception_adequate_feeling', 'block_tmms_24');
                    if ($score >= 33) return get_string('perception_excessive_attention', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 24) return get_string('perception_difficulty_feeling', 'block_tmms_24');
                    if ($score >= 25 && $score <= 35) return get_string('perception_adequate_feeling', 'block_tmms_24');
                    if ($score >= 36) return get_string('perception_excessive_attention', 'block_tmms_24');
                }
                break;
                
            case 'comprension':
                if ($gender === 'M') {
                    if ($score <= 25) return get_string('comprehension_difficulty_understanding', 'block_tmms_24');
                    if ($score >= 26 && $score <= 35) return get_string('comprehension_adequate_with_difficulties', 'block_tmms_24');
                    if ($score >= 36) return get_string('comprehension_great_clarity', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return get_string('comprehension_difficulty_understanding', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('comprehension_adequate_with_difficulties', 'block_tmms_24');
                    if ($score >= 35) return get_string('comprehension_great_clarity', 'block_tmms_24');
                }
                break;
                
            case 'regulacion':
                if ($gender === 'M') {
                    if ($score <= 23) return get_string('regulation_difficulty_managing', 'block_tmms_24');
                    if ($score >= 24 && $score <= 35) return get_string('regulation_adequate_balance', 'block_tmms_24');
                    if ($score >= 36) return get_string('regulation_great_capacity', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return get_string('regulation_difficulty_managing', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('regulation_adequate_balance', 'block_tmms_24');
                    if ($score >= 35) return get_string('regulation_great_capacity', 'block_tmms_24');
                }
                break;
        }
        
        return get_string('not_determined', 'block_tmms_24');
    }
    
    public static function calculate_scores($responses) {
        if (!is_array($responses)) {
            return [
                'percepcion' => 0,
                'comprension' => 0,
                'regulacion' => 0
            ];
        }

        $ordered = [];

        // Prefer associative arrays keyed by item1..item24.
        $foundItemKeys = false;
        for ($i = 1; $i <= 24; $i++) {
            $key = 'item' . $i;
            if (array_key_exists($key, $responses)) {
                $ordered[] = (int)$responses[$key];
                $foundItemKeys = true;
            }
        }

        // Fallback: numeric keys 1..24.
        if (!$foundItemKeys) {
            $foundNumericKeys = true;
            for ($i = 1; $i <= 24; $i++) {
                if (!array_key_exists($i, $responses)) {
                    $foundNumericKeys = false;
                    break;
                }
            }

            if ($foundNumericKeys) {
                for ($i = 1; $i <= 24; $i++) {
                    $ordered[] = (int)$responses[$i];
                }
            } else {
                // Final fallback: trust the given order.
                $ordered = array_values($responses);
            }
        }

        // Ensure exactly 24 values.
        $ordered = array_slice($ordered, 0, 24);
        if (count($ordered) < 24) {
            $ordered = array_pad($ordered, 24, 0);
        }

        $percepcion = array_sum(array_slice($ordered, 0, 8));
        $comprension = array_sum(array_slice($ordered, 8, 8));
        $regulacion = array_sum(array_slice($ordered, 16, 8));

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

    // Detailed (long) interpretations: same thresholds, expanded guidance text.
    public static function get_interpretation_long($dimension, $score, $gender) {
        switch ($dimension) {
            case 'percepcion':
                if ($gender === 'M') {
                    if ($score <= 21) return get_string('perception_difficulty_feeling_long', 'block_tmms_24');
                    if ($score >= 22 && $score <= 32) return get_string('perception_adequate_feeling_long', 'block_tmms_24');
                    if ($score >= 33) return get_string('perception_excessive_attention_long', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 24) return get_string('perception_difficulty_feeling_long', 'block_tmms_24');
                    if ($score >= 25 && $score <= 35) return get_string('perception_adequate_feeling_long', 'block_tmms_24');
                    if ($score >= 36) return get_string('perception_excessive_attention_long', 'block_tmms_24');
                }
                break;

            case 'comprension':
                if ($gender === 'M') {
                    if ($score <= 25) return get_string('comprehension_difficulty_understanding_long', 'block_tmms_24');
                    if ($score >= 26 && $score <= 35) return get_string('comprehension_adequate_with_difficulties_long', 'block_tmms_24');
                    if ($score >= 36) return get_string('comprehension_great_clarity_long', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return get_string('comprehension_difficulty_understanding_long', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('comprehension_adequate_with_difficulties_long', 'block_tmms_24');
                    if ($score >= 35) return get_string('comprehension_great_clarity_long', 'block_tmms_24');
                }
                break;

            case 'regulacion':
                if ($gender === 'M') {
                    if ($score <= 23) return get_string('regulation_difficulty_managing_long', 'block_tmms_24');
                    if ($score >= 24 && $score <= 35) return get_string('regulation_adequate_balance_long', 'block_tmms_24');
                    if ($score >= 36) return get_string('regulation_great_capacity_long', 'block_tmms_24');
                } else { // 'F' o cualquier otro valor (incluye 'prefiero_no_decir')
                    if ($score <= 23) return get_string('regulation_difficulty_managing_long', 'block_tmms_24');
                    if ($score >= 24 && $score <= 34) return get_string('regulation_adequate_balance_long', 'block_tmms_24');
                    if ($score >= 35) return get_string('regulation_great_capacity_long', 'block_tmms_24');
                }
                break;
        }

        return get_string('not_determined', 'block_tmms_24');
    }

    public static function get_all_interpretations_long($scores, $gender) {
        return [
            'percepcion' => self::get_interpretation_long('percepcion', $scores['percepcion'], $gender),
            'comprension' => self::get_interpretation_long('comprension', $scores['comprension'], $gender),
            'regulacion' => self::get_interpretation_long('regulacion', $scores['regulacion'], $gender)
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

    /**
     * Calculates a normalized score (0-100) for fair comparison between dimensions.
     * 
     * Logic:
     * - Perception: Parabolic. Optimal (27M/30F) -> 100. Adequate Range -> 80-100. Outside -> 0-59.
     * - Comprehension/Regulation: Linear. Great Range -> 80-100. Adequate Range -> 60-79. Bad Range -> 0-59.
     */
    public static function get_normalized_score($dimension, $score, $gender) {
        $score = (int)$score;
        
        if ($dimension === 'percepcion') {
            // Perception Logic
            if ($gender === 'M') {
                $optimal = 27;
                $adequate_min = 22;
                $adequate_max = 32;
            } else {
                $optimal = 30;
                $adequate_min = 25;
                $adequate_max = 35;
            }
            
            // Check if in Adequate Range (Tier 1 - Best State for Perception)
            if ($score >= $adequate_min && $score <= $adequate_max) {
                // Map to [80, 100] based on distance to optimal
                // Optimal (27) -> 100
                // Edges (22, 32) -> 80
                
                if ($score <= $optimal) {
                    $side_range = $optimal - $adequate_min;
                    $dist = $optimal - $score;
                } else {
                    $side_range = $adequate_max - $optimal;
                    $dist = $score - $optimal;
                }
                
                if ($side_range > 0) {
                    return 100 - ($dist / $side_range) * 20;
                } else {
                    return 100;
                }
            } else {
                // Outside Adequate Range (Tier 3 - Bad) -> [0, 59]
                if ($score < $adequate_min) {
                    // Lower side: 0 to adequate_min-1
                    // Map 0 -> 0, adequate_min-1 -> 59
                    if ($adequate_min > 0) {
                        return ($score / $adequate_min) * 59;
                    }
                    return 0;
                } else {
                    // Upper side (Excessive): adequate_max+1 to 40
                    // Map adequate_max+1 -> 59, 40 -> 29 (Excessive is bad)
                    $range = 40 - $adequate_max;
                    $dist_from_edge = $score - $adequate_max;
                    if ($range > 0) {
                        return 59 - (($dist_from_edge / $range) * 30);
                    }
                    return 59;
                }
            }
        } else {
            // Comprehension & Regulation Logic (Linear)
            // Define thresholds
            if ($dimension === 'comprension') {
                if ($gender === 'M') {
                    $great_min = 36;
                    $adequate_min = 26;
                    $adequate_max = 35;
                } else {
                    $great_min = 35;
                    $adequate_min = 24;
                    $adequate_max = 34;
                }
            } else { // regulacion
                if ($gender === 'M') {
                    $great_min = 36;
                    $adequate_min = 24;
                    $adequate_max = 35;
                } else {
                    $great_min = 35;
                    $adequate_min = 24;
                    $adequate_max = 34;
                }
            }
            
            if ($score >= $great_min) {
                // Tier 1 (Great): [80, 100]
                $range = 40 - $great_min;
                $dist = $score - $great_min;
                if ($range > 0) {
                    return 80 + ($dist / $range) * 20;
                }
                return 100;
            } elseif ($score >= $adequate_min) {
                // Tier 2 (Adequate): [60, 79]
                $range = $adequate_max - $adequate_min;
                $dist = $score - $adequate_min;
                if ($range > 0) {
                    return 60 + ($dist / $range) * 19;
                }
                return 79;
            } else {
                // Tier 3 (Bad): [0, 59]
                // Map 0 -> 0, adequate_min-1 -> 59
                if ($adequate_min > 0) {
                    return ($score / $adequate_min) * 59;
                }
                return 0;
            }
        }
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
        } else if (has_capability('block/tmms_24:taketest', $context)) {
            // Check if completed (is_completed = 1)
            $entry = $DB->get_record('tmms_24', ['user' => $USER->id]);
            
            if ($entry && $entry->is_completed == 1) {
                // Show enhanced results directly in the block
                $this->content->text = '<div id="tmms-results-container">' . 
                                      $this->get_student_results($entry) . 
                                      '</div>';
            } else {
                // Show enhanced test invitation (checks is_completed inside)
                $this->content->text = '<div id="tmms-invitation-container">' . 
                                      $this->get_test_invitation() . 
                                      '</div>';
            }
        } else {
            // Users without either capability (e.g. guests/roles without taketest) see no TMMS content.
            $this->content->text = '';
        }
        
        return $this->content;
    }
    
    function has_config() {
        return false;
    }
    
    private function get_student_results($entry) {
        global $COURSE, $USER;
        
        $output = '';
        
        // Build responses keyed by item1..item24 to preserve ordering.
        $responsesArray = [];
        for ($i = 1; $i <= 24; $i++) {
            $field_name = 'item' . $i;
            if (!isset($entry->{$field_name}) || (int)$entry->{$field_name} <= 0) {
                return '<div class="alert alert-warning">' . get_string('incomplete_data', 'block_tmms_24') . '</div>';
            }
            $responsesArray[$field_name] = (int)$entry->{$field_name};
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
        $output .= '<div style="position: relative; display: inline-block; line-height: 0;">';
        $output .= $this->get_tmms_24_icon('4em', 'display: block;', false);
        $output .= '<i class="fa fa-check" style="position: absolute; top: -6px; right: -9px; font-size: 1.4em; background: white; border-radius: 50%; line-height: 1;"></i>';
        $output .= '</div>';
        $output .= '<h6 class="mt-2 mb-1">' . get_string('test_completed', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('emotional_intelligence_results', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Test description
        $output .= '<div class="tmms-description mb-3" style="background: #f8f9fa; padding: 10px 12px; border-radius: 5px; border-left: 3px solid #ff6600;">';
        $output .= '<small class="text-muted" style="line-height: 1.5;">';
        $output .= '<i class="fa fa-info-circle" style="color: #ff6600;"></i> ';
        $output .= get_string('test_description_short', 'block_tmms_24');
        $output .= '</small>';
        $output .= '</div>';
        
        // Your emotional intelligence
        $output .= '<div class="tmms-top-section mb-3">';
        
        // Dimension names mapping
        $dimension_names = [
            'percepcion' => get_string('perception', 'block_tmms_24'),
            'comprension' => get_string('comprehension', 'block_tmms_24'),
            'regulacion' => get_string('regulation', 'block_tmms_24')
        ];
        
        // Calculate normalized scores for comparison
        $normalized_scores = [];
        $gender = isset($entry->gender) ? $entry->gender : 'M';
        
        foreach ($scores as $dim => $score) {
            $normalized_scores[$dim] = TMMS24Facade::get_normalized_score($dim, $score, $gender);
        }
        
        // Check for "All Bad" case (All scores < 60)
        $all_bad = true;
        foreach ($normalized_scores as $n_score) {
            if ($n_score >= 60) {
                $all_bad = false;
                break;
            }
        }
        
        // Helper closure to get goal text
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

        if ($all_bad) {
            // Case: All dimensions need improvement
            $output .= '<h6 class="mb-2">' . get_string('your_dimensions', 'block_tmms_24') . '</h6>';
            
            foreach ($scores as $dimension => $score) {
                $interpretation = isset($interpretations[$dimension]) ? $interpretations[$dimension] : get_string('not_determined', 'block_tmms_24');
                $goal_text = $get_goal_text($dimension, $gender);
                
                $output .= '<div class="card border-secondary mb-2" style="border-color: #ffaa66 !important;">';
                $output .= '<div class="card-body p-2">';
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<strong class="small">' . $dimension_names[$dimension] . '</strong>';
                $output .= '<span class="small text-muted">' . $score . '/40</span>';
                $output .= '</div>';
                $output .= '<div class="small text-muted" style="line-height: 1.2;">' . $interpretation . '</div>';
                $output .= '<div class="small text-muted mt-1" style="font-size: 0.85em; color: #999 !important;"><i class="fa fa-bullseye"></i> ' . $goal_text . '</div>';
                $output .= '</div>';
                $output .= '</div>';
            }
            
        } else {
            // Case: At least one good dimension
            // Find max score
            $max_n_score = max($normalized_scores);
            
            // Find all dimensions with max score (allowing small float error)
            $star_dimensions = [];
            foreach ($normalized_scores as $dim => $n_score) {
                if (abs($n_score - $max_n_score) < 0.1) {
                    $star_dimensions[] = $dim;
                }
            }
            
            // Title based on count
            $title_key = (count($star_dimensions) > 1) ? 'your_star_dimensions' : 'your_emotional_intelligence';
            // If user wants specific title for single star, we can use 'your_star_dimension' but 'your_emotional_intelligence' was original.
            // User asked: "Que el titulo diga Tus Dimensiones Estrella" for ties.
            // Let's use "Your Star Dimension(s)" logic.
            if (count($star_dimensions) > 1) {
                $output .= '<h6 class="mb-2">' . get_string('your_star_dimensions', 'block_tmms_24') . '</h6>';
            } else {
                $output .= '<h6 class="mb-2">' . get_string('your_emotional_intelligence', 'block_tmms_24') . '</h6>';
            }
            
            // Render Star Dimensions
            foreach ($star_dimensions as $dim) {
                $score = $scores[$dim];
                $interpretation = isset($interpretations[$dim]) ? $interpretations[$dim] : get_string('not_determined', 'block_tmms_24');
                $goal_text = $get_goal_text($dim, $gender);
                
                $output .= '<div class="card border-primary mb-3" style="border-left: 4px solid #ff6600 !important; border-color: #ff6600 !important;">';
                $output .= '<div class="card-body p-3">';
                $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                $output .= '<div>';
                $output .= '<strong><i class="fa fa-star text-warning"></i> ' . $dimension_names[$dim] . '</strong><br>';
                $output .= '<small class="text-muted">' . $score . '/40 ' . get_string('points', 'block_tmms_24') . '</small>';
                $output .= '</div>';
                $output .= '</div>';
                
                $output .= '<div class="small text-muted mt-2" style="font-style: italic; line-height: 1.3;">' . $interpretation . '</div>';
                $output .= '<div class="small text-muted mt-1" style="font-size: 0.85em; color: #999 !important;"><i class="fa fa-bullseye"></i> ' . $goal_text . '</div>';
                
                // Explanation
                $output .= '<div class="mt-2 mb-2">';
                $output .= '<span class="badge badge-light text-wrap text-left" style="font-weight: normal; color: #666; background-color: #f8f9fa; border: 1px solid #eee; width: 100%; display: block; padding: 8px;">';
                if ($dim === 'percepcion') {
                     $optimal = ($gender === 'M') ? 27 : 30;
                     if ((int)$score === $optimal) {
                         $output .= '<i class="fa fa-balance-scale text-primary mr-1"></i> ' . get_string('star_dimension_reason_perception_exact', 'block_tmms_24');
                     } else {
                         $output .= '<i class="fa fa-balance-scale text-primary mr-1"></i> ' . get_string('star_dimension_reason_perception_close', 'block_tmms_24');
                     }
                } else {
                     $output .= '<i class="fa fa-arrow-up text-success mr-1"></i> ' . get_string('star_dimension_reason_other', 'block_tmms_24');
                }
                $output .= '</span>';
                $output .= '</div>';
                
                $output .= '</div>';
                $output .= '</div>';
            }
            
            // Render Other Dimensions
            $other_dimensions = array_diff(array_keys($scores), $star_dimensions);
            
            // Sort other dimensions by normalized score descending
            usort($other_dimensions, function($a, $b) use ($normalized_scores) {
                return $normalized_scores[$b] <=> $normalized_scores[$a];
            });

            if (!empty($other_dimensions)) {
                $output .= '<div class="tmms-other-dimensions mb-3">';
                $output .= '<h6 class="mb-2">' . get_string('other_dimensions', 'block_tmms_24') . '</h6>';
                foreach ($other_dimensions as $dim) {
                    $score = $scores[$dim];
                    $interpretation = isset($interpretations[$dim]) ? $interpretations[$dim] : get_string('not_determined', 'block_tmms_24');
                    $goal_text = $get_goal_text($dim, $gender);
                    
                    $output .= '<div class="card border-secondary mb-2" style="border-color: #ffaa66 !important;">';
                    $output .= '<div class="card-body p-2">';
                    $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                    $output .= '<strong class="small">' . $dimension_names[$dim] . '</strong>';
                    $output .= '<span class="small text-muted">' . $score . '/40</span>';
                    $output .= '</div>';
                    $output .= '<div class="small text-muted" style="line-height: 1.2;">' . $interpretation . '</div>';
                    $output .= '<div class="small text-muted mt-1" style="font-size: 0.85em; color: #999 !important;"><i class="fa fa-bullseye"></i> ' . $goal_text . '</div>';
                    $output .= '</div>';
                    $output .= '</div>';
                }
                $output .= '</div>';
            }
        }
        $url = new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-sm" style="background: linear-gradient(135deg, #ff6600 0%, #e65c00 100%); border-color: #ff6600; color: #fff;">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_detailed_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        $output .= '</div>';        return $output;
    }
    
    /**
     * Helper method to generate tmms_24 icon HTML (SVG)
     * @param string $size Icon size (default: 1.8em)
     * @param string $additional_style Additional inline styles
     * @param bool $centered Whether to center the icon
     * @return string HTML img tag with the SVG icon
     */
    private function get_tmms_24_icon($size = '1.8em', $additional_style = '', $centered = false) {
        $iconurl = new moodle_url('/blocks/tmms_24/pix/tmms_24_icon.svg');
        $style = 'width: ' . $size . '; height: ' . $size . '; vertical-align: middle; float: none !important;';
        if ($centered) {
            $style .= ' display: block; margin: 0 auto;';
        }
        if (!empty($additional_style)) {
            $style .= ' ' . $additional_style;
        }
        return '<img class="tmms-24-icon" src="' . $iconurl . '" alt="TMMS-24 Icon" style="' . $style . '" />';
    }
    
    private function get_test_invitation() {
        global $COURSE, $USER, $DB;
        
        $output = '';
        
        // Check for response in tmms_24 table (user only takes test once)
        $response = $DB->get_record('tmms_24', array('user' => $USER->id));
        
        $output .= '<div class="tmms-invitation-block">';
        
        // Header with TMMS-24 icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= $this->get_tmms_24_icon('4em', '', true);
        $output .= '<h6 class="mt-2 mb-1 font-weight-bold">' . get_string('emotional_intelligence_test', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('discover_your_emotional_skills', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Initialize variables
        $answered_count = 0;
        $button_text = '';
        $button_icon = '';
        $button_class = '';
        
        if ($response && !$response->is_completed) {
            // Test in progress - calculate progress
            for ($i = 1; $i <= 24; $i++) {
                $item_field = 'item' . $i;
                if (isset($response->$item_field) && $response->$item_field !== null) {
                    $answered_count++;
                }
            }
            
            // Only show progress if at least 1 question has been answered
            if ($answered_count > 0) {
                $progress_percentage = ($answered_count / 24) * 100;
                $all_answered = ($answered_count == 24);
                
                // Description section
                $output .= '<div class="tmms-description mb-3" style="background: white; padding: 10px 12px; border-radius: 5px; border-left: 3px solid #ff6600;">';
                $output .= '<small class="text-muted" style="line-height: 1.5;">';
                $output .= '<i class="fa fa-info-circle" style="color: #ff6600;"></i> ';
                $output .= get_string('test_description_short', 'block_tmms_24');
                $output .= '</small>';
                $output .= '</div>';
            
                if ($all_answered) {
                    // Show special message when all answered but not finished
                    $output .= '<div class="alert alert-warning mb-3" style="padding: 12px 15px; margin-bottom: 15px; border-left: 4px solid #ffc107; background-color: #fff3cd; border-radius: 4px;">';
                    $output .= '<div style="display: flex; align-items: start;">';
                    $output .= '<i class="fa fa-exclamation-triangle" style="color: #856404; margin-right: 10px; margin-top: 2px; font-size: 1.2em;"></i>';
                    $output .= '<div>';
                    $output .= '<strong style="color: #856404;">' . get_string('all_answered_title', 'block_tmms_24') . '</strong><br>';
                    $output .= '<small style="color: #856404;">' . get_string('all_answered_message', 'block_tmms_24') . '</small>';
                    $output .= '</div>';
                    $output .= '</div>';
                    $output .= '</div>';
                    
                    $button_text = get_string('finish_test', 'block_tmms_24');
                    $button_icon = 'fa-flag-checkered';
                    $button_class = 'btn-success';
                } else {
                    $button_text = get_string('continue_test', 'block_tmms_24');
                    $button_icon = 'fa-play';
                    $button_class = 'btn-primary';
                }
                
                // Show progress bar with TMMS colors
                $output .= '<div class="tmms-progress mb-3">';
                $output .= '<div class="d-flex justify-content-between align-items-center mb-2">';
                $output .= '<span class="small font-weight-bold">' . get_string('your_progress', 'block_tmms_24') . '</span>';
                $output .= '<span class="small text-muted">' . $answered_count . '/24</span>';
                $output .= '</div>';
                $output .= '<div class="progress mb-2" style="height: 8px; background-color: #ffebe0;">';
                $output .= '<div class="progress-bar" style="width: ' . $progress_percentage . '%; background: linear-gradient(90deg, #ffaa66 0%, #ff6600 100%);"></div>';
                $output .= '</div>';
                $output .= '<small class="text-muted">' . number_format($progress_percentage, 1) . '% ' . get_string('completed_status', 'block_tmms_24') . '</small>';
                $output .= '</div>';
            }
        }
        
        // Show test description for new test (when no response or no answers yet)
        if (!$response || ($response && !$response->is_completed && $answered_count == 0)) {
            // Show test description for new test
            $output .= '<div class="tmms-description mb-3">';
            $output .= '<div class="card border-info">';
            $output .= '<div class="card-body p-3">';
            $output .= '<h6 class="card-title font-weight-bold">';
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
            
            $button_text = get_string('start_test', 'block_tmms_24');
            $button_icon = 'fa-rocket';
            $button_class = 'btn-primary';
            $scroll_param = null;
        }
        
        // Call to action
        $output .= '<div class="tmms-actions text-center">';
        
        $url_params = array('cid' => $COURSE->id);
        if (isset($all_answered) && $all_answered) {
            $url_params['scroll_to_finish'] = 1;
        }
        $url = new moodle_url('/blocks/tmms_24/view.php', $url_params);
        
        $button_style = 'background-color: #ff6600; border-color: #ff6600; color: white;';
        if ($button_class == 'btn-success') {
            $button_style = ''; // Use default success style
        }
        
        $output .= '<a href="' . $url . '" class="btn ' . $button_class . ' btn-block" style="' . $button_style . '">';
        $output .= '<i class="fa ' . $button_icon . '"></i> ' . $button_text;
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function get_management_summary() {
        global $DB, $COURSE;
        
        $output = '';
        $output .= '<div class="tmms-management-block">';
        
        // Header with icon
        $output .= '<div class="tmms-header text-center mb-3">';
        $output .= $this->get_tmms_24_icon('4em', '', true);
        $output .= '<h6 class="mt-2 mb-1 font-weight-bold">' . get_string('management_title', 'block_tmms_24') . '</h6>';
        $output .= '<small class="text-muted">' . get_string('course_overview', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Get course statistics
        $context = context_course::instance($COURSE->id);
        $enrolled_students = get_enrolled_users($context, 'block/tmms_24:taketest', 0, 'u.id', null, 0, 0, true);

        $student_ids = array();
        foreach ($enrolled_students as $user) {
            $candidateid = (int)$user->id;
            // Defensive: never include teachers/managers/siteadmins in student stats.
            if (is_siteadmin($candidateid)) {
                continue;
            }
            if (has_capability('block/tmms_24:viewallresults', $context, $candidateid)) {
                continue;
            }
            $student_ids[] = $candidateid;
        }
        
        $total_enrolled = count($student_ids);
        
        // Obtener respuestas solo de estudiantes inscritos
        $total_completed = 0;
        $total_in_progress = 0;
        if (!empty($student_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
            $all_responses = $DB->get_records_select('tmms_24', "user $insql", $params);
            
            // Separar completados de en progreso
            foreach ($all_responses as $response) {
                if ($response->is_completed == 1) {
                    $total_completed++;
                } else {
                    // Solo contar como en progreso si tiene al menos 1 respuesta
                    $has_answers = false;
                    for ($i = 1; $i <= 24; $i++) {
                        $item = 'item' . $i;
                        if (isset($response->$item) && $response->$item !== null) {
                            $has_answers = true;
                            break;
                        }
                    }
                    if ($has_answers) {
                        $total_in_progress++;
                    }
                }
            }
        }
        
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
        $output .= '<div class="stat-number" style="color: #ff6600;">' . $total_completed . '</div>';
        $output .= '<div class="stat-label">' . get_string('completed', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        // In Progress
        $output .= '<div class="col-4">';
        $output .= '<div class="stat-card">';
        $output .= '<div class="stat-number text-warning">' . $total_in_progress . '</div>';
        $output .= '<div class="stat-label">' . get_string('in_progress', 'block_tmms_24') . '</div>';
        $output .= '</div>';
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</div>';
        
        // Progress bar overview
        $output .= '<div class="mb-3">';
        $output .= '<div class="progress" style="height: 10px;">';
        $output .= '<div class="progress-bar" style="width: ' . ($completion_rate) . '%; background: linear-gradient(135deg, #ff6600 0%, #e65c00 100%);"></div>';
        $output .= '</div>';
        $output .= '<small class="text-muted">' . $total_completed . ' ' . get_string('of', 'block_tmms_24') . ' ' . $total_enrolled . ' ' . get_string('students_completed', 'block_tmms_24') . '</small>';
        $output .= '</div>';
        
        // Recent completions (only completed tests)
        $recent_completions = array();
        if (!empty($student_ids)) {
            list($insql, $params) = $DB->get_in_or_equal($student_ids, SQL_PARAMS_NAMED, 'user');
            $sql = "SELECT u.firstname, u.lastname, tr.created_at 
                    FROM {tmms_24} tr 
                    JOIN {user} u ON tr.user = u.id 
                    WHERE tr.user $insql AND tr.is_completed = 1
                    ORDER BY tr.created_at DESC";
            $recent_completions = $DB->get_records_sql($sql, $params, 0, 3);
        }
        
        if ($recent_completions) {
            $output .= '<div class="recent-completions mt-3">';
            $output .= '<h6 class="mb-2 font-weight-bold">' . get_string('recent_completions', 'block_tmms_24') . '</h6>';
            foreach ($recent_completions as $completion) {
                $completion_date = $completion->created_at ? $completion->created_at : time();
                $output .= '<div class="d-flex justify-content-between align-items-center mb-1">';
                $output .= '<span class="small">' . $completion->firstname . ' ' . $completion->lastname . '</span>';
                $output .= '<span class="badge badge-success small" style="background: linear-gradient(135deg, #ff6600 0%, #e65c00 100%); border-color: #ff6600; color: #fff;">' . userdate($completion_date, get_string('strftimedatefullshort')) . '</span>';
                $output .= '</div>';
            }
            $output .= '</div>';
        }
        
        // Management actions
        $url = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $COURSE->id]);
        $output .= '<div class="tmms-actions text-center mt-3">';
        $output .= '<a href="' . $url . '" class="btn btn-sm btn-block" style="background: linear-gradient(135deg, #ff6600 0%, #e65c00 100%); border-color: #ff6600; color: #fff;">';
        $output .= '<i class="fa fa-chart-bar"></i> ' . get_string('view_all_results', 'block_tmms_24');
        $output .= '</a>';
        $output .= '</div>';
        
        $output .= '</div>';
        
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
