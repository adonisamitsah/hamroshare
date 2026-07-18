<?php
set_time_limit(0);
require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
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

$lockFile = sys_get_temp_dir() . '/ipo_scanner.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "IPO Scanner is already running. Skipping execution to prevent transaction overlap.", true));
}

if (!should_run_cron($db, 'ipo_scanner', $skipReason)) {
    echo alertMessage('skip', "IPO Scanner and Auto Apply skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

// ==========================================
// 2. UNIQUE HELPER FUNCTIONS
// ==========================================

function fetch_mero_applicable_issues_v2($token)
{
    $url = "https://webbackend.cdsc.com.np/api/meroShare/companyShare/applicableIssue/";
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ["Authorization: $token", "Content-Type: application/json", "User-Agent: Mozilla/5.0"],
        CURLOPT_POSTFIELDS => '{"filterFieldParams":[],"page":1,"size":10,"searchRoleViewConstants":"VIEW_APPLICABLE_SHARE","filterDateParams":[]}',
        CURLOPT_TIMEOUT => 15
    ]);
    $resp = curl_exec($curl);
    curl_close($curl);
    return json_decode($resp, true);
}

function fetch_mero_active_details_v2($token, $companyShareId)
{
    $detailUrl = "https://webbackend.cdsc.com.np/api/meroShare/active/" . $companyShareId;
    $chDetail = curl_init($detailUrl);
    curl_setopt_array($chDetail, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ["Authorization: $token", "User-Agent: Mozilla/5.0"],
        CURLOPT_TIMEOUT => 15
    ]);
    $detailResp = curl_exec($chDetail);
    curl_close($chDetail);
    return json_decode($detailResp, true);
}

