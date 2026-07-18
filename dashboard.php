<?php
require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// --- HANDLE FILE DELETION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_install') {
    $installPath = __DIR__ . '/install.php';
    if (file_exists($installPath)) {
        @unlink($installPath); // The @ suppresses errors if permissions block deletion
    }
    // Refresh the page to remove the banner
    header("Location: ./dashboard.php");
    exit;
}

// --- 1. FETCH ALL METRICS VIA FUNCTION ---
$metrics = getDashboardMetrics($db) ?: [];

// Extract routing tokens safely
$t_allshares  = $metrics['tokens']['allshares'] ?? '';
$t_edis       = $metrics['tokens']['edis'] ?? '';
$t_renewal    = $metrics['tokens']['renewal'] ?? '';
$t_web        = $metrics['tokens']['web'] ?? '';
$t_scanner    = $metrics['tokens']['scanner'] ?? '';
$t_ipoResults = $metrics['tokens']['ipoResults'] ?? '';

// Strictly cast these to numbers (float/int) so number_format() doesn't crash
$wealthLtp          = (float)($metrics['wealth']['ltp'] ?? 0);
$totalAccounts      = (int)($metrics['wealth']['accounts'] ?? 0);
$edisTransfers      = (int)($metrics['edis']['transfers'] ?? 0);
$edisErrors         = (int)($metrics['edis']['errors'] ?? 0);
$totalRenewalIssues = (int)($metrics['renewals']['total'] ?? 0);

// Enforce arrays
$urgentRenewals     = is_array($metrics['renewals']['urgent'] ?? []) ? ($metrics['renewals']['urgent'] ?? []) : [];
$scannerLogs        = is_array($metrics['scanner_logs'] ?? []) ? ($metrics['scanner_logs'] ?? []) : [];

// --- 2. FETCH IPO ENGINE DATA ---
$ipoEngineData   = getIpoResultTableDashboard($db) ?: [];
$ipoDataSummary  = is_array($ipoEngineData['top_5'] ?? []) ? ($ipoEngineData['top_5'] ?? []) : [];
$totalActiveIpos = (int)($ipoEngineData['total_active'] ?? 0);

// --- 3. FETCH MARKET SIGNALS LOGIC ---
$signalData = get_market_signals(__DIR__ . '/market_cache') ?: [];
$globalStats = is_array($signalData['stats'] ?? []) ? ($signalData['stats'] ?? []) : ['bullish' => 0, 'bearish' => 0, 'neutral' => 0, 'total' => 0];
$analyzedScrips = is_array($signalData['scrips'] ?? []) ? ($signalData['scrips'] ?? []) : [];

// --- 4. SYSTEM SECURITY & CONFIG CHECKS ---
$hasInstallPhp = file_exists(__DIR__ . '/install.php');
$needsEnv = !file_exists(__DIR__ . '/.env');

// --- 5. CRON HEARTBEAT CHECK ---
$cronHeartbeatFile = __DIR__ . '/.cron_heartbeat';
$cronWarning = false;
$cronLastRunText = "Never";

if (!file_exists($cronHeartbeatFile)) {
    $cronWarning = true; // Has never run
} else {
    $lastRunTime = (int)file_get_contents($cronHeartbeatFile);
    $timeSinceLastRun = time() - $lastRunTime;

    // If it hasn't run in the last 25 hours (90000 seconds), flag a warning
    if ($timeSinceLastRun > 90000) {
        $cronWarning = true;
    }
    // Format for the UI
    $cronLastRunText = date('Y-m-d H:i:s', $lastRunTime);
}

echo sudo_get_header("dashboard");
?>

