<?php
// Fixture for tests/view_test.php: layout variables must also extract cleanly.
echo 'LAYOUT:' . ($data['key'] ?? 'MISSING') . ':' . ($content ?? 'NO_CONTENT');