function dispatch_ipo_scanner_alerts_v2($message, $notifier, $db)
{
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

// Extracted Transaction Logic to keep the main loop clean and readable
function execute_ipo_transaction_pipeline($dmat, $companyShareId, $name, $scrip, $issue, $db)
{
    $updateStatus = sudo_update_bankDetails($dmat, $db, $companyShareId);

    if ($updateStatus !== "Success") {
        return "❌ **$name**: Failed $scrip (Bank Sync: $updateStatus)";
    }

    $blacklistedStatuses = ['edit', 'inProcess', 'reapply'];
    // CRITICAL FIX: Accept both EDIT_APPROVE and CREATE_APPROVE from the CDSC matrix
    $isReapply = (isset($issue['action']) && $issue['action'] === "reapply" && in_array($issue['statusName'], ['EDIT_APPROVE', 'CREATE_APPROVE']));

    // BRANCH A: STANDARD IPO OR RESERVED SHARE
    if (!isset($issue['action']) || !in_array($issue['action'], $blacklistedStatuses)) {
        $isReserved = false;
        if (isset($issue['shareTypeName']) && $issue['shareTypeName'] === "RESERVED") {
            $data = sudo_apply_reserved_ipo($dmat, $companyShareId, $db);
            $isReserved = true;
        } else {
            $data = sudo_apply_ipo($dmat, $companyShareId, $db);
        }

        $resp = json_decode($data, true);
        $status = $resp['status'] ?? 'UNKNOWN';
        $statusCode = $resp['statusCode'] ?? 0;
        $apiMessage = $resp['message'] ?? 'Invalid JSON Response';

        if ($statusCode === 201 || $status === 'CREATED' || $apiMessage === "Share has been applied successfully.") {
            if ($isReserved) {
                $appliedKitta = $_SESSION['reserved_ipo_applied_kitta'] ?? 0;
                return "🟣 **$name**: Right Share applied for $scrip (Qty: $appliedKitta)";
            }
            return "✅ **$name**: Applied for $scrip";
        }

        cron_log($db, $name, 'ERROR', 'APPLY_FAILED', "$scrip: $apiMessage", 'IPO_ENGINE');
        $cleanMsg = str_replace(['.', '!', '"'], '', $apiMessage);
        return "❌ **$name**: Failed $scrip ($cleanMsg)";

        // BRANCH B: RE-APPLICATION
    } elseif ($isReapply) {
        $reapplyDataJson = sudo_get_reapply_details($dmat, $companyShareId, $db);
        $reapplyDetails = json_decode($reapplyDataJson, true);

        if (is_array($reapplyDetails)) {
            $appId = $reapplyDetails['applicantFormId'] ?? null;
            $appliedKitta = $reapplyDetails['appliedKitta'] ?? 10;

            if ($appId) {
                $data = sudo_reapply_ipo($dmat, $appId, $companyShareId, $appliedKitta, $db);
                $resp = json_decode($data, true);

                $status = $resp['status'] ?? 'UNKNOWN';
                $statusCode = $resp['statusCode'] ?? 0;
                $apiMessage = $resp['message'] ?? 'Invalid JSON Response';

                if ($statusCode === 201 || $status === 'CREATED' || $apiMessage === "Share has been applied successfully.") {
                    return "⚠️ **$name**: Re-Applied for $scrip. Verify banking balances.";
                }
                cron_log($db, $name, 'ERROR', 'REAPPLY_FAILED', "$scrip: $apiMessage", 'IPO_ENGINE');
                $cleanMsg = str_replace(['.', '!', '"'], '', $apiMessage);
                return "❌ **$name**: Failed Re-Apply for $scrip ($cleanMsg)";
            }
            cron_log($db, $name, 'ERROR', 'REAPPLY_FAILED', "$scrip: No Active App ID Found", 'IPO_ENGINE');
            return "❌ **$name**: Failed Re-Apply for $scrip (No Active App ID Found)";
        }
        cron_log($db, $name, 'ERROR', 'REAPPLY_FAILED', "$scrip: Invalid Reapply Data Format", 'IPO_ENGINE');
        return "❌ **$name**: Failed Re-Apply for $scrip (Invalid CDSC Response)";

        // BRANCH C: IN-PROCESS / EDIT 
    } else {
        return "⏭️ **$name**: Skipped $scrip (Action: {$issue['action']})";
    }
}

// ==========================================
// 3. MAIN ENGINE EXECUTION
// ==========================================

try {
    echo alertMessage('init', "Starting Global MeroShare IPO Scanner...", true);

    $authManager = new AuthManager($db);
    $notifier = new NotificationManager();
    $query = "SELECT * FROM users WHERE is_active=1;";
    $results = $db->query($query);

    $new_open_ipos = [];
    $processing_report = [];

    // Pre-load all Caches (Decisions, Details, and Notification Locks) to prevent N+1 Queries
    $globalIpoCache = [];
    $globalIpoDetailsCache = [];
    $cacheQuery = $db->query("SELECT key, value FROM constant WHERE key LIKE 'ipo_decision_%' OR key LIKE 'ipo_details_%' OR key LIKE 'notified_ipo_%'");

    while ($cRow = $cacheQuery->fetchArray(SQLITE3_ASSOC)) {
        if (strpos($cRow['key'], 'ipo_details_') === 0) {
            $globalIpoDetailsCache[$cRow['key']] = json_decode($cRow['value'], true);
        } else {
            $globalIpoCache[$cRow['key']] = $cRow['value'];
        }
    }

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $name = $row['name'] ?? $row['username'];
        $dmat = $row['dmat_num'];

        echo alertMessage('action', "Processing User: $name...", true);

        // --- STEP 1: AUTHENTICATION ---
        $token = $authManager->getToken($row);
        if (!$token) {
            echo alertMessage('error', "[$name] Login Failed.", true);
            cron_log($db, $name, 'ERROR', 'AUTH', "Failed to retrieve Auth token for user: $name", 'IPO_CHECK');
            continue;
        }

        // --- STEP 2: SCAN MARKET ISSUES ---
        $json = fetch_mero_applicable_issues_v2($token);

        if (isset($json['object']) && is_array($json['object'])) {
            foreach ($json['object'] as $issue) {
                if ($issue['shareGroupName'] !== "Ordinary Shares") continue;

                $companyShareId = $issue['companyShareId'];
                $scrip = $issue['scrip'];

                $cacheKey = "ipo_decision_" . $companyShareId;
                $detailsKey = "ipo_details_" . $companyShareId;

                $shouldApply = $globalIpoCache[$cacheKey] ?? null;
                $detailJson = $globalIpoDetailsCache[$detailsKey] ?? null;

                // If missing Decision OR Details, fetch fresh
                if ($shouldApply === null || $detailJson === null) {
                    usleep(1500000); // 1.5s delay to keep CDSC API happy
                    $detailJson = fetch_mero_active_details_v2($token, $companyShareId);

                    if ($detailJson) {
                        $minUnit = $detailJson['minUnit'] ?? 10;
                        $pricePerUnit = $detailJson['sharePerUnit'] ?? 100;
                        $totalCost = $minUnit * $pricePerUnit;

                        // 1. CALCULATE GLOBAL BASELINE (Applies to everyone)
                        if ($minUnit == 10 && $totalCost <= 1000) {
                            $shouldApply = 'apply';
                        } else {
                            $shouldApply = 'skip';
                        }

                        // 2. SAVE GLOBAL BASELINE TO DB & MEMORY
                        $db->exec("INSERT OR REPLACE INTO constant (key, value) VALUES ('$cacheKey', '$shouldApply')");
                        $globalIpoCache[$cacheKey] = $shouldApply;

                        $safeDetails = $db->escapeString(json_encode($detailJson));
                        $db->exec("INSERT OR REPLACE INTO constant (key, value) VALUES ('$detailsKey', '$safeDetails')");
                        $globalIpoDetailsCache[$detailsKey] = $detailJson;
                    }
                }

                // 3. USER-SPECIFIC OVERRIDES (Evaluated instantly per-user, NEVER cached globally)
                $finalUserDecision = $shouldApply; // Start with the global standard (e.g., skip an expensive IPO)

                $isReservedIssue = (isset($issue['shareTypeName']) && $issue['shareTypeName'] === "RESERVED");
                $isReapply = (isset($issue['action']) && $issue['action'] === "reapply" && in_array($issue['statusName'], ['EDIT_APPROVE', 'CREATE_APPROVE']));

                // If this specific user has rights to apply, override the baseline decision
                if ($isReservedIssue || $isReapply) {
                    $finalUserDecision = 'apply';
                }

                // Populate Broadcast Array ONLY if it hasn't been notified yet
                $notifiedKey = 'notified_ipo_' . $scrip;
                if ($detailJson && !isset($globalIpoCache[$notifiedKey]) && !isset($new_open_ipos[$scrip])) {
                    $new_open_ipos[$scrip] = [
                        'scrip'          => $scrip,
                        'company'        => $issue['companyName'],
                        'shareGroupName' => $issue['shareGroupName'],
                        'type'           => (isset($issue['action']) && $issue['action'] == "reapply") ? "⚠️ RE-APPLY" : "🚀 NEW " . $issue['shareTypeName'],
                        'minUnit'        => $detailJson['minUnit'] ?? 0,
                        'pricePerUnit'   => $detailJson['sharePerUnit'] ?? 0,
                        'totalValue'     => ($detailJson['minUnit'] ?? 0) * ($detailJson['sharePerUnit'] ?? 0)
                    ];
                }

                // --- STEP 3: EXECUTE TRANSACTION PIPELINE ---
                if ($finalUserDecision === 'apply') {
                    $res = execute_ipo_transaction_pipeline($dmat, $companyShareId, $name, $scrip, $issue, $db);
                    $processing_report[] = $res;
                } else {
                    $processing_report[] = "⏭️ **$name**: Skipped $scrip (Criteria not met)";
                }
            }
        }
        usleep(300000);
    }

    // ==========================================
    // 4. TELEGRAM PUBLIC ISSUES BROADCAST
    // ==========================================
    if (count($new_open_ipos) > 0) {
        $issueCount = count($new_open_ipos);

        // 1. Build Minimalist Telegram/WhatsApp Payload (Pure Markdown)
        $alertMessage = "🔔 *New Issue Alert* ($issueCount)\n\n";

        foreach ($new_open_ipos as $ipo) {
            $icon = (strpos($ipo['type'], 'RE-APPLY') !== false) ? "⚠️" : "🚀";

            // Elegant 2-line summary
            $alertMessage .= "{$icon} *{$ipo['scrip']}* — _{$ipo['company']}_\n";
            $alertMessage .= "↳ Min: {$ipo['minUnit']} Units (`Rs. " . number_format($ipo['totalValue'], 2) . "`)\n\n";

            // Mark as notified in Database
            $db->exec("INSERT OR REPLACE INTO constant (key, value) VALUES ('notified_ipo_{$ipo['scrip']}', 'true')");
        }

        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://localhost/hamroshare';
        $alertMessage .= "🔗 [View Report]($baseUrl/)";

        // 2. Output to Console using your alertMessage function
        echo alertMessage('start', "Dispatching sleek IPO Broadcast for $issueCount issue(s)...", true);

        // 3. Dispatch
        dispatch_ipo_scanner_alerts_v2($alertMessage, $notifier, $db);
    }

    // ==========================================
    // 5. SECURE PRIVATE ENGINE AUDIT LINK DISPATCH
    // ==========================================
    if (!empty($processing_report)) {
        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;
        $reapplyCount = 0;
        $reservedCount = 0;

        foreach ($processing_report as $line) {
            if (strpos($line, '✅') !== false) $successCount++;
            elseif (strpos($line, '❌') !== false) $failedCount++;
            elseif (strpos($line, '⏭️') !== false) $skippedCount++;
            elseif (strpos($line, '⚠️') !== false) $reapplyCount++;
            elseif (strpos($line, '🟣') !== false) $reservedCount++;
        }

        $totalActionable = $successCount + $failedCount + $reapplyCount + $reservedCount;
        $totalProcessed = $totalActionable + $skippedCount;

        // Anti-Spam: Only send telegram if something ACTUALLY happened
        if ($totalActionable > 0) {
            $secureToken = bin2hex(random_bytes(32));

            // Use our timezone-aware function to ensure the database matches your local time
            $currentTime = function_exists('get_local_time') ? get_local_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');

            $reportWrapper = [
                'generated_at'      => $currentTime,
                'processing_report' => $processing_report
            ];

            $saveStmt = $db->prepare("INSERT OR REPLACE INTO constant (key, value) VALUES (:key, :val)");
            $saveStmt->bindValue(':key', 'scanner_report_' . $secureToken, SQLITE3_TEXT);
            $saveStmt->bindValue(':val', json_encode($reportWrapper), SQLITE3_TEXT);
            $saveStmt->execute();

            $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://localhost:8000';
            $webLink = "$baseUrl/cr_display_apply_ipo_right.php?token=$secureToken";
            $webLink = getShortUrl($db, $webLink);

            // Minimalist Elegant Message
            $summaryMsg = "⚙️ *Auto-Apply Report*\n";
            $summaryMsg .= alertMessage('user', "_Processed: $totalProcessed accounts_\n");


            $summaryMsg .= alertMessage("Success", "Applied: `$successCount`");
            $summaryMsg .= alertMessage("failed", "Failed: `$failedCount`");
            $summaryMsg .= alertMessage("reapply", "Re-Apply: `$reapplyCount`");
            $summaryMsg .= alertMessage("skipped", "Skipped: `$skippedCount`");

            if ($reservedCount > 0) {
                $summaryMsg .= "🟣 Right Shares: `$reservedCount`\n";
            }
            $summaryMsg .= "\n🔗 [View Report]($webLink)";

            echo alertMessage('start', "Dispatching sleek Private Audit Ledger...", true);
            dispatch_ipo_scanner_alerts_v2($summaryMsg, $notifier, $db);
        } else {
            echo alertMessage('info', "No actionable transactions occurred (all accounts skipped). Telegram audit suppressed.", true);
        }
    }

    mark_cron_run($db, 'ipo_scanner', 'SUCCESS');
    echo alertMessage('done', "IPO Scanner Completed.", true);
} catch (Exception $e) {
    cron_log($db, 'SYSTEM', 'ERROR', 'EXCEPTION', $e->getMessage(), 'IPO_CHECK');
    mark_cron_run($db, 'ipo_scanner', 'FAILED');
    echo alertMessage('fatal', "An error occurred: " . $e->getMessage(), true);
}

// Release the PID lock
flock($lockFp, LOCK_UN);
