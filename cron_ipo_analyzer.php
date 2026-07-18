<?php
/**
 * cron_ipo_analyzer.php - High-Performance CLI IPO Signal & Analytics Engine
 * Optimized for single-fetch global market state and persistent data caching.
 */

// ==========================================
// 1. BOOTSTRAP & CLI LOCK PROTECTION
// ==========================================
require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */
require_once __DIR__ . '/php_function.php'; 
require_once __DIR__ . '/class_notification_manager.php'; 

if (php_sapi_name() !== 'cli') {
    http_response_code(403); 
    die(alertMessage('danger', "Access Denied: Command line execution only.", true));
}

// Check new environmental toggle
$isSignalEnabled = filter_var($_ENV['IPO_SIGNAL_ENABLE'] ?? getenv('IPO_SIGNAL_ENABLE'), FILTER_VALIDATE_BOOLEAN);
if (!$isSignalEnabled) {
    die(alertMessage('skip', "Execution Terminated: IPO_SIGNAL_ENABLE is disabled.", true));
}

// PID Lock to prevent overlapping cron executions
$lockFile = sys_get_temp_dir() . '/ipo_analyzer.lock';
$lockFp = fopen($lockFile, 'c');
if (!flock($lockFp, LOCK_EX | LOCK_NB)) {
    die(alertMessage('notice', "Engine already running. Skipping execution.", true));
}

if (!should_run_cron($db, 'ipo_analyzer', $skipReason)) {
    echo alertMessage('skip', "Skipped: " . $skipReason, true);
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit();
}

// Setup core time variables
$todayStr = date('Y-m-d');
$envString = $_ENV['SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION'] ?? getenv('SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION') ?? '';
$skipClients = $envString ? array_map('trim', explode(',', $envString)) : [];

// ==========================================
// 2. PORTFOLIO DEDUPLICATION AGGREGATOR
// ==========================================
echo alertMessage('info', "Scanning local database for active IPO holdings...", true);

$query = "SELECT u.username, u.name, u.myshare, i.scrip FROM users u JOIN ipo_results i ON u.dmat_num = i.dmat_num WHERE u.is_active = 1 AND i.statusName IN ('Alloted', 'Allotted')";
$results = $db->query($query);
$uniqueScrips = [];

while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    $clientName = !empty($row['name']) ? $row['name'] : $row['username'];
    if (in_array($clientName, $skipClients)) continue;

    $rawShare = html_entity_decode($row['myshare'] ?? '', ENT_QUOTES, 'UTF-8');
    $jsonStart = strpos($rawShare, '{');
    if ($jsonStart !== false) $rawShare = substr($rawShare, $jsonStart);
    
    $shareData = json_decode($rawShare, true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($shareData['meroShareMyPortfolio'])) continue;

    foreach ($shareData['meroShareMyPortfolio'] as $portfolioItem) {
        if ($portfolioItem['script'] === $row['scrip']) {
            if (!isset($uniqueScrips[$row['scrip']])) $uniqueScrips[$row['scrip']] = ['users' => []];
            if (!in_array($clientName, $uniqueScrips[$row['scrip']]['users'])) {
                $uniqueScrips[$row['scrip']]['users'][] = $clientName;
            }
        }
    }
}

if (empty($uniqueScrips)) {
    echo alertMessage('skip', "No active IPO holdings identified.", true);
    mark_cron_run($db, 'ipo_analyzer', 'SUCCESS'); 
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    exit();
}

// ==========================================
// 3. SINGLE HTTP FETCH: GLOBAL MARKET DATA
// ==========================================
echo alertMessage('info', "Downloading global market snapshot from GitHub CDN...", true);

$marketJsonUrl = "https://raw.githubusercontent.com/Shubhamnpk/yonepse/refs/heads/main/data/nepse_data.json";
$marketJsonRaw = @file_get_contents($marketJsonUrl);

if ($marketJsonRaw === FALSE) {
    die(alertMessage('danger', "Failed to download market data from remote repository.", true));
}

// Convert global JSON array into a mapped array where the key is the stock symbol for instant O(1) lookups
$globalMarketArray = json_decode($marketJsonRaw, true);
$marketMap = [];
foreach ($globalMarketArray as $asset) {
    $marketMap[strtoupper($asset['symbol'])] = $asset;
}

// ==========================================
// 4. CACHE MERGING & SIGNAL CALCULATION
// ==========================================
$cacheDir = __DIR__ . '/market_cache';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0777, true);

$analyzedScrips = [];

