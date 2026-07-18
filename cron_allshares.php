<?php
// cron_allshares.php
set_time_limit(0);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_auth_manager.php';
require_once __DIR__ . '/class_notification_manager.php'; 

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die(alertMessage('danger', "Access Denied: This script can only be executed via the command line.", true));
}

// ==========================================
// 1. PID LOCK (Prevent Cron Overlap)
// ==========================================
$lockFile = sys_get_temp_dir() . '/allshares.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "Global Portfolio Sync is already running. Skipping execution to prevent overlap.", true));
}

if (!should_run_cron($db, 'allshares', $skipReason)) {
    echo alertMessage('skip', "All Shares Sync skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

echo alertMessage('init', "Starting Global Portfolio Sync Engine...", true);

$authManager = new AuthManager($db);
$query = "SELECT * FROM users WHERE is_active=1;"; 
$results = $db->query($query);

// 1. Fetch the raw string from the environment (falling back to an empty string if missing)
$envString = $_ENV['SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION'] ?? getenv('SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION') ?? '';

// 2. Convert to an array and strip any accidental whitespace around the names
$skipClients = $envString ? array_map('trim', explode(',', $envString)) : [];

// Reporting Matrices
$portfolioPayload = [];

$metrics = [
    'total_accounts' => 0,
    'total_value_ltp' => 0,
    'total_value_pcp' => 0,
    'errors' => 0,
    'skipped' => 0,
    'skippedNamesList' => [] // <--- Added here
];
$hasContent = false;

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $name = $row['name'] ?? $row['username'];
    $dmat = $row['dmat_num'];

    // 1. Skip Logic
   if (in_array($name, $skipClients)) {
    $metrics['skipped']++;
    $metrics['skippedNamesList'][] = $name; // <--- Pushed directly to metrics
    echo alertMessage('skipped', "Skipping User (Blacklisted): $name", true);
    continue;
}

    echo alertMessage('action', "Processing User: $name...", true);

    // 2. Auth Management
    $token = $authManager->getToken($row);

    if (!$token) {
        echo alertMessage('error', "Auth Failed for $name.", true);
        cron_log($db, $name, 'ERROR', 'AUTH', "Failed to retrieve Auth token for user: $name", 'PORTFOLIO_ENGINE');
        $metrics['errors']++;
        continue; 
    }

    // 3. Fetch Portfolio Data
    $clientCode = substr($dmat, 3, 5);
    $url = "https://webbackend.cdsc.com.np/api/meroShareView/myPortfolio/";
    $payloadData = json_encode([
        "sortBy" => "CCY_SHORT_NAME",
        "demat" => [$dmat],
        "clientCode" => $clientCode,
        "page" => 1,
        "size" => 200,
        "sortAsc" => true
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POSTFIELDS => $payloadData,
        CURLOPT_HTTPHEADER => [
            "Authorization: {$token}",
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36"
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($resp, true);

    // 4. Validate and Process Data
    if ($httpCode === 200 && isset($json['meroShareMyPortfolio'])) {
        
        // Save to Database (using your existing function or a direct update)
        if (function_exists('sudo_put_myshare')) {
            sudo_put_myshare($dmat, $resp, $db);
        } else {
            // Fallback direct update if function is missing
            $stmt = $db->prepare("UPDATE users SET myshare = :share, myshare_time = :time WHERE dmat_num = :dmat");
            $stmt->bindValue(':share', $resp, SQLITE3_TEXT);
            $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
            $stmt->bindValue(':dmat', $dmat, SQLITE3_TEXT);
            $stmt->execute();
        }

        // Add to Reporting Matrix
        $portfolioPayload[$dmat] = [
            'name' => $name,
            'portfolio' => $json
        ];
        
        $metrics['total_accounts']++;
        $metrics['total_value_ltp'] += (float)($json['totalValueOfLastTransPrice'] ?? 0);
        $metrics['total_value_pcp'] += (float)($json['totalValueOfPrevClosingPrice'] ?? 0);
        $hasContent = true;

        echo alertMessage('success', "Synced Successfully.", true);
    } else {
        echo alertMessage('error', "API Error / No Data for $name.", true);
        $metrics['errors']++;
    }

    // ==========================================
    // API JITTER (Rate-Limit Protection)
    // Random delay between 0.5s and 1s
    // ==========================================
    usleep(rand(500000, 1000000));
}

// 5. SECURE WEB LAYER ENGINE INTERFACE
if ($hasContent) {
    
    // Generate Security Token
    $secureToken = bin2hex(random_bytes(32)); 
    
    $reportWrapper = [
        'generated_at'   => date('Y-m-d H:i:s'),
        'metrics'        => $metrics,
        'companyPayload' => $portfolioPayload
    ];

    $keyName = 'allshares_report_' . $secureToken;
    $jsonValue = json_encode($reportWrapper);

    // Insert directly into DB Constant Table (Phantom Delete Removed)
    $insertStmt = $db->prepare("INSERT INTO constant (key, value) VALUES (:key, :val)");
    $insertStmt->bindValue(':key', $keyName, SQLITE3_TEXT);
    $insertStmt->bindValue(':val', $jsonValue, SQLITE3_TEXT);
    $insertStmt->execute();

    // Safe BASE_URL Formatting
    $baseUrl = defined('BASE_URL') ? BASE_URL : 'https://localhost/hamroshare';
    $webLink = rtrim($baseUrl, '/') . "/cr_display_allshares.php?token=" . $secureToken;
    $webLink = getShortUrl($db, $webLink);

    // ==========================================
    // HISTORICAL BASELINE LOGIC FOR DAILY CHANGE
    // ==========================================
    $baselinePcp = (float)$metrics['total_value_pcp']; // Default to CDSC
    
    // Scan the database for the previous report
    $query = "SELECT value FROM constant WHERE key LIKE 'allshares_report_%' AND key != :currentKey";
    $stmt = $db->prepare($query);
    $stmt->bindValue(':currentKey', $keyName, SQLITE3_TEXT);
    $result = $stmt->execute();

    $latestPrevTime = 0;
    $prevLtp = null;
    $currentGeneratedAt = $reportWrapper['generated_at'];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data = json_decode($row['value'], true);
        if (!$data || !isset($data['generated_at'])) continue;

        $genTime = strtotime($data['generated_at']);
        $currTime = strtotime($currentGeneratedAt);

        // Find the absolute latest report that happened BEFORE this current one
        if ($genTime < $currTime && $genTime > $latestPrevTime) {
            $latestPrevTime = $genTime;
            $prevLtp = $data['metrics']['total_value_ltp'] ?? null;
        }
    }

    // Override the CDSC previous close if we found a valid historical report
    if ($prevLtp !== null) {
        $baselinePcp = (float)$prevLtp;
    }

    // Calculate Global Change using the matched baseline
    $changeAmt = (float)$metrics['total_value_ltp'] - $baselinePcp;
    $changePct = ($baselinePcp > 0) ? ($changeAmt / $baselinePcp) * 100 : 0;
    $trendIcon = ($changeAmt >= 0) ? "📈" : "📉";

    // Clean, programmatic notification builder
    $alertMessage = alertMessage('money', "*All Shares*\n");
    $alertMessage .= alertMessage('users', "Total Accounts: `{$metrics['total_accounts']}`");
    $alertMessage .= alertMessage('wealth', "Total Valuation: `Rs. " . number_format($metrics['total_value_ltp'], 2) . "`");
    $alertMessage .= "{$trendIcon} Daily Change: `" . ($changeAmt >= 0 ? '+' : '') . number_format($changePct, 2) . "%`\n";
    
    if ($metrics['skipped'] > 0) {
        $alertMessage .= "⏭️ *Skipped:* `{$metrics['skipped']}`\n";
    }
    if ($metrics['errors'] > 0) {
        $alertMessage .= alertMessage('warning', "Errors: `{$metrics['errors']}`");
    }
    
    $alertMessage .= "\n" . alertMessage('report', "[View Report]($webLink)");

    echo alertMessage('start', "Initializing Notification Manager...", true);
    
    try {
        $notifier = new NotificationManager();
        
        // Dispatch Telegram
        $tgResults = $notifier->sendTelegramMessage($alertMessage);
        if (is_string($tgResults) && strpos(strtolower($tgResults), 'error') !== false) {
            echo alertMessage('error', "Telegram alert failed: " . $tgResults, true);
            cron_log($db, 'SYSTEM', 'ERROR', 'NOTIFICATION SERVICE', "Telegram alert failed: " . $tgResults, 'CRON ALERT');
        } else {
            echo alertMessage('success', "Telegram alert dispatched.", true);
        }

        // Dispatch WhatsApp
        $waResults = $notifier->sendWhatsAppMessage($alertMessage);
        if (is_string($waResults) && strpos(strtolower($waResults), 'error') !== false) {
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
} else {
    echo alertMessage('notice', "No portfolio data was updated.", true);
}

mark_cron_run($db, 'allshares', 'SUCCESS'); 

// Release the PID lock
flock($lockFp, LOCK_UN);
echo alertMessage('done', "Portfolio Sync Loop Ended Clear", true);