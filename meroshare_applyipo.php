<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// 1. Get parameters from the AJAX call
$dmat_num = $_GET['dmat_num'] ?? '';
$companyShareId = $_GET['companyShareId'] ?? '';

if (empty($dmat_num) || empty($companyShareId)) {
    echo json_encode(["message" => "Error: Missing DMAT or Share ID"]);
    exit;
}

// 2. Perform the "Handshake" and update Bank Details
// Now passing all 3 required arguments
$updateStatus = sudo_update_bankDetails($dmat_num, $db, $companyShareId);

if ($updateStatus === "Success") {
    // 3. If bank details are synced, proceed to apply
    $data = sudo_apply_ipo($dmat_num, $companyShareId, $db);
    echo $data;
} else {
    // Return the specific error (e.g., Firewall block or Step 2 failure)
    echo json_encode(["message" => "Bank Sync Failed: " . $updateStatus]);
}
?>