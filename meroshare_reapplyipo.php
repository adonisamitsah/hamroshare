<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

$dmat_num = $_POST['dmat_num'] ?? '';
$companyShareId = $_POST['companyShareId'] ?? ''; 

if (empty($dmat_num) || empty($companyShareId)) {
    echo json_encode(["message" => "Error: Missing DMAT or Company Share ID"]);
    exit;
}

// STEP 1: Fetch exact reapply details (Application ID & Kitta) directly from the dedicated endpoint
$reapplyDataJson = sudo_get_reapply_details($dmat_num, $companyShareId, $db);
$reapplyDetails = json_decode($reapplyDataJson, true);

$appId = $reapplyDetails['applicantFormId'] ?? null;
$appliedKitta = $reapplyDetails['appliedKitta'] ?? 10; // Fallback

if ($appId) {
    // STEP 2: Execute the reapplication using the precise parameters
    echo sudo_reapply_ipo($dmat_num, $appId, $companyShareId, $appliedKitta, $db);
} else {
    // Return a structured error if the MeroShare API didn't give us the App ID
    $errorMessage = $reapplyDetails['message'] ?? "No Active App ID Found for Reapplication.";
    echo json_encode(["status" => "ERROR", "message" => $errorMessage]);
}
?>