<?php
// cr_display_account_renewal_reminder.php

require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_report_viewer.php';

class RenewalReport extends BaseReportViewer {
    
    // 1. Define the report's identity
    protected string $prefix = 'renewal_report_';
    protected string $themeColor = 'amber';
    protected string $pageTitle = 'MeroShare/Dmat Expiry';

    // 2. Define the Main Content
    protected function renderMainContent(): void {
        $detailed_logs = $this->payload['detailed_logs'] ?? [];
        $lookaheadWindow = $this->payload['lookahead_window'] ?? '15';

        // --- UI/UX Stats Analytics Parsing & Optimization ---
        $statProfilesFlagged = count($detailed_logs);
        $statMeroShare = 0;
        $statDemat = 0;
        $statOverdue = 0;

        // Optimization: Calculate 'today' exactly once
        $date_today = new DateTime('today');

        // Pre-calculate all days and stats to keep the HTML clean
        foreach ($detailed_logs as &$userLog) {
            foreach ($userLog['issues'] as &$issue) {
                if ($issue['type'] === 'MeroShare') {
                    $statMeroShare++;
                } else {
                    $statDemat++;
                }
                
                $date_expiry = new DateTime($issue['ad_date']);
                $days = (int)$date_today->diff($date_expiry)->format('%r%a');
                $issue['days'] = $days; // Inject the calculated days into the array

                if ($days < 0) {
                    $statOverdue++;
                }
            }
        }
        unset($userLog, $issue); // Break reference safely

        $statTotalIssues = $statMeroShare + $statDemat;

        // --- Render the HTML Content ---
        // Note: The <main> wrapper is already provided by BaseReportViewer
        ?>
        
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 lg:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute -top-24 -right-10 w-64 h-64 bg-amber-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 relative z-10">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight text-white mb-2">
                        Account Expiry Desk
                    </h1>
                    <p class="text-xs lg:text-sm text-gray-400 font-mono flex flex-wrap items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        Horizon: <span class="text-amber-400 font-bold"><?= htmlspecialchars($lookaheadWindow) ?> Days</span>
                        <span class="text-gray-600 hidden sm:inline">|</span>
                        <span class="text-gray-500">Scan: <?= htmlspecialchars($this->executionTime) ?></span>
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 lg:gap-4 w-full md:w-auto">
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[80px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-gray-500 mb-1">Flagged</span>
                        <span class="text-xl font-black text-white font-mono"><?= $statProfilesFlagged ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[80px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-amber-500 mb-1">Expiring</span>
                        <span class="text-xl font-black text-amber-400 font-mono"><?= $statTotalIssues - $statOverdue ?></span>
                    </div>
                    <div class="bg-gray-950 border <?= $statOverdue > 0 ? 'border-red-900/50 shadow-[inset_0_0_20px_rgba(239,68,68,0.05)]' : 'border-gray-800' ?> p-3 rounded-xl text-center min-w-[80px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest <?= $statOverdue > 0 ? 'text-red-500' : 'text-gray-500' ?> mb-1">Overdue</span>
                        <span class="text-xl font-black <?= $statOverdue > 0 ? 'text-red-400' : 'text-gray-500' ?> font-mono"><?= $statOverdue ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($detailed_logs)): ?>
            <div class="bg-gray-900/50 border border-emerald-900/30 border-dashed rounded-2xl p-16 text-center flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-emerald-500/10 rounded-full flex items-center justify-center mb-4 border border-emerald-500/20">
                    <i class="fas fa-check text-2xl text-emerald-400"></i>
                </div>
                <h3 class="text-lg font-bold text-emerald-400 mb-2">All Accounts Secure</h3>
                <p class="text-gray-400 font-mono text-sm max-w-md mx-auto">Zero expiration flags or overdue renewals captured within the active <?= htmlspecialchars($lookaheadWindow) ?>-day horizon matrix.</p>
            </div>
        <?php else: ?>
            <div class="space-y-5 lg:space-y-6">
                <?php foreach ($detailed_logs as $userLog): ?>
                    <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-xl overflow-hidden group hover:border-gray-700/50 transition-colors">
                        
                        <div class="bg-gray-850 px-5 py-4 border-b border-gray-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-400 shrink-0 shadow-inner">
                                    <i class="fas fa-user text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="text-base lg:text-lg font-bold text-white tracking-tight">
                                        <?= htmlspecialchars($userLog['name']) ?>
                                    </h2>
                                    <p class="text-[10px] text-gray-500 font-mono mt-0.5">ID_REF: <?= htmlspecialchars($userLog['userId']) ?></p>
                                </div>
                            </div>
                            <div class="text-left sm:text-right bg-gray-950 sm:bg-transparent p-2 sm:p-0 rounded border border-gray-800 sm:border-none">
                                <span class="block text-[9px] uppercase text-gray-500 font-bold tracking-widest mb-0.5">DMAT Account</span>
                                <span class="text-xs sm:text-sm font-mono font-bold text-gray-300"><?= htmlspecialchars($userLog['dmatNum']) ?></span>
                            </div>
                        </div>

                        <div class="p-0 sm:p-5 divide-y divide-gray-800/60">
                            <?php foreach ($userLog['issues'] as $issue): ?>
                                <?php 
                                    $isOverdue = ($issue['days'] < 0);
                                    $isMeroShare = ($issue['type'] === 'MeroShare');
                                    
                                    $indicatorColor = $isMeroShare ? 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]' : 'bg-cyan-500 shadow-[0_0_8px_rgba(6,182,212,0.6)]';
                                    $serviceName = $isMeroShare ? 'MeroShare Portal Login' : 'C-ASBA Demat Account';
                                ?>
                                <div class="p-4 sm:p-0 sm:py-4 first:sm:pt-0 last:sm:pb-0 flex flex-col sm:flex-row sm:items-center justify-between gap-4 transition-colors hover:bg-gray-800/30 sm:hover:bg-transparent">
                                    
                                    <div class="flex items-start gap-3">
                                        <div class="mt-1.5 shrink-0">
                                            <span class="flex h-2.5 w-2.5 rounded-full <?= $indicatorColor ?>"></span>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-gray-200">
                                                <?= $serviceName ?>
                                            </h4>
                                            <div class="text-[11px] text-gray-500 font-mono mt-1 space-y-0.5">
                                                <?php if (!$isMeroShare): ?>
                                                    <p>BS_TARGET: <span class="text-gray-400 font-semibold"><?= htmlspecialchars($issue['bs_date']) ?></span></p>
                                                <?php endif; ?>
                                                <p>AD_TARGET: <span class="text-gray-400 font-semibold"><?= htmlspecialchars($issue['ad_date']) ?></span></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="self-start sm:self-center">
                                        <?php if ($isOverdue): ?>
                                            <span class="inline-flex items-center gap-1.5 bg-red-500/10 text-red-400 font-black px-3 py-1.5 rounded-lg text-xs border border-red-500/20 shadow-sm animate-pulse">
                                                <i class="fas fa-exclamation-circle text-[10px]"></i>
                                                OVERDUE <?= abs($issue['days']) ?> DAYS
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 bg-amber-500/10 text-amber-400 font-bold px-3 py-1.5 rounded-lg text-xs border border-amber-500/20">
                                                <i class="fas fa-clock text-[10px]"></i>
                                                EXPIRES IN <?= $issue['days'] ?> DAYS
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    }
}

// 3. Instantiate and execute the class
$report = new RenewalReport($db, $_GET['token'] ?? '');
$report->render();