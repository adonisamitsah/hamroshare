<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

header('Content-Type: application/json');

// Get the 5 most recent unread success/error logs
$query = "SELECT * FROM system_logs WHERE is_notified = 0 ORDER BY id DESC LIMIT 5";
$result = $db->query($query);

$notifications = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $notifications[] = $row;
    
    // Mark as notified immediately so it doesn't pop up again
    $db->exec("UPDATE system_logs SET is_notified = 1 WHERE id = " . $row['id']);
}

echo json_encode($notifications);