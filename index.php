<?php
require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */



// Fallback to normal dashboard view
header("Location: dashboard.php");
exit();
?>