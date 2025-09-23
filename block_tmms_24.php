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
            // Teacher/Admin view: show a link to the dashboard.
            $url = new moodle_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $COURSE->id]);
            $this->content->text = '<div class="text-center"><a href="' . $url . '" class="btn btn-primary">' . get_string('view_all_results', 'block_tmms_24') . '</a></div>';
        } else {
            // Student view
            $entry = $DB->get_record('tmms_24', ['user' => $USER->id, 'course' => $COURSE->id]);

            if ($entry) {
                // If student has taken the test, show their results
                ob_start();
                include(__DIR__ . '/student_results.php');
                $this->content->text = ob_get_clean();
            } else {
                // If student has not taken the test, show the button to take it
                $url = new moodle_url('/blocks/tmms_24/view.php', ['cid' => $COURSE->id]);
                $this->content->text = '<div class="text-center"><a href="' . $url . '" class="btn btn-primary">' . get_string('take_test', 'block_tmms_24') . '</a></div>';
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
}