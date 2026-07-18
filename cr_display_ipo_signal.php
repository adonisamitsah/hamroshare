<?php
// cr_display_ipo_signal.php
// Ultimate Quantitative Trading Dashboard

require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_report_viewer.php';
require_once __DIR__ . '/class_ipo_signal_report.php';

// Validate static token securely
$providedToken = $_GET['token'] ?? '';
$isValidToken = false;
for ($i = 0; $i <= 3; $i++) {
    $checkDate = date('Y-m-d', strtotime("-{$i} days"));
    if ($providedToken === md5($checkDate . "IPO_DASHBOARD_SECRET")) {
        $isValidToken = true;
        break;
    }
}

if (!$isValidToken) {
    http_response_code(403);
    die("<div style='background:#0a0a0a; height:100vh; display:flex; align-items:center; justify-content:center;'><h2 style='color:#ef4444; font-family:monospace; text-align:center;'>[403] UNAUTHORIZED ACCESS<br><span style='font-size:14px; color:#6b7280;'>Token is invalid or expired.</span></h2></div>");
}



$report = new IpoSignalReport($db, $providedToken);
$report->render();