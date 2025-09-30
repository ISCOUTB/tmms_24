<?php
require_once('../../config.php');
require_once('block_tmms_24.php');

echo "<h3>Prueba de nuevas interpretaciones TMMS-24</h3>";

// Casos de prueba
$test_cases = [
    // Percepción - Hombre
    ['dimension' => 'percepcion', 'score' => 18, 'gender' => 'M', 'desc' => 'Hombre, score 18 (< 21)'],
    ['dimension' => 'percepcion', 'score' => 25, 'gender' => 'M', 'desc' => 'Hombre, score 25 (22-32)'],
    ['dimension' => 'percepcion', 'score' => 35, 'gender' => 'M', 'desc' => 'Hombre, score 35 (> 33)'],
    
    // Percepción - Mujer
    ['dimension' => 'percepcion', 'score' => 20, 'gender' => 'F', 'desc' => 'Mujer, score 20 (< 24)'],
    ['dimension' => 'percepcion', 'score' => 30, 'gender' => 'F', 'desc' => 'Mujer, score 30 (25-35)'],
    ['dimension' => 'percepcion', 'score' => 38, 'gender' => 'F', 'desc' => 'Mujer, score 38 (> 36)'],
    
    // Comprensión - Hombre
    ['dimension' => 'comprension', 'score' => 22, 'gender' => 'M', 'desc' => 'Hombre, score 22 (< 25)'],
    ['dimension' => 'comprension', 'score' => 30, 'gender' => 'M', 'desc' => 'Hombre, score 30 (26-35)'],
    ['dimension' => 'comprension', 'score' => 38, 'gender' => 'M', 'desc' => 'Hombre, score 38 (> 36)'],
    
    // Comprensión - Mujer
    ['dimension' => 'comprension', 'score' => 20, 'gender' => 'F', 'desc' => 'Mujer, score 20 (< 23)'],
    ['dimension' => 'comprension', 'score' => 28, 'gender' => 'F', 'desc' => 'Mujer, score 28 (24-34)'],
    ['dimension' => 'comprension', 'score' => 37, 'gender' => 'F', 'desc' => 'Mujer, score 37 (> 35)'],
    
    // Regulación - Hombre
    ['dimension' => 'regulacion', 'score' => 20, 'gender' => 'M', 'desc' => 'Hombre, score 20 (< 23)'],
    ['dimension' => 'regulacion', 'score' => 28, 'gender' => 'M', 'desc' => 'Hombre, score 28 (24-35)'],
    ['dimension' => 'regulacion', 'score' => 38, 'gender' => 'M', 'desc' => 'Hombre, score 38 (> 36)'],
    
    // Regulación - Mujer
    ['dimension' => 'regulacion', 'score' => 20, 'gender' => 'F', 'desc' => 'Mujer, score 20 (< 23)'],
    ['dimension' => 'regulacion', 'score' => 28, 'gender' => 'F', 'desc' => 'Mujer, score 28 (24-34)'],
    ['dimension' => 'regulacion', 'score' => 37, 'gender' => 'F', 'desc' => 'Mujer, score 37 (> 35)'],
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Caso</th><th>Interpretación</th></tr>";

foreach ($test_cases as $case) {
    try {
        $interpretation = TMMS24Facade::get_interpretation($case['dimension'], $case['score'], $case['gender']);
        echo "<tr>";
        echo "<td>" . $case['desc'] . "</td>";
        echo "<td>" . $interpretation . "</td>";
        echo "</tr>";
    } catch (Exception $e) {
        echo "<tr>";
        echo "<td>" . $case['desc'] . "</td>";
        echo "<td style='color: red;'>ERROR: " . $e->getMessage() . "</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<h4>Prueba de cálculo de puntajes</h4>";
$test_responses = array_fill(0, 24, 3); // Todas las respuestas con valor 3
$scores = TMMS24Facade::calculate_scores($test_responses);
echo "<p>Respuestas de prueba (todas = 3):</p>";
echo "<p>Percepción: " . $scores['percepcion'] . " (esperado: 24)</p>";
echo "<p>Comprensión: " . $scores['comprension'] . " (esperado: 24)</p>";
echo "<p>Regulación: " . $scores['regulacion'] . " (esperado: 24)</p>";

$interpretations = TMMS24Facade::get_all_interpretations($scores, 'M');
echo "<p>Interpretaciones para hombre:</p>";
foreach ($interpretations as $dim => $interp) {
    echo "<p>$dim: $interp</p>";
}
?>