<?php
// ajax_single_edis.php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_edis_automation.php';
require_once __DIR__ . '/class_auth_manager.php';

try {
    // 1. Parse incoming JSON request
    $input = json_decode(file_get_contents('php://input'), true);
    $dmat = $input['dmat'] ?? null;

    if (!$dmat) {
        echo json_encode(['status' => 'error', 'message' => 'Missing DMAT parameter.']);
        exit;
    }


    $authManager = new AuthManager($db);

    // 3. Fetch User Profile
    $stmt = $db->prepare("SELECT * FROM users WHERE dmat_num = :dmat LIMIT 1");
    $stmt->bindValue(':dmat', $dmat, SQLITE3_TEXT);
    $userRow = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$userRow) {
        echo json_encode(['status' => 'error', 'message' => 'User profile not found in database.']);
        exit;
    }

    // 4. Secure Authentication Token
    $token = $authManager->getToken($userRow);

    if (!$token) {
        echo json_encode(['status' => 'error', 'message' => 'MeroShare Authentication Failed or Expired.']);
        exit;
    }

    // 5. Execute Pipeline
    $edis = new EDISAutomation($db, $dmat, $token);
    $report = $edis->run(); // Returns the formatted array with status, transfers_done, danger_obligations, errors.

    // 6. Return Data to UI
    echo json_encode($report);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'System Error: ' . $e->getMessage()]);
}
?>