foreach ($uniqueScrips as $scrip => $scripMetadata) {
    $scrip = strtoupper($scrip);
    $cacheFile = $cacheDir . "/{$scrip}.json";
    $historicalRecords = [];
    $lastCheckedDate = '';

    if (file_exists($cacheFile)) {
        $cacheJson = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cacheJson)) {
            $historicalRecords = $cacheJson['history'] ?? [];
            $lastCheckedDate = $cacheJson['last_check'] ?? '';
        }
    }

    // Update Cache if we have fresh data for this specific IPO today
    if ($lastCheckedDate !== $todayStr && isset($marketMap[$scrip])) {
        $liveData = $marketMap[$scrip];
        $tradeDate = substr($liveData['last_updated'], 0, 10); // Extract YYYY-MM-DD
        
        $newFrame = [
            "symbol"         => $scrip,
            "open"           => floatval($liveData['previous_close']), // Approximation mapping
            "high"           => floatval($liveData['high']),
            "low"            => floatval($liveData['low']),
            "close"          => floatval($liveData['ltp']),
            "f_date"         => $tradeDate,
            "percent_change" => floatval($liveData['percent_change']),
            "volume"         => intval($liveData['volume'])
        ];

        // Deduplicate and merge history
        $timelineMerge = [];
        foreach ($historicalRecords as $dayFrame) $timelineMerge[$dayFrame['f_date']] = $dayFrame; 
        $timelineMerge[$newFrame['f_date']] = $newFrame; 
        
        krsort($timelineMerge); // Sort descending by date
        $historicalRecords = array_slice(array_values($timelineMerge), 0, 60); // Constrain to 60 days
        
        // Save back to local JSON
        file_put_contents($cacheFile, json_encode([
            'last_check' => $todayStr,
            'history'    => $historicalRecords
        ], JSON_PRETTY_PRINT), LOCK_EX);
        
        echo alertMessage('success', "Cache synced for {$scrip}.", true);
    }

    // Mathematical Rating Engine
    if (!empty($historicalRecords)) {
        $waccValue = 100.00; 
        $lastTradedPrice = (float)$historicalRecords[0]['close'];
        $growthReturnPct = (($lastTradedPrice - $waccValue) / $waccValue) * 100;
        
        $historicalMaximumHigh = max(array_column($historicalRecords, 'high'));
        $trailingDrawdownPct = $historicalMaximumHigh > 0 ? (($historicalMaximumHigh - $lastTradedPrice) / $historicalMaximumHigh) * 100 : 0;
        
        $algorithmicRiskScore = 0;
        if ($growthReturnPct >= 100.0)    $algorithmicRiskScore += 40;
        if ($trailingDrawdownPct >= 10.0) $algorithmicRiskScore += 40;
        if (isset($historicalRecords[1]) && $historicalRecords[0]['percent_change'] < 0 && $historicalRecords[1]['percent_change'] < 0) {
            $algorithmicRiskScore += 20; 
        }

        $analyzedScrips[$scrip] = [
            'users'            => $scripMetadata['users'],
            'history'          => $historicalRecords,
            'score'            => $algorithmicRiskScore,
            'ltp'              => $lastTradedPrice,
            'profitPercentage' => $growthReturnPct,
            'drawdown'         => $trailingDrawdownPct
        ];
    }
}

// ==========================================
// 5. DIGEST CONSTRUCTION & BROADCAST
// ==========================================
if (!empty($analyzedScrips)) {
    // Sort highest risk to the top
    uasort($analyzedScrips, function($a, $b) { return $b['score'] <=> $a['score']; });
    
    $notificationBody = "📊 *IPO Market Signal Digest*\n\n";
    $totalProcessed = count($analyzedScrips);
    $currentIndex = 0;

    foreach ($analyzedScrips as $scripKey => $dataset) {
        $currentIndex++;
        
        $signalVerdict = "💎 HOLD POSITION";
        $signalIcon = "💎";
        if ($dataset['score'] >= 70) {
            $signalVerdict = "SELL / TAKE PROFIT";
            $signalIcon = "⚡";
        } elseif ($dataset['score'] >= 40) {
            $signalVerdict = "REDUCE RISK";
            $signalIcon = "⚠️";
        }

        $holdersOutput = $dataset['users'][0] . (count($dataset['users']) > 1 ? " (+ " . (count($dataset['users']) - 1) . " others)" : "");
        $cleanScripHeader = str_replace('_', '\_', $scripKey);
        $roiFormatted = ($dataset['profitPercentage'] >= 0 ? '+' : '') . number_format($dataset['profitPercentage'], 1) . "%";
        
        $notificationBody .= "{$signalIcon} *{$cleanScripHeader}* — `{$signalVerdict}`\n";
        $notificationBody .= "💰 LTP: `Rs. " . number_format($dataset['ltp'], 2) . "` | 📈 ROI: `{$roiFormatted}`\n";
        $notificationBody .= "📉 Drop: `-" . number_format($dataset['drawdown'], 1) . "%` | 🚨 Score: `{$dataset['score']}/100`\n";
        $notificationBody .= "👥 Accts: {$holdersOutput}\n";

        if ($currentIndex < $totalProcessed) $notificationBody .= "───────────────\n";
    }

    // Append Secure Display Dashboard Link
    $secureToken = md5($todayStr . "IPO_DASHBOARD_SECRET");
    $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://localhost:8000';
    $webLink = "$baseUrl/cr_display_ipo_signal.php?token=" . $secureToken;
    $webLink = getShortUrl($db, $webLink);
    $notificationBody .= "\n🔗 [View Report]($webLink)";

    echo alertMessage('info', "Transmitting high-priority trade digest...$webLink", true);
    
    try {
        $dispatcher = new NotificationManager();
        $dispatcher->sendTelegramMessage($notificationBody);
        $dispatcher->sendWhatsAppMessage($notificationBody);
        echo alertMessage('success', "Digest successfully broadcasted.", true);
    } catch (Exception $e) {
        echo alertMessage('danger', "Notification routing failed: " . $e->getMessage(), true);
    }
} else {
    echo alertMessage('notice', "Standby processing. No data resolved for active holdings.", true);
}

mark_cron_run($db, 'ipo_analyzer', 'SUCCESS'); 
flock($lockFp, LOCK_UN);
fclose($lockFp);
echo alertMessage('done', "IPO Analyzer Loop Ended Clear", true);