<div class="max-w-[1500px] mx-auto p-4 lg:p-8 space-y-8">

    <div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4 border-b border-slate-800 pb-6">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tight">Hamroshare Command Center</h1>
            <p class="text-slate-400 text-sm mt-1">Real-time status monitor, core system heuristics, and secure execution routes.</p>
        </div>
        <div class="flex items-center gap-3 bg-[#161b22] border border-slate-800 px-4 py-2 rounded-xl shadow-lg">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
            </span>
            <span class="text-xs font-bold text-slate-300 font-mono tracking-widest uppercase">Token-Authorized Session</span>
        </div>
    </div>

    <!-- SYSTEM ALERTS LAYER -->
    <?php if ($hasInstallPhp || $needsEnv): ?>
        <div class="flex flex-col gap-4">

            <?php if ($hasInstallPhp): ?>
                <div class="bg-rose-500/10 border border-rose-500/50 rounded-2xl p-5 flex flex-col sm:flex-row items-start gap-4 shadow-lg animate-pulse">
                    <div class="text-rose-500 mt-1"><i class="fas fa-exclamation-triangle text-2xl"></i></div>
                    <div class="flex-1">
                        <h3 class="text-rose-400 font-bold tracking-tight text-lg">Critical Security Vulnerability</h3>
                        <p class="text-slate-300 text-sm mt-1">The <code class="bg-rose-900/50 px-1.5 py-0.5 rounded text-rose-300 font-mono text-xs">install.php</code> file is still present in your root directory. Anyone accessing this file can reset your database and overwrite your system.</p>
                        <div class="mt-3 flex items-center gap-3">
                            <span class="text-white font-bold text-[10px] uppercase tracking-widest bg-rose-500/20 px-2 py-1 rounded border border-rose-500/30">Action Required</span>
                            <span class="text-slate-400 text-xs">Delete this file immediately.</span>
                        </div>
                    </div>
                    <form method="POST" class="mt-4 sm:mt-0 shrink-0">
                        <input type="hidden" name="action" value="delete_install">
                        <button type="submit" onclick="return confirm('Are you sure you want to permanently delete install.php?');" class="bg-rose-600 hover:bg-rose-500 text-white font-bold py-2.5 px-5 rounded-xl text-sm transition-all shadow-md shadow-rose-900/20 flex items-center gap-2 border border-rose-400">
                            <i class="fas fa-trash-alt"></i> Delete File Now
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($needsEnv): ?>
                <div class="bg-amber-500/10 border border-amber-500/50 rounded-2xl p-5 flex items-start gap-4 shadow-lg">
                    <div class="text-amber-500 mt-1"><i class="fas fa-cog text-2xl"></i></div>
                    <div>
                        <h3 class="text-amber-400 font-bold tracking-tight text-lg">Environment Configuration Missing</h3>
                        <p class="text-slate-300 text-sm mt-1">The system cannot locate your <code class="bg-amber-900/50 px-1.5 py-0.5 rounded text-amber-300 font-mono text-xs">.env</code> file. WhatsApp, Telegram, and core automations will fail to execute without it.</p>
                        <p class="text-slate-400 text-xs mt-2 font-mono">Action: Rename <code class="text-amber-200">.env.example</code> to <code class="text-amber-200">.env</code> and populate your API credentials.</p>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>
    <?php if ($cronWarning): ?>
        <div class="bg-blue-500/10 border border-blue-500/50 rounded-2xl p-5 flex items-start gap-4 shadow-lg">
            <div class="text-blue-500 mt-1"><i class="fas fa-history text-2xl"></i></div>
            <div class="flex-1">
                <h3 class="text-blue-400 font-bold tracking-tight text-lg">Automation Engine Inactive</h3>
                <p class="text-slate-300 text-sm mt-1">The master cron job (<code class="bg-blue-900/50 px-1.5 py-0.5 rounded text-blue-300 font-mono text-xs">run_all_cron.php</code>) is not executing. Background data scraping, IPO checks, and WhatsApp notifications are currently paralyzed.</p>
                <div class="mt-3 flex items-center justify-between">
                    <p class="text-slate-400 text-xs font-mono">Last successful run: <span class="text-slate-200 font-bold"><?= $cronLastRunText ?></span></p>
                    <p class="text-blue-200 text-xs font-mono bg-blue-900/40 px-2 py-1 rounded border border-blue-500/30">Requires Crontab Setup</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- END SYSTEM ALERTS -->

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

        <a href="cr_display_allshares.php?token=<?= $t_allshares ?>" class="block bg-[#161b22] border border-slate-800 hover:border-blue-500/50 transition-all rounded-2xl p-6 group shadow-lg relative overflow-hidden">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center border border-blue-500/20">
                    <i class="fas fa-wallet text-lg"></i>
                </div>
                <i class="fas fa-arrow-right text-slate-700 group-hover:text-blue-400 transition-colors text-xs"></i>
            </div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Managed Portfolio Valuation</h3>
            <div class="mt-4">
                <span class="text-2xl font-black text-white tracking-tight font-mono">Rs. <?= number_format($wealthLtp, 2) ?></span>
                <p class="text-[11px] text-slate-500 mt-1 font-mono"><?= $totalAccounts ?> Global Accounts Connected</p>
            </div>
        </a>

        <a href="cr_display_edis_results.php?token=<?= $t_edis ?>" class="block bg-[#161b22] border border-slate-800 hover:border-emerald-500/50 transition-all rounded-2xl p-6 group shadow-lg relative overflow-hidden">
            <?= $edisErrors > 0 ? '<div class="absolute top-0 right-0 w-2 h-2 m-4 rounded-full bg-rose-500 animate-pulse"></div>' : '' ?>
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center border border-emerald-500/20">
                    <i class="fas fa-exchange-alt text-lg"></i>
                </div>
                <i class="fas fa-arrow-right text-slate-700 group-hover:text-emerald-400 transition-colors text-xs"></i>
            </div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">EDIS Settlement Status</h3>
            <div class="mt-4 flex items-end justify-between">
                <span class="text-2xl font-black text-white font-mono"><?= $edisTransfers ?> <span class="text-xs text-slate-500 font-normal">Cleared</span></span>
                <span class="text-xs <?= $edisErrors > 0 ? 'text-rose-400 font-bold' : 'text-slate-500' ?> font-mono"><?= $edisErrors ?> Failures</span>
            </div>
        </a>

        <a href="cr_display_account_renewal_reminder.php?token=<?= $t_renewal ?>" class="block bg-[#161b22] border border-slate-800 hover:border-amber-500/50 transition-all rounded-2xl p-6 group shadow-lg relative">
            <?= count($urgentRenewals) > 0 ? '<div class="absolute top-0 right-0 w-2 h-2 m-4 rounded-full bg-amber-500 animate-pulse"></div>' : '' ?>
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center border border-amber-500/20">
                    <i class="fas fa-clock text-lg"></i>
                </div>
                <i class="fas fa-arrow-right text-slate-700 group-hover:text-amber-400 transition-colors text-xs"></i>
            </div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Account Lifecycle Expiries</h3>
            <div class="mt-4 flex items-end justify-between">
                <span class="text-2xl font-black text-white font-mono"><?= $totalRenewalIssues ?> <span class="text-xs text-slate-500 font-normal">Tracked</span></span>
                <span class="text-xs <?= count($urgentRenewals) > 0 ? 'text-amber-400 font-bold' : 'text-slate-500' ?> font-mono"><?= count($urgentRenewals) ?> Critical</span>
            </div>
        </a>

        <a href="cr_display_ipo_results.php?token=<?= $t_web ?>" class="block bg-[#161b22] border border-slate-800 hover:border-purple-500/50 transition-all rounded-2xl p-6 group shadow-lg">
            <div class="flex justify-between items-start mb-4">
                <div class="w-10 h-10 rounded-lg bg-purple-500/10 text-purple-400 flex items-center justify-center border border-purple-500/20">
                    <i class="fas fa-poll text-lg"></i>
                </div>
                <i class="fas fa-arrow-right text-slate-700 group-hover:text-purple-400 transition-colors text-xs"></i>
            </div>
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Allotment Outcome Engine</h3>
            <div class="mt-4 flex items-end justify-between">
                <span class="text-2xl font-black text-white font-mono"><?= $totalActiveIpos ?> <span class="text-xs text-slate-500 font-normal">Monitored</span></span>
                <span class="text-xs text-slate-500 font-mono">Secure API Check</span>
            </div>
        </a>

    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="matrix_grid">
        <?php render_signal_cards($analyzedScrips); ?>
    </div>

    <div class="bg-[#161b22] border border-slate-800 rounded-3xl p-6 lg:p-8 shadow-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-slate-800 pb-4">
            <div>
                <h2 class="text-xl font-bold text-white tracking-tight flex items-center gap-2">
                    <i class="fas fa-layer-group text-blue-500 text-sm"></i> Core Corporate Issues Matrix (Top 5 Issues)
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Granular validation, confirmation checkpoints, and exception parameters aggregated from all historical data scans.</p>
            </div>
            <a href="view-all-ipo-results.php" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold bg-slate-900 border border-slate-700 hover:border-slate-500 rounded-xl text-slate-300 hover:text-white transition-all shadow-md shrink-0">
                Launch Execution Scanner <i class="fas fa-external-link-alt text-[10px]"></i>
            </a>
        </div>

        <?php if (!empty($ipoDataSummary)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse min-w-[900px]">
                    <thead>
                        <tr class="border-b border-slate-800 text-[10px] uppercase font-bold tracking-wider text-slate-500 bg-slate-900/40">
                            <th class="p-4 rounded-tl-xl">Corporate Security</th>
                            <th class="p-4 text-center">Verified</th>
                            <th class="p-4 text-center">Unverified</th>
                            <th class="p-4 text-center">Rejected</th>
                            <th class="p-4 text-center">Allotted</th>
                            <th class="p-4 text-center">Not Allotted</th>
                            <th class="p-4 text-center">Requires Reapply</th>
                            <th class="p-4 text-right rounded-tr-xl">Total Items</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50">
                        <?php foreach ($ipoDataSummary as $scrip => $info):
                            $m = $info['metrics'];
                        ?>
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="p-4">
                                    <span class="inline-block px-2 py-0.5 font-mono text-xs font-black bg-slate-800 border border-slate-700 rounded text-slate-200 mb-1"><?= htmlspecialchars($scrip) ?></span>
                                    <span class="block text-xs font-semibold text-slate-400 max-w-[240px] truncate" title="<?= htmlspecialchars($info['companyName']) ?>">
                                        <?= htmlspecialchars($info['companyName']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center font-mono">
                                    <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Verified'] > 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-slate-600' ?>">
                                        <?= $m['Verified'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center font-mono">
                                    <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Unverified'] > 0 ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse' : 'text-slate-600' ?>">
                                        <?= $m['Unverified'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center font-mono">
                                    <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Rejected'] > 0 ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'text-slate-600' ?>">
                                        <?= $m['Rejected'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center font-mono">
                                    <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Allotted'] > 0 ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20 font-black' : 'text-slate-600' ?>">
                                        <?= $m['Allotted'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center font-mono">
                                    <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Not Allotted'] > 0 ? 'bg-slate-700/50 text-slate-400' : 'text-slate-600' ?>">
                                        <?= $m['Not Allotted'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center font-mono">
                                    <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Reapply'] > 0 ? 'bg-purple-500/20 text-purple-400 border border-purple-500/40 animate-bounce' : 'text-slate-600' ?>">
                                        <?= $m['Reapply'] ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right font-mono font-bold text-slate-300">
                                    <?= $info['total_actions'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="border border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-500">
                <i class="fas fa-folder-minus text-3xl mb-3 opacity-40"></i>
                <p class="text-sm font-semibold">No operational records identified during report evaluation.</p>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($urgentRenewals) > 0 || !empty($scannerLogs)): ?>
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">

            <div class="xl:col-span-1 bg-[#161b22] border border-slate-800 rounded-3xl p-6 shadow-xl">
                <h3 class="text-xs font-bold uppercase tracking-wider text-amber-400 mb-4 flex items-center gap-2">
                    <i class="fas fa-calendar-times"></i> Critical Account Expiries (< 60 Days)
                        </h3>
                        <div class="space-y-3 max-h-[350px] overflow-y-auto pr-1 custom-scrollbar">
                            <?php if (!empty($urgentRenewals)): ?>
                                <?php foreach ($urgentRenewals as $urg): ?>
                                    <div class="bg-slate-900/60 border border-slate-800/80 p-3 rounded-xl flex justify-between items-center">
                                        <div>
                                            <p class="text-xs font-bold text-slate-200"><?= htmlspecialchars($urg['name']) ?></p>
                                            <span class="text-[9px] uppercase font-bold tracking-widest text-slate-500"><?= $urg['type'] ?> Protocol</span>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs font-black font-mono text-amber-500"><?= $urg['days'] ?> d</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-xs text-slate-600 italic">All user profiles clean.</p>
                            <?php endif; ?>
                        </div>
            </div>

            <div class="xl:col-span-2 bg-[#161b22] border border-slate-800 rounded-3xl p-6 shadow-xl">
                <h3 class="text-xs font-bold uppercase tracking-wider text-purple-400 mb-4 flex items-center gap-2">
                    <i class="fas fa-terminal"></i> Latest Exception Logs & Automation Skip Reports
                </h3>
                <div class="bg-slate-900 text-slate-400 p-4 rounded-xl font-mono text-xs border border-slate-800 shadow-inner max-h-[350px] overflow-y-auto custom-scrollbar">
                    <?php
                    if (!empty($scannerLogs)):
                        // Flatten all logs into one clean array of non-empty lines
                        $finalLogs = [];
                        foreach ($scannerLogs as $block) {
                            $lines = explode("\n", $block);
                            foreach ($lines as $line) {
                                $trimmed = trim($line);
                                if (!empty($trimmed)) {
                                    $finalLogs[] = $trimmed;
                                }
                            }
                        }

                        // Output as a flat list
                        foreach ($finalLogs as $log): ?>
                            <div class="border-b border-slate-800/60 py-2 last:border-0 text-slate-300">
                                <?= htmlspecialchars($log) ?>
                            </div>
                        <?php endforeach;
                    else: ?>
                        <div class="text-slate-600 italic">// No parsing exceptions logs reported in current transaction sequence.</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    <?php endif; ?>

</div>

<?php include('footer.php'); ?>