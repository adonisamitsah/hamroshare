<?php
set_time_limit(0);

// 1. Core Includes
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_notification_manager.php';

// ==========================================
// 1. CLI PROTECTION
// ==========================================
if (php_sapi_name() !== 'cli') {
    http_response_code(403); 
    die(alertMessage('danger', "Access Denied: This script can only be executed via the command line.", true));
}

// ==========================================
// 2. PID LOCK (Prevent Overlap)
// ==========================================
// CRITICAL: VACUUM locks the entire DB. We cannot allow overlap here.
$lockFile = sys_get_temp_dir() . '/maintenance.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "Database Maintenance is already running. Skipping execution.", true));
}

if (!should_run_cron($db, 'maintenance', $skipReason)) {
    echo alertMessage('skip', "Maintenance skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

echo alertMessage('init', "STARTING DATABASE MAINTENANCE & OPTIMIZATION Engine...", true);

try {
    // ---------------------------------------------------------------------
    // STEP 1: PRUNE SYSTEM LOGS (Keep latest 500)
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Pruning system_logs (Keeping newest 500 records)...", true);
    $db->exec("DELETE FROM system_logs WHERE id NOT IN (SELECT id FROM system_logs ORDER BY id DESC LIMIT 500)");
    $deletedLogs = $db->changes();
    echo alertMessage('success', "Removed {$deletedLogs} old system log records.", true);

    // ---------------------------------------------------------------------
    // STEP 2: PRUNE IPO RESULTS (Older than 3 months)
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Pruning ipo_results (Older than 3 months)...", true);
    $db->exec("DELETE FROM ipo_results WHERE last_updated <= datetime('now', '-3 months')");
    $deletedIpos = $db->changes();
    if ($deletedIpos > 0) {
        echo alertMessage('success', "Removed {$deletedIpos} old IPO results.", true);
    } else {
        echo alertMessage('info', "No old IPO results needed pruning.", true);
    }

    // ---------------------------------------------------------------------
    // STEP 3: CLEAN CONSTANT TABLE REPORTS (JSON Parsing)
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Cleaning up Report JSONs in 'constant' table...", true);
    $reportPrefixes = ['web_report_', 'renewal_report_', 'scanner_report_', 'edis_report_', 'allshares_report_'];
    $totalReportsDeleted = 0;

    foreach ($reportPrefixes as $prefix) {
        // Uses SQLite's native JSON engine to parse the dates instantly without loading into PHP memory
        $query = "DELETE FROM constant WHERE key LIKE '{$prefix}%' AND key NOT IN (
            SELECT key FROM constant WHERE key LIKE '{$prefix}%'
            ORDER BY json_extract(value, '$.generated_at') DESC LIMIT 7
        )";
        $db->exec($query);
        $totalReportsDeleted += $db->changes();
    }

    // Handle ipo_decision_xxx (Keep latest 5 based on internal rowid)
    $db->exec("DELETE FROM constant WHERE key LIKE 'ipo_decision_%' AND rowid NOT IN (
        SELECT rowid FROM constant WHERE key LIKE 'ipo_decision_%' ORDER BY rowid DESC LIMIT 5
    )");
    $totalReportsDeleted += $db->changes();

    echo alertMessage('success', "Removed {$totalReportsDeleted} old report payload records.", true);

    // ---------------------------------------------------------------------
    // STEP 4: LINE-AWARE LOG FILE SLICING
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Checking cron_debug.log size constraints...", true);
    $logFile = __DIR__ . '/cron_debug.log';
    
    // Only slice if file exists and is larger than 200 KB (204,800 bytes)
    if (file_exists($logFile) && filesize($logFile) > 204800) { 
        $lines = file($logFile);
        $totalLines = count($lines);
        
        // Grab exactly the bottom half of the lines (prevents cutting text mid-sentence)
        $keepLines = array_slice($lines, floor($totalLines / 2)); 
        file_put_contents($logFile, implode("", $keepLines));
        
        echo alertMessage('success', "Truncated cron_debug.log perfectly by 50% lines.", true);
    } else {
        echo alertMessage('info', "cron_debug.log is under 200KB limit.", true);
    }

    // ---------------------------------------------------------------------
    // STEP 4.1: LINE-AWARE LOG FILE SLICING
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Checking cron_debug.log size constraints...", true);
    $logFile = __DIR__ . '/error_log';
    
    // Only slice if file exists and is larger than 200 KB (204,800 bytes)
    if (file_exists($logFile) && filesize($logFile) > 204800) { 
        $lines = file($logFile);
        $totalLines = count($lines);
        
        // Grab exactly the bottom half of the lines (prevents cutting text mid-sentence)
        $keepLines = array_slice($lines, floor($totalLines / 2)); 
        file_put_contents($logFile, implode("", $keepLines));
        
        echo alertMessage('success', "Truncated error_log perfectly by 50% lines.", true);
    } else {
        echo alertMessage('info', "error_log is under 200KB limit.", true);
    }    

    // ---------------------------------------------------------------------
    // STEP 5: ORPHANED LOCK FILE CLEANUP
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Clearing orphaned cron lock files...", true);
    $tempDir = sys_get_temp_dir();
    $lockFiles = glob($tempDir . '/*.lock');
    $deletedLocks = 0;
    $now = time();

    foreach ($lockFiles as $file) {
        // NEVER delete the current running maintenance lock!
        if (basename($file) === 'maintenance.lock') continue;

        // If a lock file is older than 24 hours (86400 seconds), it's a crashed orphaned file
        if (is_file($file) && ($now - filemtime($file)) > 86400) { 
            unlink($file);
            $deletedLocks++;
        }
    }
    if ($deletedLocks > 0) {
        echo alertMessage('success', "Cleared {$deletedLocks} crashed/orphaned lock files.", true);
    }

    // ---------------------------------------------------------------------
    // STEP 6: WAL CHECKPOINT
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Checkpointing WAL file...", true);
    $db->exec('PRAGMA wal_checkpoint(TRUNCATE);');
    echo alertMessage('success', "WAL Checkpoint Complete.", true);

    // ---------------------------------------------------------------------
    // STEP 7: DATABASE DEFRAGMENTATION (VACUUM)
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Vacuuming and defragmenting database...", true);
    $db->exec('VACUUM;');
    echo alertMessage('success', "Database Vacuumed. Reclaimed all empty space.", true);

    // ---------------------------------------------------------------------
    // STEP 8: QUERY PLANNER OPTIMIZATION
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Optimizing query planner statistics...", true);
    $db->exec('PRAGMA optimize;');
    echo alertMessage('success', "Query Planner Optimized.", true);

    mark_cron_run($db, 'maintenance', 'SUCCESS');

} catch (Exception $e) {
    echo alertMessage('fatal', "Maintenance Error: " . $e->getMessage(), true);
    
    // Attempt to log the failure if the DB isn't completely locked
    try {
        cron_log($db, 'SYSTEM', 'CRITICAL', 'DB_MAINTENANCE', "Maintenance script crashed: " . $e->getMessage(), 'CRON EXCEPTION');
        mark_cron_run($db, 'maintenance', 'FAILED');
    } catch (Exception $logException) {
        // Silent fail if the DB is completely inaccessible (e.g., corrupted)
    }
    
    // Optional: Ping yourself on Telegram if the entire DB engine crashes
    try {
        $notifier = new NotificationManager();
        $notifier->sendTelegramMessage(alertMessage('danger', " **CRITICAL DB MAINTENANCE FAILURE:**\n`" . $e->getMessage() . "`"));
    } catch (Exception $notifEx) {
        // Silent
    }
}

// ---------------------------------------------------------------------
    // STEP 3.5: PRUNE EXPIRED SHORT URLs (Older than 90 days)
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Pruning short_urls (Older than 30 days)...", true);
    $db->exec("DELETE FROM short_urls WHERE created_at <= datetime('now', '-90 days')");
    $deletedUrls = $db->changes();
    
    if ($deletedUrls > 0) {
        echo alertMessage('success', "Removed {$deletedUrls} expired short URLs.", true);
    } else {
        echo alertMessage('info', "No expired short URLs needed pruning.", true);
    }

// ---------------------------------------------------------------------
    // STEP 4: ORPHANED MARKET CACHE CLEANUP
    // ---------------------------------------------------------------------
    echo alertMessage('action', "Scanning for orphaned market cache files...", true);

    // Fetch the raw skip string from the environment
    $envString = $_ENV['SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION'] ?? getenv('SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION') ?? '';
    $skipClients = $envString ? array_map('trim', explode(',', $envString)) : [];

    $activeScrips = [];
    $validPortfoliosFound = 0;

    // 1. Build the Master List of all currently held scrips across non-skipped users
    $query = "SELECT username, name, myshare FROM users WHERE is_active=1 AND myshare IS NOT NULL AND myshare != ''";
    $results = $db->query($query);

    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $clientName = !empty($row['name']) ? $row['name'] : $row['username'];
        
        // Skip parsing for ignored clients
        if (in_array($clientName, $skipClients)) {
            continue;
        }

        $rawShare = html_entity_decode($row['myshare'], ENT_QUOTES, 'UTF-8');
        $jsonStart = strpos($rawShare, '{');
        
        if ($jsonStart !== false) {
            $rawShare = substr($rawShare, $jsonStart);
            $shareData = json_decode($rawShare, true);
            
            if (is_array($shareData) && isset($shareData['meroShareMyPortfolio'])) {
                $validPortfoliosFound++;
                foreach ($shareData['meroShareMyPortfolio'] as $item) {
                    if (!empty($item['script'])) {
                        // Use the scrip name as the array key for lightning-fast O(1) lookups
                        $activeScrips[$item['script']] = true; 
                    }
                }
            }
        }
    }

    // 2. Scan and Prune the Cache Directory
    $cacheDir = __DIR__ . '/market_cache';
    $deletedCacheFiles = 0;

    // Safety Net: Only proceed with deletion if we successfully parsed at least one valid portfolio.
    if ($validPortfoliosFound > 0 && is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*.json');
        
        foreach ($files as $file) {
            $scripCode = basename($file, '.json');
            
            // If the cached scrip is NOT in the master list of active holdings, axe it.
            if (!isset($activeScrips[$scripCode])) {
                if (unlink($file)) {
                    $deletedCacheFiles++;
                }
            }
        }

        if ($deletedCacheFiles > 0) {
            echo alertMessage('success', "Removed {$deletedCacheFiles} orphaned market cache files.", true);
        } else {
            echo alertMessage('info', "Market cache is perfectly synced. No orphaned files found.", true);
        }
    } else {
        echo alertMessage('warning', "Skipped cache cleanup. No valid portfolio data found to cross-reference (or all were skipped).", true);
    }


// Release the PID lock
flock($lockFp, LOCK_UN);
echo alertMessage('done', "Database Maintenance & Optimization Complete.", true);
?>