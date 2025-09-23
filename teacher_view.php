<?php
require_once('../../config.php');
require_once($CFG->libdir.'/tablelib.php');
require_once('block_tmms_24.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($course->id);

require_login($course);
require_capability('block/tmms_24:viewallresults', $context);

$PAGE->set_url('/blocks/tmms_24/teacher_view.php', ['courseid' => $courseid]);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('all_results_title', 'block_tmms_24'));
$PAGE->set_heading(get_string('all_results_heading', 'block_tmms_24'));

echo $OUTPUT->header();

$results = $DB->get_records('tmms_24', ['course' => $courseid]);

if (!empty($results)) {
    // Estadísticas
    $total_completed = count($results);
    $avg_scores = ['percepcion' => 0, 'comprension' => 0, 'regulacion' => 0];
    
    foreach ($results as $result) {
        // Calculate scores from individual item responses
        $responses = [];
        for ($i = 1; $i <= 24; $i++) {
            $item = 'item' . $i;
            $responses[] = $result->$item;
        }
        $scores = TMMS24Facade::calculate_scores($responses);
        $avg_scores['percepcion'] += $scores['percepcion'];
        $avg_scores['comprension'] += $scores['comprension'];
        $avg_scores['regulacion'] += $scores['regulacion'];
    }
    $avg_scores['percepcion'] /= $total_completed;
    $avg_scores['comprension'] /= $total_completed;
    $avg_scores['regulacion'] /= $total_completed;

    echo '<h3>' . get_string('statistics', 'block_tmms_24') . '</h3>';
    echo '<p>' . get_string('total_completed', 'block_tmms_24') . ': ' . $total_completed . '</p>';
    echo '<ul>';
    echo '<li>' . get_string('avg_perception', 'block_tmms_24') . ': ' . round($avg_scores['percepcion'], 2) . '</li>';
    echo '<li>' . get_string('avg_comprehension', 'block_tmms_24') . ': ' . round($avg_scores['comprension'], 2) . '</li>';
    echo '<li>' . get_string('avg_regulation', 'block_tmms_24') . ': ' . round($avg_scores['regulacion'], 2) . '</li>';
    echo '</ul>';

    // Botón de descarga
    $download_url = new moodle_url('/blocks/tmms_24/export.php', ['cid' => $courseid, 'format' => 'csv']);
    echo '<a href="' . $download_url . '" class="btn btn-success">' . get_string('download_all_csv', 'block_tmms_24') . '</a>';

    // Tabla de resultados
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('perception', 'block_tmms_24'),
        get_string('comprehension', 'block_tmms_24'),
        get_string('regulation', 'block_tmms_24'),
        get_string('date_completed', 'block_tmms_24')
    ];
    $table->data = [];

    foreach ($results as $result) {
        $user = $DB->get_record('user', ['id' => $result->user]);
        
        // Calculate scores from individual item responses
        $responses = [];
        for ($i = 1; $i <= 24; $i++) {
            $item = 'item' . $i;
            $responses[] = $result->$item;
        }
        $scores = TMMS24Facade::calculate_scores($responses);
        
        $row = [
            fullname($user),
            $scores['percepcion'],
            $scores['comprension'],
            $scores['regulacion'],
            userdate($result->created_at)
        ];
        $table->data[] = $row;
    }
    echo html_writer::table($table);
} else {
    echo get_string('no_results_yet', 'block_tmms_24');
}

echo $OUTPUT->footer();
