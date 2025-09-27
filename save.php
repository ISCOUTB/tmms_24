<?php
require_once(dirname(__FILE__) . '/../../config.php');

// Incluir la clase base de bloques para Moodle 4.1
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');

// Incluir nuestro archivo después de que Moodle esté cargado
require_once(dirname(__FILE__) . '/block_tmms_24.php');

if (!isloggedin()) {
    redirect($CFG->wwwroot . '/login/index.php');
}

require_sesskey();

$courseid = required_param('cid', PARAM_INT);
$age = required_param('age', PARAM_INT);
$gender = required_param('gender', PARAM_ALPHA);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Validar que todos los ítems estén presentes
$responses = [];
$missing_items = [];

for ($i = 1; $i <= 24; $i++) {
    $item_value = optional_param('item' . $i, 0, PARAM_INT);
    if ($item_value < 1 || $item_value > 5) {
        $missing_items[] = $i;
    } else {
        $responses[$i] = $item_value;
    }
}

// Validar datos demográficos
if ($age < 10 || $age > 100) {
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             get_string('validation_age_required', 'block_tmms_24'), null, 'error');
}

if (!in_array($gender, ['M', 'F', 'prefiero_no_decir'])) {
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             get_string('validation_gender_required', 'block_tmms_24'), null, 'error');
}

// Si hay ítems faltantes, redirigir con error
if (!empty($missing_items)) {
    $error_message = get_string('validation_missing_items', 'block_tmms_24') . ' ' . implode(', ', $missing_items);
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             $error_message, null, 'error');
}

// Calcular puntajes
$scores = TMMS24Facade::calculate_scores(array_values($responses));

// Verificar si ya existe un registro para este usuario en este curso
$existing_entry = $DB->get_record('tmms_24', array('user' => $USER->id, 'course' => $courseid));

// Si ya existe un registro, no permitir retakes
if ($existing_entry) {
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid, 'view_results' => 1)), 
             get_string('test_already_completed', 'block_tmms_24'), null, 'info');
}

$entry = new stdClass();
$entry->user = $USER->id;
$entry->course = $courseid;
$entry->age = $age;
$entry->gender = $gender;

// Asignar respuestas individuales
for ($i = 1; $i <= 24; $i++) {
    $field_name = 'item' . $i;
    $entry->$field_name = $responses[$i];
}

// Asignar puntajes calculados
$entry->percepcion_score = $scores['percepcion'];
$entry->comprension_score = $scores['comprension'];
$entry->regulacion_score = $scores['regulacion'];

$entry->created_at = time();
$entry->updated_at = time();

try {
    // Crear nuevo registro (único permitido)
    $entry->id = $DB->insert_record('tmms_24', $entry);
    $message = get_string('test_saved_successfully', 'block_tmms_24');
    
    // Generar evento para logging (opcional)
    $event = \core\event\user_updated::create(array(
        'objectid' => $USER->id,
        'context' => $context,
        'relateduserid' => $USER->id, // Añadir el ID del usuario relacionado
        'other' => array('action' => 'tmms24_completed')
    ));
    $event->trigger();
    
    // Redirigir a la vista de resultados
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid, 'view_results' => 1)), 
             $message, null, 'success');
             
} catch (Exception $e) {
    // Error al guardar
    error_log('Error saving TMMS-24 test: ' . $e->getMessage());
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid)), 
             get_string('error_saving_test', 'block_tmms_24'), null, 'error');
}
?>