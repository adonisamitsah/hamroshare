<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

$dmat = $_GET['dmat_num'];
$formId = $_GET['formId'];
$scrip = $_GET['scrip'];

$auth = sudo_get_Authorization($dmat, $db);

// Hit the Detail API
$url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/report/detail/" . $formId;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: $auth",
        "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36",
        "sec-ch-ua-platform: \"Android\""
    ],
    CURLOPT_SSL_VERIFYPEER => false
]);
$resp = curl_exec($ch);
curl_close($ch);

$data = json_decode($resp, true);

if (isset($data['statusName'])) {
    $status = trim($data['statusName']);
    $kitta = isset($data['receivedKitta']) ? (int)$data['receivedKitta'] : 0;

    // 1. Update Local Database
    $stmt = $db->prepare("UPDATE ipo_results SET statusName = :status, receivedKitta = :kitta, last_updated = CURRENT_TIMESTAMP WHERE dmat_num = :dmat AND applicantFormId = :fid");
    $stmt->bindValue(':status', $status);
    $stmt->bindValue(':kitta', $kitta);
    $stmt->bindValue(':dmat', $dmat);
    $stmt->bindValue(':fid', $formId);
    $stmt->execute();

    // 2. Extract values and trigger Ledger Math if Allotted
    if ($status === 'Alloted' || $status === 'Allotted') {
        
        $applied_kitta = isset($data['appliedKitta']) ? (float)$data['appliedKitta'] : 0;
        $total_blocked_amount = isset($data['amount']) ? (float)$data['amount'] : 0;
        
        // Safely calculate exactly how much money was deducted for the won shares
        $alloted_amount = ($applied_kitta > 0) ? ($total_blocked_amount / $applied_kitta) * $kitta : 0;
        
        // The allotted amount acts as the "withdrawal" from their bank account
        initiateLedgerEntry(
            $db, 
            $dmat, 
            "Allotment of $scrip (Kitta: $kitta)", 
            0, // deposit
            $alloted_amount, // withdraw
            'IPO_ALLOTMENT'
        );
    }

    // 3. Return the updated data to JS
    echo json_encode([
        "statusName" => $status,
        "receivedKitta" => $kitta,
        "applicantFormId" => $formId,
        "scrip" => $scrip
    ]);
} else {
    // Graceful error handling for the frontend
    http_response_code(400);
    echo json_encode(["error" => "Failed to retrieve valid API data from MeroShare."]);
}
?>