<?php
set_time_limit(0);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_edis_automation.php';
require_once __DIR__ . '/class_auth_manager.php'; 
require_once __DIR__ . '/class_notification_manager.php'; 

error_log("[CRON_EDIS] === Script Initiated ===");

if (php_sapi_name() !== 'cli') {
    error_log("[CRON_EDIS] CRITICAL: Access denied. Script not run via CLI.");
    http_response_code(403); 
    die(alertMessage('denied',"Access Denied: This script can only be executed via the command line."));
}

// ==========================================
// FIX 3: PID LOCK (Prevent Cron Overlap)
// ==========================================
$lockFile = sys_get_temp_dir() . '/edis_automation.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    error_log("[CRON_EDIS] NOTICE: Script already running. PID Lock active. Aborting.");
    die(alertMessage('notice',"Notice: EDIS Automation is already running. Skipping execution to prevent overlap."));
}
error_log("[CRON_EDIS] PID Lock acquired successfully.");

if (!should_run_cron($db, 'edis_automation', $skipReason)) {
    error_log("[CRON_EDIS] SKIPPED: Condition not met - " . $skipReason);
    echo alertMessage('skipped',"Edis Automation skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

echo alertMessage('init',"Starting EDIS Automation",true) . PHP_EOL;

$startupDelay = rand(1, 2);
error_log("[CRON_EDIS] Staggering startup by {$startupDelay} seconds.");
echo alertMessage('notice',"Staggering startup by {$startupDelay} seconds to prevent API collision...", true);
sleep($startupDelay);

$authManager = new AuthManager($db);

$query = "SELECT * FROM users WHERE is_active=1;"; 
$results = $db->query($query);
if (!$results) {
    error_log("[CRON_EDIS] CRITICAL: Database query failed to fetch active users: " . $db->lastErrorMsg());
}

// 1. Initialize Reporting Matrices
$companyPayload = []; 
$metrics = [
    'profiles_scanned' => 0,
    'successful_transfers' => 0,
    'failed_transfers' => 0,
    'danger_alerts' => 0,
    'auth_failures' => 0
];
$hasContent = false;

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $name = $row['name'] ?? $row['username'];
    $dmat = $row['dmat_num'];
    $metrics['profiles_scanned']++;

    error_log("[CRON_EDIS] ---> Processing User: {$name} [DMAT: {$dmat}]");
    echo alertMessage('user',"Processing User: $name... ", true);

    $token = $authManager->getToken($row);

    if (!$token) {
        error_log("[CRON_EDIS] AUTH FAILED: Could not retrieve token for {$name}.");
        echo alertMessage('failed','Login Failed', true); 
        cron_log($db, $name, 'ERROR', 'AUTH', "Failed to retrieve Auth token for user: $name", 'EDIS_ENGINE');
        
        $metrics['auth_failures']++;
        
        $companyPayload[$dmat] = [
            'name' => $name,
            'report' => [
                'errors' => [alertMessage('failed','Authentication failed. Please check credentials or refresh token.')]
            ]
        ];
        $hasContent = true; 
        continue; 
    }

    error_log("[CRON_EDIS] Auth Success for {$name}. Launching EDIS Pipeline.");
    echo alertMessage('success',"Authenticated. Running EDIS Pipeline... ", true);

    try {
        $edis = new EDISAutomation($db, $dmat, $token);
        $report = $edis->run(); 
        
        error_log("[CRON_EDIS] EDIS Pipeline completed for {$name}. Payload: " . json_encode($report));
        
        $companyPayload[$dmat] = [
            'name' => $name,
            'report' => $report
        ];
        $hasContent = true;

        // Tally Metrics for Summary
        if (!empty($report['danger_obligations'])) {
            $metrics['danger_alerts'] += count($report['danger_obligations']);
            error_log("[CRON_EDIS] Danger Obligations found for {$name}: " . implode(", ", $report['danger_obligations']));
        }

        if (isset($report['transfers_done']) && $report['transfers_done'] > 0) {
            $metrics['successful_transfers'] += $report['transfers_done'];
            error_log("[CRON_EDIS] Successful Transfers for {$name}: {$report['transfers_done']}");
        }
        
        if (!empty($report['errors'])) {
            $metrics['failed_transfers'] += count($report['errors']);
            error_log("[CRON_EDIS] Transfer Errors for {$name}: " . implode(" | ", $report['errors']));
        }

        echo alertMessage('success','Done', true);

    } catch (Exception $e) {
        error_log("[CRON_EDIS] EXCEPTION THROWN for {$name}: " . $e->getMessage());
        echo alertMessage('error',"Error: " . $e->getMessage(), true);
        cron_log($db, $name, 'ERROR', 'EDIS_EXCEPTION', $e->getMessage(), 'EDIS_ENGINE');
        
        $metrics['failed_transfers']++;
        
        $companyPayload[$dmat] = [
            'name' => $name,
            'report' => [
                'errors' => [alertMessage('error','Pipeline Error: ' . $e->getMessage())]
            ]
        ];
        $hasContent = true;
    }
    
    $delay = rand(500000, 1000000); 
    usleep($delay); 
}

if ($hasContent) {
    error_log("[CRON_EDIS] Constructing final report payload.");
    $secureToken = bin2hex(random_bytes(32)); 
    
    $currentTime = function_exists('get_local_time') ? get_local_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    
    $reportWrapper = [
        'generated_at'   => $currentTime,
        'companyPayload' => $companyPayload
    ];

    $keyName = 'edis_report_' . $secureToken;
    $jsonValue = json_encode($reportWrapper);

    $insertStmt = $db->prepare("INSERT INTO constant (key, value) VALUES (:key, :val)");
    $insertStmt->bindValue(':key', $keyName, SQLITE3_TEXT);
    $insertStmt->bindValue(':val', $jsonValue, SQLITE3_TEXT);
    
    if (!$insertStmt->execute()) {
        error_log("[CRON_EDIS] CRITICAL: Failed to save report to database. Error: " . $db->lastErrorMsg());
    } else {
        error_log("[CRON_EDIS] Report saved to DB with token: {$secureToken}");
    }

    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://localhost/hamroshare';
    $webLink = "$baseUrl/cr_display_edis_results.php?token=$secureToken";
    $webLink = getShortUrl($db, $webLink);
    
    echo alertMessage('success', "Generated secure report link: $webLink", true);

    $totalActionable = $metrics['successful_transfers'] + 
                       $metrics['danger_alerts'] + 
                       $metrics['auth_failures'] + 
                       $metrics['failed_transfers'];

    if ($totalActionable > 0) {
        error_log("[CRON_EDIS] Actionable metrics found ({$totalActionable}). Preparing notifications.");
        
        $alertMessage = "⚙️ *EDIS Automation*\n";
        $alertMessage .= "_Scanned {$metrics['profiles_scanned']} profiles_\n\n";
        
        if ($metrics['successful_transfers'] > 0) $alertMessage .= "✅ Completed: `{$metrics['successful_transfers']}`\n";
        if ($metrics['danger_alerts'] > 0) $alertMessage .= "⚠️ WACC Pending: `{$metrics['danger_alerts']}`\n";
        if ($metrics['auth_failures'] > 0) $alertMessage .= "🚫 Auth Failed: `{$metrics['auth_failures']}`\n";
        if ($metrics['failed_transfers'] > 0) $alertMessage .= "❌ System Errors: `{$metrics['failed_transfers']}`\n";
        
        $alertMessage .= "\n🔗 [View Report]($webLink)";

        echo alertMessage('start', "Dispatching EDIS Automation Alert...", true);
        try {
            $notifier = new NotificationManager();
            
            error_log("[CRON_EDIS] Dispatching Telegram alert...");
            $tgResults = $notifier->sendTelegramMessage($alertMessage);
            if (is_string($tgResults) && stripos($tgResults, 'error') !== false) {
                error_log("[CRON_EDIS] TELEGRAM ERROR: " . $tgResults);
                echo alertMessage('error', "Telegram alert failed: " . $tgResults, true);
                cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION SERVICE', "Telegram alert failed: " . $tgResults, 'CRON ALERT');
            } else {
                error_log("[CRON_EDIS] Telegram alert successful.");
                echo alertMessage('success', "Telegram alert dispatched.", true);
            }

            error_log("[CRON_EDIS] Dispatching WhatsApp alert...");
            $waResults = $notifier->sendWhatsAppMessage($alertMessage);
            if (is_string($waResults) && stripos($waResults, 'error') !== false) {
                error_log("[CRON_EDIS] WHATSAPP ERROR: " . $waResults);
                echo alertMessage('error', "WhatsApp alert failed: " . $waResults, true);
                cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION SERVICE', "WhatsApp alert failed: " . $waResults, 'CRON ALERT');
            } else {
                error_log("[CRON_EDIS] WhatsApp alert successful.");
                echo alertMessage('success', "WhatsApp alert dispatched.", true);
            }

        } catch (Exception $notifEx) {
            $notifError = $notifEx->getMessage();
            error_log("[CRON_EDIS] FATAL NOTIFICATION CRASH: " . $notifError);
            echo alertMessage('fatal', "CRITICAL ERROR: Notification Manager crashed - " . $notifError, true);
            cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION MANAGER', "Crash during alert dispatch: " . $notifError, 'CRON ALERT');
        }
    } else {
        error_log("[CRON_EDIS] Idle state: Profiles scanned but no actions required.");
        echo alertMessage('completed', "Status: Idle. Profiles scanned but no EDIS transfers or errors required notification.", true);
    }
} else {
    error_log("[CRON_EDIS] No content generated during execution loop.");
    echo alertMessage('notice',"No status updates or transfers processed across client base during this execution window.");
}

mark_cron_run($db, 'edis_automation', 'SUCCESS'); 

flock($lockFp, LOCK_UN);
error_log("[CRON_EDIS] === Script Completed Successfully. Lock Released. ===");
echo alertMessage('completed',"--- Automation Loop Ended Clear ---") . PHP_EOL;