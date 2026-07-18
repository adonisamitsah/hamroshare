<?php
set_time_limit(0);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_notification_manager.php'; 
require_once __DIR__ . '/class_auth_manager.php'; 

if (php_sapi_name() !== 'cli') {
    http_response_code(403); 
    die(alertMessage('danger',"Access Denied: This script can only be executed via the command line.",true));
}

// ==========================================
// 1. PID LOCK (Prevent Cron Overlap)
// ==========================================
$lockFile = sys_get_temp_dir() . '/renewal_monitor.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "Renewal Monitor is already running. Skipping execution to prevent overlap.", true));
}

if (!should_run_cron($db, 'account_renewal_reminder', $skipReason)) {
    echo alertMessage('skip', "Account Renewal Reminder skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

$numberofdays = 15; // Tracking evaluation range window threshold

try {
    echo alertMessage('init', "Starting MeroShare & Demat Account Renewal Monitor Engine...", true);

    $query = "SELECT * FROM users WHERE is_active=1";
    $results = $db->query($query);
    echo alertMessage('action', "Fetched user records for evaluation.", true);
    $detailed_logs = []; 
    $today = new DateTime('today');

    // Initialize AuthManager outside the loop for performance
    $authManager = new AuthManager($db);

    // KPI Summary Counters
    $totalProfilesChecked = 0;
    $expiredMeroShare = 0;
    $expiringMeroShare = 0;
    $expiredDemat = 0;
    $expiringDemat = 0;

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $name = $row['name'] ?? $row['username'];
        $dmatNum = $row['dmat_num'] ?? 'N/A';
        $userId = $row['id'];
        $userIssues = [];
        $detailsJson = json_decode($row['ownDetails'], true);
        $needsLiveCheck = false;

       

        // --- LOOKAHEAD TRIGGER LOGIC ---
        if (empty($detailsJson)) {
            $needsLiveCheck = true; // No data exists at all
        } else {
            // Peek at MeroShare Date
            $msStr = $detailsJson['expiredDateStr'] ?? null;
            
            if ($msStr && (int)$today->diff(new DateTime($msStr))->format('%r%a') <= $numberofdays) {
                $needsLiveCheck = true;
            }
            // Peek at Demat Date (Only if we haven't already triggered a fetch)
            if (!$needsLiveCheck) {
                $dematStr = $detailsJson['dematExpiryDate'] ?? null;
                
                if ($dematStr) {
                    $convAd = bs2ad($dematStr);
                    
                    if ($convAd && (int)$today->diff(new DateTime($convAd))->format('%r%a') <= $numberofdays) {
                        $needsLiveCheck = true;
                    }
                }
            }
        }

        // --- LIVE CDSC FETCH EXECUTION ---
        if ($needsLiveCheck) {
            echo alertMessage('action', "Verification required for {$name}. Fetching live MeroShare data...", true);
            
            // Note: We pass $row so AuthManager has the password/clientId it needs
            $token = $authManager->getToken($row); 
            
            if ($token) {
                usleep(500000); // 0.5s jitter to prevent MeroShare rate-limiting
                
                // Execute the fetch and update the database natively
                fetchMeroShareOwnDetails_v2($token, $userId, $db);
                
                // Re-pull the freshly saved JSON from the database
                $freshData = $db->querySingle("SELECT ownDetails FROM users WHERE id = {$userId}");
                $detailsJson = json_decode($freshData, true);
            } else {
                echo alertMessage('error', "Login failed for {$name}. Cannot verify expiry.", true);
            }
        }

        // --- SAFETY FALLBACK ---
        // If it's STILL empty (e.g., login failed, CDSC is down), skip the math.
        if (empty($detailsJson)) {
            continue; 
        }

        $totalProfilesChecked++; // Increment total verifiable profiles

        // --- STRATEGY A: MEROSHARE PORTAL LOGIN EXPIRY ---
        $msExpiryStr = $detailsJson['expiredDateStr'] ?? null;
        if (!empty($msExpiryStr)) {
            $msExpiryDate = new DateTime($msExpiryStr);
            $msInterval = $today->diff($msExpiryDate);
            $msDaysRemaining = (int)$msInterval->format('%r%a');

            if ($msDaysRemaining < 0) {
                $expiredMeroShare++;
                $userIssues[] = ['type' => 'MeroShare', 'days' => $msDaysRemaining, 'ad_date' => $msExpiryStr];
            } elseif ($msDaysRemaining >= 0 && $msDaysRemaining <= $numberofdays) {
                $expiringMeroShare++;
                $userIssues[] = ['type' => 'MeroShare', 'days' => $msDaysRemaining, 'ad_date' => $msExpiryStr];
            }
        }

        // --- STRATEGY B: DEMAT SYSTEM EXPIRY ---
        $dematExpiryStr = $detailsJson['dematExpiryDate'] ?? null;
        if (!empty($dematExpiryStr)) {
            $convertedAdExpiry = bs2ad($dematExpiryStr);

            if ($convertedAdExpiry !== null) {
                $dematExpiryDate = new DateTime($convertedAdExpiry);
                $dematInterval = $today->diff($dematExpiryDate);
                $dematDaysRemaining = (int)$dematInterval->format('%r%a');

                if ($dematDaysRemaining < 0) {
                    $expiredDemat++;
                    $userIssues[] = ['type' => 'Demat', 'days' => $dematDaysRemaining, 'bs_date' => $dematExpiryStr, 'ad_date' => $convertedAdExpiry];
                } elseif ($dematDaysRemaining >= 0 && $dematDaysRemaining <= $numberofdays) {
                    $expiringDemat++;
                    $userIssues[] = ['type' => 'Demat', 'days' => $dematDaysRemaining, 'bs_date' => $dematExpiryStr, 'ad_date' => $convertedAdExpiry];
                }
            }
        }

        // Save structured record metrics if any issue arrays exist
        if (!empty($userIssues)) {
            $detailed_logs[] = [
                'name'    => $name,
                'userId'  => $userId,
                'dmatNum' => $dmatNum,
                'issues'  => $userIssues
            ];
        }
    }

    // --- STEP 3: SECURE CONSTANTS TRANSLATION LAYOUT DISPATCH ---
    $totalAlertsCount = count($detailed_logs);

    if ($totalAlertsCount > 0) {
        
        $secureToken = bin2hex(random_bytes(32));
        
        $reportWrapper = [
            'generated_at'     => date('Y-m-d H:i:s'),
            'lookahead_window' => $numberofdays,
            'detailed_logs'    => $detailed_logs
        ];
        
        $saveStmt = $db->prepare("INSERT OR REPLACE INTO constant (key, value) VALUES (:key, :val)");
        $saveStmt->bindValue(':key', 'renewal_report_' . $secureToken, SQLITE3_TEXT);
        $saveStmt->bindValue(':val', json_encode($reportWrapper), SQLITE3_TEXT);
        $saveStmt->execute();

        // Safe BASE_URL Formatting
        $baseUrl = defined('BASE_URL') ? BASE_URL : 'https://localhost/hamroshare';
        $webLink = rtrim($baseUrl, '/') . "/cr_display_account_renewal_reminder.php?token=" . $secureToken;
        $webLink = getShortUrl($db, $webLink);
        // Clean, programmatic notification builder
        $alertMessage = alertMessage('init', "*Meroshare/Demat Expiry*\n");
        
        $alertMessage .= alertMessage('waiting', "Horizon: `$numberofdays Days`");
        $alertMessage .= alertMessage('users', "Affected Profiles: `$totalAlertsCount / $totalProfilesChecked`");

        if ($expiringMeroShare > 0 || $expiredMeroShare > 0) {
            $alertMessage .= alertMessage('applied', "MeroShare -> Expiring: `$expiringMeroShare` | Expired: `$expiredMeroShare`");
        }
        if ($expiringDemat > 0 || $expiredDemat > 0) {
            $alertMessage .= alertMessage('success', "Demat -> Expiring: `$expiringDemat` | Expired: `$expiredDemat`");
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
        echo alertMessage('complete', "Run finished cleanly. 0 anomalies found across $totalProfilesChecked profiles.", true);
    }

   mark_cron_run($db, 'account_renewal_reminder', 'SUCCESS');

} catch (Exception $e) {
    echo alertMessage('fatal', "Execution halted: " . $e->getMessage(), true);
    cron_log($db, 'SYSTEM', 'ERROR', 'EXCEPTION', $e->getMessage(), 'RENEWAL_MONITOR');
    mark_cron_run($db, 'account_renewal_reminder', 'FAILED');
}

// Release the PID lock
flock($lockFp, LOCK_UN);
echo alertMessage('done', "Automation Loop Ended Clear", true);