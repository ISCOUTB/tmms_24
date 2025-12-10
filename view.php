<?php
require_once(dirname(__FILE__) . '/../../config.php');

// Incluir la clase base de bloques para Moodle 4.1
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Ahora incluir nuestro archivo después de que Moodle esté cargado
require_once(dirname(__FILE__) . '/block_tmms_24.php');

if (!isloggedin()) {
    redirect($CFG->wwwroot . '/login/index.php');
}

$courseid = required_param('cid', PARAM_INT);
$view_results = optional_param('view_results', 0, PARAM_INT);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$PAGE->set_course($course);
$context = $PAGE->context;

$PAGE->set_url('/blocks/tmms_24/view.php', array('cid' => $courseid));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('test_title', 'block_tmms_24'));
$PAGE->set_heading(get_string('test_title', 'block_tmms_24'));

// Verificar si ya existe información del usuario (sin importar el curso)
$entry = $DB->get_record('tmms_24', array('user' => $USER->id));

echo $OUTPUT->header();
echo "<link rel='stylesheet' href='" . $CFG->wwwroot . "/blocks/tmms_24/styles.css'>";
echo "<div class='block_tmms_24_container'>";

if ($entry && $view_results) {
    // Mostrar resultados completos
    $responses = [
        $entry->item1, $entry->item2, $entry->item3, $entry->item4, $entry->item5, $entry->item6, $entry->item7, $entry->item8,
        $entry->item9, $entry->item10, $entry->item11, $entry->item12, $entry->item13, $entry->item14, $entry->item15, $entry->item16,
        $entry->item17, $entry->item18, $entry->item19, $entry->item20, $entry->item21, $entry->item22, $entry->item23, $entry->item24
    ];
    
    $scores = TMMS24Facade::calculate_scores($responses);
    $interpretations = TMMS24Facade::get_all_interpretations($scores, $entry->gender);
    
    echo "<div class='tmms-results-container'>";
    echo "<h2>" . get_string('results_title', 'block_tmms_24') . "</h2>";
    
    // Header with student name
    echo '<div class="mb-4">';
    echo '<h4 class="mb-0">' . get_string('results_for', 'block_tmms_24') . ' ' . fullname($USER) . '</h4>';
    echo '</div>';
    
    // Display results summary with cards (like student_results.php)
    echo '<div class="row mb-4">';
    echo '<div class="col-md-4">';
    echo '<div class="card border-primary">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('perception', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-primary">' . $scores['percepcion'] . '/40</h3>';
    echo '<p class="mb-0">' . $interpretations['percepcion'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4">';
    echo '<div class="card border-info">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('comprehension', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-info">' . $scores['comprension'] . '/40</h3>';
    echo '<p class="mb-0">' . $interpretations['comprension'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="col-md-4">';
    echo '<div class="card border-success">';
    echo '<div class="card-body text-center">';
    echo '<h5>' . get_string('regulation', 'block_tmms_24') . '</h5>';
    echo '<h3 class="text-success">' . $scores['regulacion'] . '/40</h3>';
    echo '<p class="mb-0">' . $interpretations['regulacion'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Test completion info
    echo '<div class="row mb-4">';
    echo '<div class="col-md-6">';
    echo '<strong>' . get_string('date_completed', 'block_tmms_24') . ':</strong> ';
    echo userdate($entry->created_at, get_string('strftimedatetimeshort'));
    echo '</div>';
    echo '<div class="col-md-6">';
    echo '<strong>' . get_string('gender', 'block_tmms_24') . ':</strong> ';
    
    // Convert gender code to display string
    $gender_display = '';
    switch($entry->gender) {
        case 'M':
            $gender_display = get_string('gender_male', 'block_tmms_24');
            break;
        case 'F':
            $gender_display = get_string('gender_female', 'block_tmms_24');
            break;
        case 'prefiero_no_decir':
            $gender_display = get_string('gender_prefer_not_say', 'block_tmms_24');
            break;
        default:
            $gender_display = $entry->gender; // fallback
    }
    echo $gender_display;
    echo '</div>';
    echo '</div>';

    // Detailed responses
    echo '<h5>' . get_string('detailed_responses', 'block_tmms_24') . '</h5>';
    
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
            $response_value = $entry->$item_key;
            
            echo '<tr>';
            echo '<td style="width: 60px;"><strong>' . $item_num . '.</strong></td>';
            echo '<td>' . get_string('item' . $item_num, 'block_tmms_24') . '</td>';
            echo '<td style="width: 100px;" class="text-center">';
            echo '<span class="badge badge-' . ($response_value >= 4 ? 'success' : ($response_value >= 3 ? 'warning' : 'danger')) . '">';
            echo $response_value . '/5';
            echo '</span>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }
    
    // Interpretation section
    echo '<div class="card">';
    echo '<div class="card-header">';
    echo '<h5 class="mb-0">' . get_string('results_interpretation', 'block_tmms_24') . '</h5>';
    echo '</div>';
    echo '<div class="card-body">';
    
    // Display interpretations directly
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>' . get_string('perception', 'block_tmms_24') . ':</strong><br>' . $interpretations['percepcion'] . '</div>';
    echo '<div class="col-md-4"><strong>' . get_string('comprehension', 'block_tmms_24') . ':</strong><br>' . $interpretations['comprension'] . '</div>';
    echo '<div class="col-md-4"><strong>' . get_string('regulation', 'block_tmms_24') . ':</strong><br>' . $interpretations['regulacion'] . '</div>';
    echo '</div>';
    
    echo '</div>';
    echo '</div>';
    
    // Botones de acción
    echo "<div class='results-actions mt-4'>";
    
    // Solo profesores/administradores pueden descargar resultados
    if (has_capability('block/tmms_24:viewallresults', context_course::instance($courseid))) {
        echo "<a href='" . new moodle_url('/blocks/tmms_24/export.php', array('cid' => $courseid, 'format' => 'csv')) . "' class='btn btn-success'>" . get_string('download_csv', 'block_tmms_24') . "</a> ";
        echo "<a href='" . new moodle_url('/blocks/tmms_24/export.php', array('cid' => $courseid, 'format' => 'json')) . "' class='btn btn-success'>" . get_string('download_json', 'block_tmms_24') . "</a> ";
    }
    
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    
    // Disclaimer
    echo "<div class='disclaimer'>";
    echo "<p><em>" . get_string('disclaimer', 'block_tmms_24') . "</em></p>";
    echo "<p><small>" . get_string('legal_notice', 'block_tmms_24') . "</small></p>";
    echo "</div>";
    
    echo "</div>";

} else if ($entry && !$view_results) {
    // Ya completó el test, mostrar opción de ver resultados (NO retomar)
    echo "<div class='test-completed-message'>";
    echo "<h2>" . get_string('test_completed', 'block_tmms_24') . "</h2>";
    echo "<p>" . get_string('test_already_completed', 'block_tmms_24') . "</p>";
    echo "<div class='completed-actions'>";
    echo "<a href='" . new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid, 'view_results' => 1)) . "' class='btn btn-primary'>" . get_string('view_full_results', 'block_tmms_24') . "</a> ";
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    echo "</div>";

} else if (!$entry) {
    // Mostrar formulario del test
    echo "<div class='tmms-test-container'>";
    
    // Instrucciones
    echo "<div class='test-instructions'>";
    echo "<h2>" . get_string('instructions_title', 'block_tmms_24') . "</h2>";
    echo "<p>" . get_string('instructions_text', 'block_tmms_24') . "</p>";
    echo "<p>" . get_string('instructions_text2', 'block_tmms_24') . "</p>";
    
    echo "<div class='scale-legend'>";
    echo "<h4>" . get_string('scale_legend', 'block_tmms_24') . "</h4>";
    echo "<ul>";
    echo "<li>" . get_string('scale_1', 'block_tmms_24') . "</li>";
    echo "<li>" . get_string('scale_2', 'block_tmms_24') . "</li>";
    echo "<li>" . get_string('scale_3', 'block_tmms_24') . "</li>";
    echo "<li>" . get_string('scale_4', 'block_tmms_24') . "</li>";
    echo "<li>" . get_string('scale_5', 'block_tmms_24') . "</li>";
    echo "</ul>";
    echo "</div>";
    echo "</div>";
    
    // Mensaje de continuación si hay borrador
    echo "<div id='continueDraftMessage' class='alert alert-info' style='display:none;'>";
    echo "<strong>" . get_string('draft_found', 'block_tmms_24') . "</strong><br>";
    echo get_string('draft_found_message', 'block_tmms_24');
    echo "</div>";
    
    // Formulario
    echo "<form method='POST' action='" . $CFG->wwwroot . "/blocks/tmms_24/save.php' class='tmms-form' id='tmmsForm'>";
    echo "<input type='hidden' name='cid' value='" . $courseid . "'>";
    echo "<input type='hidden' name='sesskey' value='" . sesskey() . "'>";
    
    // Datos demográficos
    echo "<div class='demographics-section'>";
    echo "<h3>" . get_string('demographics', 'block_tmms_24') . "</h3>";
    echo "<div class='form-row'>";
    echo "<div class='form-group'>";
    echo "<label for='age'>" . get_string('age', 'block_tmms_24') . " *</label>";
    echo "<input type='number' id='age' name='age' class='form-control' min='10' max='100'>";
    echo "</div>";
    echo "<div class='form-group'>";
    echo "<label for='gender'>" . get_string('gender', 'block_tmms_24') . " *</label>";
    echo "<select id='gender' name='gender' class='form-control'>";
    echo "<option value=''>Seleccione...</option>";
    echo "<option value='M'>" . get_string('gender_male', 'block_tmms_24') . "</option>";
    echo "<option value='F'>" . get_string('gender_female', 'block_tmms_24') . "</option>";
    echo "<option value='prefiero_no_decir'>" . get_string('gender_prefer_not_say', 'block_tmms_24') . "</option>";
    echo "</select>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    
    // Barra de progreso
    echo "<div class='progress-container'>";
    echo "<div class='progress-bar'>";
    echo "<div class='progress-fill' id='progressFill'></div>";
    echo "</div>";
    echo "<div class='progress-text' id='progressText'>0 / 24 " . get_string('items_completed', 'block_tmms_24') . "</div>";
    echo "</div>";
    
    // Cuestionario
    echo "<div class='questionnaire-section'>";
    echo "<h3>" . get_string('questionnaire', 'block_tmms_24') . "</h3>";
    
    $items = TMMS24Facade::get_tmms24_items();
    foreach ($items as $number => $text) {
        echo "<div class='question-item' data-item='" . $number . "' id='question-" . $number . "'>";
        echo "<div class='question-header'>";
        echo "<span class='question-number'>" . $number . ".</span>";
        echo "<span class='question-text'>" . $text . "</span>";
        echo "</div>";
        echo "<div class='likert-scale'>";
        for ($i = 1; $i <= 5; $i++) {
            echo "<label class='likert-option'>";
            echo "<input type='radio' name='item" . $number . "' value='" . $i . "'>";
            echo "<span class='likert-label'>" . $i . "</span>";
            echo "</label>";
        }
        echo "</div>";
        echo "</div>";
    }
    
    echo "</div>";
    
    // Botón de envío
    echo "<div class='form-actions'>";
    echo "<button type='submit' class='btn btn-primary btn-lg' id='submitBtn'>" . get_string('submit_test', 'block_tmms_24') . "</button>";
    echo "<a href='" . new moodle_url('/course/view.php', array('id' => $courseid)) . "' class='btn btn-secondary'>" . get_string('back_to_course', 'block_tmms_24') . "</a>";
    echo "</div>";
    
    echo "</form>";
    echo "</div>";
}

echo "</div>";

// JavaScript para validación y UX
echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('tmmsForm');
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    
    if (form) {
        let formAttempted = false;
        
        // Progreso y guardado local
        updateProgress();
        const hasDraft = loadFromLocalStorage();
        
        // Mostrar mensaje de continuación si hay borrador
        if (hasDraft) {
            const continueMsg = document.getElementById('continueDraftMessage');
            if (continueMsg) {
                continueMsg.style.display = 'block';
            }
        }
        
        // Event listeners para los radios
        const radios = form.querySelectorAll('input[type=\"radio\"]');
        radios.forEach(radio => {
            radio.addEventListener('change', function() {
                updateProgress();
                saveToLocalStorage();
                
                // Remover clase de error visual si se responde
                if (formAttempted) {
                    const itemNumber = this.name.replace('item', '');
                    const questionDiv = document.getElementById('question-' + itemNumber);
                    if (questionDiv) {
                        questionDiv.classList.remove('unanswered');
                    }
                }
            });
        });
        
        // Validación del formulario
        form.addEventListener('submit', function(e) {
            formAttempted = true;
            
            if (!validateForm()) {
                e.preventDefault();
                
                // Marcar visualmente demografía
                const ageInput = document.getElementById('age');
                const genderSelect = document.getElementById('gender');
                
                if (!ageInput.value) {
                    ageInput.classList.add('invalid');
                } else {
                    ageInput.classList.remove('invalid');
                }
                
                if (!genderSelect.value) {
                    genderSelect.classList.add('invalid');
                } else {
                    genderSelect.classList.remove('invalid');
                }
                
                // Marcar visualmente las preguntas sin responder
                for (let i = 1; i <= 24; i++) {
                    const checked = form.querySelector('input[name=\"item' + i + '\"]:checked');
                    const questionDiv = document.getElementById('question-' + i);
                    
                    if (!checked && questionDiv) {
                        questionDiv.classList.add('unanswered');
                    } else if (questionDiv) {
                        questionDiv.classList.remove('unanswered');
                    }
                }
                
                // Scroll al primer campo inválido
                const firstInvalidDemo = document.querySelector('#age.invalid, #gender.invalid');
                const firstUnanswered = document.querySelector('.question-item.unanswered');
                
                if (firstInvalidDemo) {
                    firstInvalidDemo.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else if (firstUnanswered) {
                    firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                
                return false;
            }
            // Limpiar localStorage al enviar
            clearLocalStorage();
        });
        
        // Guardado automático de datos demográficos
        const ageInput = document.getElementById('age');
        const genderSelect = document.getElementById('gender');
        
        if (ageInput) {
            ageInput.addEventListener('input', function() {
                saveToLocalStorage();
                if (formAttempted && this.value) {
                    this.classList.remove('invalid');
                }
            });
        }
        if (genderSelect) {
            genderSelect.addEventListener('change', function() {
                saveToLocalStorage();
                if (formAttempted && this.value) {
                    this.classList.remove('invalid');
                }
            });
        }
    }
    
    function updateProgress() {
        const totalItems = 24;
        const completedItems = form.querySelectorAll('input[type=\"radio\"]:checked').length;
        const percentage = (completedItems / totalItems) * 100;
        
        if (progressFill) {
            progressFill.style.width = percentage + '%';
        }
        if (progressText) {
            progressText.textContent = completedItems + ' / ' + totalItems + ' " . get_string('items_completed', 'block_tmms_24') . "';
        }
    }
    
    function validateForm() {
        let isValid = true;
        
        // Validar datos demográficos
        const age = document.getElementById('age');
        const gender = document.getElementById('gender');
        
        if (!age.value) {
            isValid = false;
        }
        if (!gender.value) {
            isValid = false;
        }
        
        // Validar todos los ítems
        for (let i = 1; i <= 24; i++) {
            const checked = form.querySelector('input[name=\"item' + i + '\"]:checked');
            if (!checked) {
                isValid = false;
            }
        }
        
        return isValid;
    }
    
    function saveToLocalStorage() {
        const formData = new FormData(form);
        const data = {};
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        localStorage.setItem('tmms24_draft_' + " . $courseid . ", JSON.stringify(data));
    }
    
    function loadFromLocalStorage() {
        const saved = localStorage.getItem('tmms24_draft_' + " . $courseid . ");
        if (saved) {
            const data = JSON.parse(saved);
            Object.keys(data).forEach(key => {
                const element = form.querySelector('[name=\"' + key + '\"]');
                if (element) {
                    if (element.type === 'radio') {
                        const radio = form.querySelector('[name=\"' + key + '\"][value=\"' + data[key] + '\"]');
                        if (radio) radio.checked = true;
                    } else {
                        element.value = data[key];
                    }
                }
            });
            updateProgress();
            return true; // Indica que había borrador
        }
        return false; // No había borrador
    }
    
    function clearLocalStorage() {
        localStorage.removeItem('tmms24_draft_' + " . $courseid . ");
    }
});
</script>";

echo $OUTPUT->footer();
?>
