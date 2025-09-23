<?php
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
require_once(dirname(__FILE__) . '/block_tmms_24.php');

if (!isloggedin()) {
    redirect($CFG->wwwroot . '/login/index.php');
}

$courseid = required_param('cid', PARAM_INT);
$format = required_param('format', PARAM_ALPHA);

if ($courseid == SITEID && !$courseid) {
    redirect($CFG->wwwroot);
}

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid);

// Require capability to view all results to access this script
require_capability('block/tmms_24:viewallresults', $context);

// Fetch all results for the course
$all_entries = $DB->get_records('tmms_24', array('course' => $courseid), 'created_at DESC');

if (empty($all_entries)) {
    redirect(new moodle_url('/course/view.php', array('id' => $courseid)), 
             get_string('no_results_yet', 'block_tmms_24'), null, 'error');
}

$facade = new TMMS24Facade();

// Prepare data for export
$export_data = [];
foreach ($all_entries as $entry) {
    $user = $DB->get_record('user', ['id' => $entry->user]);
    if (!$user) {
        continue;
    }

    $responses = [];
    for ($i = 1; $i <= 24; $i++) {
        $item = 'item' . $i;
        $responses[] = $entry->$item;
    }

    $scores = $facade->calculate_scores($responses);
    $interpretations = $facade->get_all_interpretations($scores, $entry->gender);

    $export_data[] = [
        'timestamp' => date('c', $entry->created_at),
        'user_id' => $entry->user,
        'course_id' => $entry->course,
        'participant_name' => $user->firstname . ' ' . $user->lastname,
        'participant_email' => $user->email,
        'age' => $entry->age,
        'gender' => $entry->gender,
        'perception_score' => $scores['perception'],
        'comprehension_score' => $scores['comprehension'],
        'regulation_score' => $scores['regulation'],
        'perception_interpretation' => $interpretations['perception'],
        'comprehension_interpretation' => $interpretations['comprehension'],
        'regulation_interpretation' => $interpretations['regulation'],
    ];
}

$filename = 'tmms24_results_course_' . $courseid . '_' . time();

if ($format == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $fp = fopen('php://output', 'w');

    // Add headers
    fputcsv($fp, array_keys($export_data[0]));

    // Add data
    foreach ($export_data as $row) {
        fputcsv($fp, $row);
    }

    fclose($fp);
    exit;

} elseif ($format == 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    echo json_encode($export_data, JSON_PRETTY_PRINT);
    exit;

} else {
    print_error('invalidformat', 'block_tmms_24');
}

    'scores' => $scores,
    'interpretations' => $interpretations
];

// Agregar respuestas individuales
for ($i = 1; $i <= 24; $i++) {
    $export_data['responses']['item_' . $i] = $responses[$i - 1];
}

// Generar archivo según formato
if ($format === 'csv') {
    // Exportación CSV
    $filename = 'tmms24_results_' . $USER->id . '_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    $headers = [
        'Timestamp', 'User_ID', 'Course_ID', 'Name', 'Email', 'Age', 'Gender'
    ];
    
    // Agregar columnas de respuestas
    for ($i = 1; $i <= 24; $i++) {
        $headers[] = 'Item_' . $i;
    }
    
    // Agregar columnas de puntajes
    $headers[] = 'Perception_Score';
    $headers[] = 'Comprehension_Score';
    $headers[] = 'Regulation_Score';
    
    // Agregar columnas de interpretaciones
    if ($entry->gender === 'prefiero_no_decir') {
        $headers[] = 'Perception_Interpretation_Male';
        $headers[] = 'Comprehension_Interpretation_Male';
        $headers[] = 'Regulation_Interpretation_Male';
        $headers[] = 'Perception_Interpretation_Female';
        $headers[] = 'Comprehension_Interpretation_Female';
        $headers[] = 'Regulation_Interpretation_Female';
    } else {
        $headers[] = 'Perception_Interpretation';
        $headers[] = 'Comprehension_Interpretation';
        $headers[] = 'Regulation_Interpretation';
    }
    
    fputcsv($output, $headers);
    
    // Datos
    $row = [
        $export_data['timestamp'],
        $export_data['user_id'],
        $export_data['course_id'],
        $export_data['participant_name'],
        $export_data['participant_email'],
        $export_data['age'],
        $export_data['gender']
    ];
    
    // Agregar respuestas
    for ($i = 1; $i <= 24; $i++) {
        $row[] = $export_data['responses']['item_' . $i];
    }
    
    // Agregar puntajes
    $row[] = $scores['percepcion'];
    $row[] = $scores['comprension'];
    $row[] = $scores['regulacion'];
    
    // Agregar interpretaciones
    if ($entry->gender === 'prefiero_no_decir') {
        $row[] = $interpretations['Hombre']['percepcion'];
        $row[] = $interpretations['Hombre']['comprension'];
        $row[] = $interpretations['Hombre']['regulacion'];
        $row[] = $interpretations['Mujer']['percepcion'];
        $row[] = $interpretations['Mujer']['comprension'];
        $row[] = $interpretations['Mujer']['regulacion'];
    } else {
        $row[] = $interpretations['result']['percepcion'];
        $row[] = $interpretations['result']['comprension'];
        $row[] = $interpretations['result']['regulacion'];
    }
    
    fputcsv($output, $row);
    fclose($output);
    
} else if ($format === 'json') {
    // Exportación JSON
    $filename = 'tmms24_results_' . $USER->id . '_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // Agregar metadatos adicionales
    $export_data['metadata'] = [
        'test_name' => 'TMMS-24',
        'test_version' => '1.0.0',
        'export_date' => date('c'),
        'moodle_version' => $CFG->version ?? 'unknown',
        'course_name' => $course->fullname,
        'course_shortname' => $course->shortname
    ];
    
    // Agregar ítems del test para referencia
    $export_data['test_items'] = [];
    for ($i = 1; $i <= 24; $i++) {
        $export_data['test_items'][$i] = get_string('item' . $i, 'block_tmms_24');
    }
    
    // Agregar información sobre las dimensiones
    $export_data['dimensions'] = [
        'perception' => [
            'name' => get_string('dimension_perception', 'block_tmms_24'),
            'items' => range(1, 8),
            'score_range' => [8, 40],
            'score' => $scores['percepcion']
        ],
        'comprehension' => [
            'name' => get_string('dimension_comprehension', 'block_tmms_24'),
            'items' => range(9, 16),
            'score_range' => [8, 40],
            'score' => $scores['comprension']
        ],
        'regulation' => [
            'name' => get_string('dimension_regulation', 'block_tmms_24'),
            'items' => range(17, 24),
            'score_range' => [8, 40],
            'score' => $scores['regulacion']
        ]
    ];
    
    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} else {
    // Formato no válido
    redirect(new moodle_url('/blocks/tmms_24/view.php', array('cid' => $courseid, 'view_results' => 1)), 
             get_string('invalid_export_format', 'block_tmms_24'), null, 'error');
}

exit;
?>