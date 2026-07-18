<?php

require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

$dmat_num = $_POST['dmat_num'] ?? '';
$user = $db->query("SELECT * FROM users WHERE dmat_num='$dmat_num' LIMIT 1")->fetchArray(SQLITE3_ASSOC);

// The payload for the search API you captured
$payload = json_encode([
    "filterFieldParams" => [
        ["key" => "companyShare.companyIssue.companyISIN.script", "alias" => "Scrip"],
        ["key" => "companyShare.companyIssue.companyISIN.company.name", "alias" => "Company Name"]
    ],
    "page" => 1, "size" => 200,
    "searchRoleViewConstants" => "VIEW_APPLICANT_FORM_COMPLETE"
]);

$ch = curl_init('https://webbackend.cdsc.com.np/api/meroShare/applicantForm/active/search/');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Authorization: " . $user['Authorization'], "Content-Type: application/json"],
    CURLOPT_POSTFIELDS => $payload
]);

echo curl_exec($ch);
curl_close($ch);
?>