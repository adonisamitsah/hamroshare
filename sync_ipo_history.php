<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

$dmat_num = $_GET['dmat_num'];
$auth = sudo_get_Authorization($dmat_num, $db);

// 1. Fetch Discovery Data from Meroshare
$url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/active/search/";
$payload = json_encode([
    "filterFieldParams" => [], "page" => 1, "size" => 200, 
    "searchRoleViewConstants" => "VIEW_APPLICANT_FORM_COMPLETE",
    "filterDateParams" => []
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, // Fixed Typo here
    CURLOPT_HTTPHEADER => ["Authorization: $auth", "Content-Type: application/json", "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5)"],
    CURLOPT_POSTFIELDS => $payload, CURLOPT_SSL_VERIFYPEER => false
]);
$resp = curl_exec($ch); 
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
curl_close($ch);

// Only attempt to decode and insert if the Meroshare API responded with a successful 200 status
if ($http_code === 200 && !empty($resp)) {
    $msData = json_decode($resp, true);
    if (isset($msData['object']) && is_array($msData['object'])) {
        foreach ($msData['object'] as $item) {
            $stmt = $db->prepare("INSERT OR IGNORE INTO ipo_results (dmat_num, scrip, companyName, companyShareId, applicantFormId, statusName) VALUES (:dmat, :scrip, :cname, :sid, :fid, 'Never Checked')");
            $stmt->bindValue(':dmat', $dmat_num);
            $stmt->bindValue(':scrip', $item['scrip']);
            $stmt->bindValue(':cname', $item['companyName']);
            $stmt->bindValue(':sid', $item['companyShareId']);
            $stmt->bindValue(':fid', $item['applicantFormId']);
            $stmt->execute();
        }
    }
}

// 2. Retrieve all history for this user from DB to show in the UI
$stmt_select = $db->prepare("SELECT * FROM ipo_results WHERE dmat_num = :dmat ORDER BY applicantFormId DESC LIMIT 5");
$stmt_select->bindValue(':dmat', $dmat_num);
$results = $stmt_select->execute();

$list = [];
if ($results) {
    while($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $list[] = $row;
    }
}

// 3. Exactly the same JSON output format your JS expects
header('Content-Type: application/json');
echo json_encode($list);
