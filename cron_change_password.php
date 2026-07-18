<?php
// password_rotation.php
set_time_limit(0);

// ==========================================
// 1. CORE INCLUDES & SETUP
// ==========================================
require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_auth_manager.php';
require_once __DIR__ . '/class_notification_manager.php'; 

if (php_sapi_name() !== 'cli') {
    http_response_code(403); 
    die(alertMessage('danger', "Access Denied: This script can only be executed via the command line.", true));
}

// ==========================================
// 2. PID LOCK (Prevent Overlap)
// ==========================================
$lockFile = sys_get_temp_dir() . '/password_rotation.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "MeroShare Guardian is already running. Skipping execution.", true));
}

if (!should_run_cron($db, 'password_rotation', $skipReason)) {
    echo alertMessage('skip', "Password Rotation skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

// ==========================================
// 3. MAIN ENGINE EXECUTION
// ==========================================
echo alertMessage('init', "Starting Automated Account Refresh Engine...", true);

$authManager = new AuthManager($db);
$passwordChangeInterval = 35;

// OPTIMIZATION 1: Get the exact count using a dedicated, memory-light SQL query
$totalUsers = $db->querySingle("SELECT COUNT(*) FROM users WHERE is_active=1") ?: 0;

// Now run the actual data query
$query = "SELECT id, username, password, dmat_num, name, ownDetails, Authorization, lastLogin, clientId FROM users WHERE is_active=1";
$results = $db->query($query);

$today = new DateTime('today');

$metrics = [
    'success' => 0, 
    'info'    => 0
];
$failedNames = []; 

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $uid = $row['id'];
    $fullName = $row['name'] ?? $row['username'];
    $firstName = $fullName; 
    $oldPass = $row['password'];

    $details = json_decode($row['ownDetails'], true);
    $activeToken = null; // OPTIMIZATION 2: Cache the token per-user to save DB queries

    // --- STEP 1: CHECK IF DATA IS MISSING OR OLD ---
    if (!$details || empty($details['passwordExpiryDateStr'])) {
        echo alertMessage('info', "[$firstName] Missing vault data. Fetching...", true);
        
        $activeToken = $authManager->getToken($row);
        if (!$activeToken) {
            cron_log($db, $fullName, 'ERROR', 'AUTH_FAILED', "Failed to retrieve Auth token. Skipping.", 'CHANGE PASSWORD');
            $failedNames[] = $firstName;
            continue; 
        }

        $details = fetchMeroShareOwnDetails_v2($activeToken, $uid, $db);
        if (!$details) {
            cron_log($db, $fullName, 'ERROR', 'FAILED', "API Error fetching details. Skipping user.", 'CHANGE PASSWORD');
            $failedNames[] = $firstName;
            continue;
        }
        
        $metrics['info']++;
        echo alertMessage('success', "[$firstName] Data synced.", true);
    }

    // --- STEP 2: EVALUATE EXPIRY TARGET ---
    $expiryDate = new DateTime($details['passwordExpiryDateStr']);
    $daysRemaining = (int)$today->diff($expiryDate)->format('%r%a');

    echo alertMessage('waiting', "Checking User: $firstName ($daysRemaining days left)", true);

    // Trigger if expiring in 35 days OR if it's already expired (<= 0)
    if ($daysRemaining <= $passwordChangeInterval) {
        
        // Use cached token if we grabbed it in Step 1, otherwise fetch a fresh one
        if (!$activeToken) {
            $activeToken = $authManager->getToken($row);
        }
        
        if (!$activeToken) {
            cron_log($db, $fullName, 'ERROR', 'AUTH_FAILED', "Auth token missing during rotation attempt.", 'CHANGE PASSWORD');
            $failedNames[] = $firstName;
            continue;
        }

        $newPass = getSuggestedPassGuardian($oldPass, $fullName);
        echo alertMessage('tool', "[$firstName] Executing Security Update...", true);

        // Execute Rotation
        $rotation = executeMeroSharePasswordRotation_v2($activeToken, $oldPass, $newPass);

        if ($rotation['success']) {
            $db->exec("UPDATE users SET password = '".$db->escapeString($newPass)."' WHERE id = $uid");
            fetchMeroShareOwnDetails_v2($activeToken, $uid, $db); // Hard Reset Expiry Date
            
            $metrics['success']++;
            echo alertMessage('success', "[$firstName] Account Secured.", true);
        } else {
            cron_log($db, $fullName, 'ERROR', 'FAILED', "API Error: {$rotation['message']} (HTTP {$rotation['http_code']})", 'CHANGE PASSWORD');
            $failedNames[] = $firstName;
            echo alertMessage('error', "[$firstName] Update Failed.", true);
        }
    }

    // API Jitter (0.5 to 1 seconds) to prevent rate-limiting
    usleep(rand(500000, 1000000)); 
}

// ==========================================
// 4. SECURE NOTIFICATIONS & CLEANUP
// ==========================================
$failedCount = count($failedNames);

if ($failedCount === 0) {
    mark_cron_run($db, 'password_rotation', 'SUCCESS');
} else {
    mark_cron_run($db, 'password_rotation', 'FAILED');
}

// SILENT HOUSEKEEPING: Only notify if an actual rotation was attempted or failed.
if ($metrics['success'] > 0 || $failedCount > 0) {
    
    // OPTIMIZATION 3: Clean Markdown formatting for WhatsApp/Telegram
    $alertMessage = "🛡️ *Password Change Notice*\n\n";


    if ($metrics['success'] > 0) {
        $alertMessage .= "✅ *Success:* `{$metrics['success']} / {$totalUsers}`\n";
    }

    if ($failedCount > 0) {
        $alertMessage .= "❌ *Failed:* `{$failedCount} / {$totalUsers}`\n\n";
        $alertMessage .= "Manual verification required for:\n";
        
        // TRUNCATION LOGIC: Cap the list to 10 names to protect Chat API limits
        $displayLimit = 10;
        $displayedNames = array_slice($failedNames, 0, $displayLimit);
        
        $alertMessage .= "• " . implode("\n• ", $displayedNames) . "\n";
        
        if ($failedCount > $displayLimit) {
            $remaining = $failedCount - $displayLimit;
            $alertMessage .= "\n_...and {$remaining} more accounts._\n";
        }
    }

    echo alertMessage('start', "Dispatching Notification Report...", true);
    
    try {
        $notifier = new NotificationManager();
        
        $tgResults = $notifier->sendTelegramMessage($alertMessage);
        if (is_string($tgResults) && stripos($tgResults, 'error') !== false) {
            echo alertMessage('error', "Telegram alert failed: " . $tgResults, true);
        } else {
            echo alertMessage('success', "Telegram report delivered.", true);
        }

        $waResults = $notifier->sendWhatsAppMessage($alertMessage);
        if (is_string($waResults) && stripos($waResults, 'error') !== false) {
            echo alertMessage('error', "WhatsApp alert failed: " . $waResults, true);
        } else {
            echo alertMessage('success', "WhatsApp report delivered.", true);
        }

    } catch (Exception $notifEx) {
        echo alertMessage('fatal', "CRITICAL ERROR: Notification Manager crashed.", true);
    }
} else {
    echo alertMessage('completed', "Status: Idle. Routine checks complete, no rotations required.", true);
}

// Release the PID lock
flock($lockFp, LOCK_UN);
echo alertMessage('done', "Security Engine Shutdown Complete.", true);
?>