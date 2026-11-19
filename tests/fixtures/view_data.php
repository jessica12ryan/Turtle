<?php

// Fixture for tests/view_test.php: verifies that a view variable named "data"
// survives the EXTR_SKIP extraction in App\Core\View.
if (!isset($data) || !is_array($data) || ($data['key'] ?? null) !== 'value') {
    echo 'MISSING';
    return;
}
echo $data['key'];
