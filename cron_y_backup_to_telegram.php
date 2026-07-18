<?php
// cron_backup_to_telegram.php
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
// 2. PID LOCK (Prevent Overlap & ZIP Corruption)
// ==========================================
$lockFile = sys_get_temp_dir() . '/backup.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "System Backup is already running. Skipping execution to prevent overlap.", true));
}

echo alertMessage('start', "CRON STARTED: Full System Backup", true);

if (!should_run_cron($db, 'backup', $skipReason)) {
    echo alertMessage('skip', "Backup skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    exit();
}

echo alertMessage('init', "Starting backup process...", true);
$timestamp = time();

// File paths
$dbPath = __DIR__ . "/data.sqlite"; 
$tmpDbPath = sys_get_temp_dir() . "/backup_" . $timestamp . ".msbak";
$tmpZipPath = sys_get_temp_dir() . "/full_backup_" . $timestamp . ".zip";
$sourceDir = __DIR__; 

$errors = [];
$notifier = new NotificationManager();

try {
    // --- PART 1: Database Backup (.msbak) ---
    echo alertMessage('action', "Creating database snapshot...", true);
    if (copy($dbPath, $tmpDbPath)) {
        echo alertMessage('waiting', "Uploading DB snapshot to Telegram...", true);
        
        $caption = "🗄️ Database Backup: " . date("Y-m-d H:i:s");
        
        // Pass 'true' as the 3rd parameter to target TELEGRAM_CHANNEL_ID
        $dbResults = $notifier->sendTelegramDocument($tmpDbPath, $caption, true);
        processBackupResult($dbResults, 'Database Backup', $errors, $db);

        unlink($tmpDbPath);
    } else {
        $err = "Failed to copy data.sqlite to temporary directory.";
        echo alertMessage('error', $err, true);
        cron_log($db, 'SYSTEM', 'ERROR', 'BACKUP CRON', $err, 'CRON EXCEPTION');
        $errors[] = $err;
    }

    // --- PART 2: Full Directory Backup (.zip) ---
    echo alertMessage('action', "Creating full source code ZIP archive...", true);
    $zip = new ZipArchive();
    if ($zip->open($tmpZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST 
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            if ($filePath === false) continue;

            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // Optimization: Prevent temporary backups, git, node_modules, and logs from bloating the zip
            $ext = $file->getExtension();
            $filename = $file->getFilename();
            
            if (
                strpos($relativePath, '.git/') === 0 || 
                strpos($relativePath, 'node_modules/') === 0 ||
                $ext === 'msbak' || 
                $ext === 'zip' ||
                $filename === 'error_log' ||
                $filename === 'data.sqlite-journal' ||
                $filename === 'data.sqlite.sqbpro'
            ) {
                continue;
            }

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }
        $zip->close();
        
        echo alertMessage('success', "ZIP archive created successfully.", true);
        echo alertMessage('waiting', "Uploading ZIP to Telegram...", true);

        $caption = "📦 Full Source Code Backup: " . date("Y-m-d H:i:s");
        
        // Pass 'true' as the 3rd parameter to target TELEGRAM_CHANNEL_ID
        $zipResults = $notifier->sendTelegramDocument($tmpZipPath, $caption, true);
        processBackupResult($zipResults, 'Source Code ZIP', $errors, $db);
        
        unlink($tmpZipPath); 

    } else {
        $err = "Failed to create ZIP archive.";
        echo alertMessage('error', $err, true);
        cron_log($db, 'SYSTEM', 'ERROR', 'BACKUP CRON', $err, 'CRON EXCEPTION');
        $errors[] = $err;
    }

} catch (Exception $e) {
    $err = "FATAL EXCEPTION: " . $e->getMessage();
    echo alertMessage('error', $err, true);
    cron_log($db, 'SYSTEM', 'ERROR', 'BACKUP CRON', $err, 'CRON EXCEPTION');
    $errors[] = $err;
}

// --- PART 3: Final Status Logging & Notifications ---
if (empty($errors)) {
    echo alertMessage('completed', "CRON COMPLETED SUCCESSFULLY.", true);
    mark_cron_run($db, 'backup', 'SUCCESS'); 
} else {
    echo alertMessage('warning', "CRON COMPLETED WITH ERRORS.", true);
    mark_cron_run($db, 'backup', 'FAILED');
    
    // Send Failure Summary Alert to your phone
    $alertMessage = alertMessage('failed', "*Backup System Failure*");
    $alertMessage .= "One or more critical errors occurred during the automated backup process:\n\n";
    foreach ($errors as $error) {
        $alertMessage .= "• `" . $error . "`\n";
    }
    
    try {
        $notifier->sendTelegramMessage($alertMessage);
    } catch (Exception $notifEx) {
        echo alertMessage('error', "Failed to send backup error notification.", true);
    }
}

// Release the PID lock
flock($lockFp, LOCK_UN);
?>