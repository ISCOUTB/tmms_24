<?php
require_once('../../config.php');

echo "<h3>Debug de cadenas TMMS-24</h3>";

$test_strings = [
    'questions_24',
    'dimensions_3', 
    'pluginname',
    'emotional_intelligence_test',
    'test_description_short',
    'duration_5_minutes'
];

foreach ($test_strings as $string_key) {
    try {
        if (get_string_manager()->string_exists($string_key, 'block_tmms_24')) {
            $value = get_string($string_key, 'block_tmms_24');
            echo "<p><strong>$string_key:</strong> ✓ $value</p>";
        } else {
            echo "<p><strong>$string_key:</strong> ✗ No existe</p>";
        }
    } catch (Exception $e) {
        echo "<p><strong>$string_key:</strong> ERROR: " . $e->getMessage() . "</p>";
    }
}

echo "<h4>Verificación de get_tmms24_items()</h4>";
try {
    require_once('block_tmms_24.php');
    $items = TMMS24Facade::get_tmms24_items();
    echo "<p>Primeros 3 items:</p>";
    for ($i = 1; $i <= 3; $i++) {
        echo "<p>Item $i: " . (isset($items[$i]) ? $items[$i] : 'NO DEFINIDO') . "</p>";
    }
} catch (Exception $e) {
    echo "<p>ERROR en get_tmms24_items(): " . $e->getMessage() . "</p>";
}
?>