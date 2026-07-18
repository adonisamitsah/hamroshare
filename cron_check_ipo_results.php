<?php
set_time_limit(0);

// 1. Core Includes
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_auth_manager.php';
require_once __DIR__ . '/class_notification_manager.php'; 

// ==========================================
// 1. CLI & PID LOCK PROTECTION
// ==========================================
if (php_sapi_name() !== 'cli') {
    http_response_code(403); 
    die(alertMessage('danger', "Access Denied: This script can only be executed via the command line.", true));
}

$lockFile = sys_get_temp_dir() . '/ipo_results.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "IPO Results Verification is already running. Skipping to prevent ledger overlap.", true));
}

if (!should_run_cron($db, 'check_ipo_results', $skipReason)) {
    echo alertMessage('skip', "IPO Results Check skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

// ==========================================
// 2. UNIQUE HELPER FUNCTIONS
// ==========================================

function fetch_mero_applicant_forms_v2($token) {
    $sync_url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/active/search/";
    $payload = json_encode([
        "filterFieldParams" => [], "page" => 1, "size" => 5, 
        "searchRoleViewConstants" => "VIEW_APPLICANT_FORM_COMPLETE",
        "filterDateParams" => []
    ]);

    $ch = curl_init($sync_url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, 
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: $token", "Content-Type: application/json", "User-Agent: Mozilla/5.0"],
        CURLOPT_POSTFIELDS => $payload, 
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $sync_resp = curl_exec($ch);
    $sync_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($sync_code === 200 && !empty($sync_resp)) ? json_decode($sync_resp, true) : false;
}

function fetch_mero_report_detail_v2($token, $formId) {
    $report_url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/report/detail/$formId";
    $ch = curl_init($report_url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: $token", "User-Agent: Mozilla/5.0"],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 15
    ]);
    $resp = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($http_code === 200 && !empty($resp)) ? json_decode($resp, true) : false;
}

function dispatch_ipo_result_alerts_v2($message, $notifier, $db) {
    try {
        $tgResults = $notifier->sendTelegramMessage($message);
        if (is_string($tgResults) && stripos($tgResults, 'error') !== false) {
            echo alertMessage('error', "Telegram alert failed: " . $tgResults, true);
            cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION SERVICE', "Telegram alert failed: " . $tgResults, 'CRON ALERT');
        } else {
            echo alertMessage('success', "Telegram alert dispatched.", true);
        }

        $waResults = $notifier->sendWhatsAppMessage($message);
        if (is_string($waResults) && stripos($waResults, 'error') !== false) {
            echo alertMessage('error', "WhatsApp alert failed: " . $waResults, true);
            cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION SERVICE', "WhatsApp alert failed: " . $waResults, 'CRON ALERT');
        } else {
            echo alertMessage('success', "WhatsApp alert dispatched.", true);
        }
    } catch (Exception $notifEx) {
        $notifError = $notifEx->getMessage();
        echo alertMessage('fatal', "CRITICAL ERROR: Notification Manager crashed - " . $notifError, true);
        cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION MANAGER', "Crash during alert dispatch: " . $notifError, 'CRON ALERT');
    }
}

// ==========================================
// 3. MAIN ENGINE EXECUTION
// ==========================================

echo alertMessage('init', "Starting MeroShare IPO Verification Engine...", true);

$authManager = new AuthManager($db);
$notifier = new NotificationManager();

$metrics = [
    'manual_interventions'   => 0,
    'bank_verifications'     => 0,
    'successful_allotments'  => 0,
    'monitoring_accounts'    => 0,
    'rejected_applications'  => 0
];

$companyPayload = []; 
$telegramAlerts = []; 
$hasContent = false;

$user_query = "SELECT id, username, password, dmat_num, name, Authorization, lastLogin, clientId FROM users WHERE is_active=1"; 
$user_results = $db->query($user_query);

while ($user = $user_results->fetchArray(SQLITE3_ASSOC)) {
    $uid = $user['id'];
    $name = $user['name'] ?? $user['username'];
    $dmat_num = $user['dmat_num'];

    echo alertMessage('action', "Scanning Portfolio: $name...", true);

    // --- 1. TOKEN LIFECYCLE MANAGEMENT ---
    $token = $authManager->getToken($user);
    if (!$token) {
        echo alertMessage('error', "[$name] Authentication failed. Skipping.", true);
        continue;
    }

    // --- 2. DISCOVERY & LOCAL SYNCHRONIZATION ---
    $msData = fetch_mero_applicant_forms_v2($token);
    
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

    // --- 3. TARGET POLL RUNTIME QUEUE (Hard Limit 5) ---
    // Place this inside Section 3 before starting the user loop
$records_query = $db->query("SELECT * FROM ipo_results WHERE dmat_num='$dmat_num' ORDER BY applicantFormId DESC LIMIT 5");
    
    while ($row = $records_query->fetchArray(SQLITE3_ASSOC)) {
        $id = $row['id'];
        $scrip = $row['scrip'];
        $companyName = $row['companyName'];
        $formId = $row['applicantFormId'];
        $current_status = trim($row['statusName']);
        $last_updated = strtotime($row['last_updated']);
        
        $should_check = false;

        // State Machine Decision
        if ($current_status === 'Never Checked' || $current_status === 'Unverified') {
            $should_check = true;
        } elseif ($current_status === 'Verified') {
            if ((time() - $last_updated) > 43200) { $should_check = true; } 
        } elseif ($current_status === 'Alloted' || $current_status === 'Not Alloted') {
            $should_check = false; 
        } else {
            $should_check = true; 
        }

        if (!$should_check) continue;

        usleep(500000); // API Jitter (0.5s) to prevent CDSC blocks
        $apiData = fetch_mero_report_detail_v2($token, $formId);
        if (!$apiData || !isset($apiData['statusName'])) continue;

        $new_status = trim($apiData['statusName']);
        $applied_kitta = isset($apiData['appliedKitta']) ? (float)$apiData['appliedKitta'] : 0;
        $received_kitta = isset($apiData['receivedKitta']) ? (int)$apiData['receivedKitta'] : 0;
        $amount = isset($apiData['amount']) ? (float)$apiData['amount'] : 0;
        
        // Safely calculate allotted amount preventing Division by Zero
        $alloted_amount = ($applied_kitta > 0) ? ($amount / $applied_kitta) * $received_kitta : 0;

        if (!isset($companyPayload[$scrip])) {
            $companyPayload[$scrip] = [
                'meta' => ['scrip' => $scrip, 'companyName' => $companyName],
                'statuses' => []
            ];
        }

        // --- 4. DATA MATRIX INGESTION & STATE MACHINE CHANGES ---
        if ($new_status !== $current_status) {
            $hasContent = true;

            if ($current_status === 'Verified' && $new_status !== 'Alloted' && $new_status !== 'Not Alloted') {
                $metrics['manual_interventions']++;
                $telegramAlerts[] = "🚨 *Critical Status Reversion:* `$name` ➔ `$scrip` dropped back to *{$new_status}*!";
            }

            if ($new_status === 'Verified') {
                $metrics['bank_verifications']++;
            } elseif ($new_status === 'Alloted') {
                $metrics['successful_allotments']++;
                
                // Trigger Financial Ledger Entry
                initiateLedgerEntry(
                    $db, 
                    $dmat_num, 
                    "Allotment of $scrip (Kitta: $received_kitta)", 
                    0, 
                    $alloted_amount, 
                    'IPO_ALLOTMENT'
                );
                
                echo alertMessage('success', "[$name] Alloted $received_kitta kitta of $scrip!", true);
            } elseif ($new_status === 'Rejected') {
                $metrics['rejected_applications']++;
            } else {
                $metrics['monitoring_accounts']++;
            }

            $companyPayload[$scrip]['statuses'][$new_status][] = [
                'name'  => $name,
                'dmat'  => $dmat_num,
                'kitta' => $received_kitta,
                'old'   => $current_status
            ];

            $update_stmt = $db->prepare("UPDATE ipo_results SET statusName = :status, receivedKitta = :kitta, last_updated = CURRENT_TIMESTAMP WHERE id = :id");
            $update_stmt->bindValue(':status', $new_status);
            $update_stmt->bindValue(':kitta', $received_kitta);
            $update_stmt->bindValue(':id', $id);
            $update_stmt->execute();
            
        } else {
            // No status change, just update the timestamp so we don't spam CDSC
            $db->exec("UPDATE ipo_results SET last_updated = CURRENT_TIMESTAMP WHERE id = $id");
        }
    }
}

// ==========================================
// 5. SECURE WEB LAYER ENGINE INTERFACE
// ==========================================

if ($hasContent) {
    
    // 5.1 Generate Cryptographically Unpredictable Access Token
    $secureToken = bin2hex(random_bytes(32)); 
    
    $reportWrapper = [
        'generated_at'   => date('Y-m-d H:i:s'),
        'companyPayload' => $companyPayload
    ];

    $keyName = 'web_report_' . $secureToken;
    $jsonValue = json_encode($reportWrapper);

    // Atomic Cache Update (INSERT OR REPLACE)
    $insertStmt = $db->prepare("INSERT OR REPLACE INTO constant (key, value) VALUES (:key, :val)");
    $insertStmt->bindValue(':key', $keyName, SQLITE3_TEXT);
    $insertStmt->bindValue(':val', $jsonValue, SQLITE3_TEXT);
    $insertStmt->execute();

    // 5.2 Build Public Web Link Reference URL string
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://localhost:8000';
    $webLink = "$baseUrl/cr_display_ipo_results.php?token=" . $secureToken;
    $webLink = getShortUrl($db, $webLink);
    
    // 5.3 Build Streamlined Notification Summary Text Frame
    $alertMessage = "🔔 *Share Results*\n\n";

    // 1. Inject Individual User Alerts (if any exist)
    if (!empty($telegramAlerts)) {
        $alertMessage .= implode("\n", $telegramAlerts) . "\n\n";
    }

    // 2. The Vertical List Matrix

    $alertMessage .= "🎉 Allotted: `{$metrics['successful_allotments']}`\n";
    $alertMessage .= "❌ Rejected: `{$metrics['rejected_applications']}`\n";
    $alertMessage .= "✅ Verified: `{$metrics['bank_verifications']}`\n";
    $alertMessage .= "📋 Unverified: `{$metrics['monitoring_accounts']}`\n";
    
    // 3. Conditional Alert (Only shows if there's a problem)
    if ($metrics['manual_interventions'] > 0) {
        $alertMessage .= "🚨 Manual Action Needed: `{$metrics['manual_interventions']}`\n";
    }
    
    // 4. Sleek Call-to-Action
    $alertMessage .= "\n🔗 [View Report]($webLink)";

    // Output to Console
    echo alertMessage('start', "Dispatching sleek MeroShare Results Ledger...", true);

    dispatch_ipo_result_alerts_v2($alertMessage, $notifier, $db);

} else {
    echo alertMessage('completed', "Status: Idle. No updates found across client base portfolios.", true);
}

mark_cron_run($db, 'check_ipo_results', 'SUCCESS'); 

// Release the PID lock
flock($lockFp, LOCK_UN);
echo alertMessage('done', "IPO Verification Automation Loop Ended Clear", true);
?>