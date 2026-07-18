<?php
// cr_display_ipo_results.php

require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_report_viewer.php';

class IpoResultReport extends BaseReportViewer
{

    // 1. Define the report's identity
    protected string $prefix = 'web_report_';
    protected string $themeColor = 'emerald';
    protected string $pageTitle = 'Share Results';

    // 2. Define the Main Content
    protected function renderMainContent(): void
    {
        $companyPayload = $this->payload['companyPayload'] ?? [];

        // --- 1. IPO METRICS FOR THE TOP DASHBOARD MATRIX ---
        $ipoDataSummaryDasboard = [];
        $totalActiveIpos = 0;

        // Use $this->db to access the parent class's database connection
        if (function_exists('getIpoResultTableDashboard')) {
            $ipoEngineData = getIpoResultTableDashboard($this->db);
            $ipoDataSummaryDasboard = $ipoEngineData['top_5'] ?? [];
            $totalActiveIpos = $ipoEngineData['total_active'] ?? 0;
        }

        // --- 2. CALCULATE UI STATS ---
        $totalCompanies = count($companyPayload);
        $totalAllocations = 0;
        $uniqueAccounts = [];

        foreach ($companyPayload as $scrip => $package) {
            if (!isset($package['statuses'])) continue; // Safety check

            foreach ($package['statuses'] as $statusGroup => $accountsList) {
                foreach ($accountsList as $ac) {
                    $dmat = $ac['dmat'] ?? 'unknown';
                    $uniqueAccounts[$dmat] = true;
                    if (strpos(strtolower($statusGroup), 'allot') !== false) {
                        $totalAllocations++;
                    }
                }
            }
        }
        $totalAccountsChecked = count($uniqueAccounts);
?>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 lg:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 relative z-10">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight text-white mb-2">
                        Allocation Matrix Result
                    </h1>
                    <div class="flex items-center gap-4">
                        <p class="text-xs lg:text-sm text-gray-400 font-mono flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            Live Execution: <span class="text-gray-300 font-bold"><?= htmlspecialchars($this->executionTime) ?></span>
                        </p>
                        <a href="view-all-ipo-results.php" class="text-xs font-bold font-mono tracking-widest uppercase text-blue-400 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/20 px-3 py-1 rounded-lg transition-all shadow-inner">
                            <i class="fas fa-database mr-1"></i> Live Ledger
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 lg:gap-4 w-full md:w-auto">
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[90px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-gray-500 mb-1">Scrips</span>
                        <span class="text-xl font-black text-white font-mono"><?= $totalCompanies ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[90px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-gray-500 mb-1">Profiles</span>
                        <span class="text-xl font-black text-blue-400 font-mono"><?= $totalAccountsChecked ?></span>
                    </div>
                    <div class="bg-gray-950 border border-emerald-900/50 p-3 rounded-xl text-center min-w-[90px] shadow-[inset_0_0_20px_rgba(16,185,129,0.05)]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-emerald-600 mb-1">Allots</span>
                        <span class="text-xl font-black text-emerald-400 font-mono"><?= $totalAllocations ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-[#161b22] border border-gray-800 rounded-3xl p-6 lg:p-8 shadow-2xl">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 border-b border-gray-800 pb-4">
                <div>
                    <h2 class="text-xl font-bold text-white tracking-tight flex items-center gap-2">
                        <i class="fas fa-layer-group text-blue-500 text-sm"></i> Core Corporate Issues Matrix (Top 5 Issues)
                    </h2>
                </div>
            </div>

            <?php if (!empty($ipoDataSummaryDasboard)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[900px]">
                        <thead>
                            <tr class="border-b border-gray-800 text-[10px] uppercase font-bold tracking-wider text-gray-500 bg-gray-900/40">
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
                        <tbody class="divide-y divide-gray-800/50">
                            <?php foreach ($ipoDataSummaryDasboard as $scrip => $info):
                                $m = $info['metrics'];
                            ?>
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="p-4">
                                        <span class="inline-block px-2 py-0.5 font-mono text-xs font-black bg-gray-800 border border-gray-700 rounded text-gray-200 mb-1"><?= htmlspecialchars($scrip) ?></span>
                                        <span class="block text-xs font-semibold text-gray-400 max-w-[240px] truncate" title="<?= htmlspecialchars($info['companyName']) ?>">
                                            <?= htmlspecialchars($info['companyName']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center font-mono">
                                        <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Verified'] > 0 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'text-gray-600' ?>"><?= $m['Verified'] ?></span>
                                    </td>
                                    <td class="p-4 text-center font-mono">
                                        <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Unverified'] > 0 ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20 animate-pulse' : 'text-gray-600' ?>"><?= $m['Unverified'] ?></span>
                                    </td>
                                    <td class="p-4 text-center font-mono">
                                        <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Rejected'] > 0 ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'text-gray-600' ?>"><?= $m['Rejected'] ?></span>
                                    </td>
                                    <td class="p-4 text-center font-mono">
                                        <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Allotted'] > 0 ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20 font-black' : 'text-gray-600' ?>"><?= $m['Allotted'] ?></span>
                                    </td>
                                    <td class="p-4 text-center font-mono">
                                        <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Not Allotted'] > 0 ? 'bg-gray-700/50 text-gray-400' : 'text-gray-600' ?>"><?= $m['Not Allotted'] ?></span>
                                    </td>
                                    <td class="p-4 text-center font-mono">
                                        <span class="inline-flex justify-center min-w-[2rem] px-2.5 py-1 text-xs font-bold rounded-lg <?= $m['Reapply'] > 0 ? 'bg-purple-500/20 text-purple-400 border border-purple-500/40 animate-bounce' : 'text-gray-600' ?>"><?= $m['Reapply'] ?></span>
                                    </td>
                                    <td class="p-4 text-right font-mono font-bold text-gray-300">
                                        <?= $info['total_actions'] ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="border border-dashed border-gray-800 rounded-2xl p-12 text-center text-gray-500">
                    <p class="text-sm font-semibold">Matrix evaluation complete. No transactional modifications tracked.</p>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($companyPayload)): ?>
            <div class="bg-gray-900/50 border border-gray-800 border-dashed rounded-2xl p-16 text-center flex flex-col items-center justify-center">
                <i class="fas fa-folder-open text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400 font-mono text-sm">No historical detailed payload found for this token.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($companyPayload as $scrip => $package): ?>
                    <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-xl overflow-hidden group">

                        <div class="bg-gray-850 px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                            <h2 class="text-base lg:text-lg font-bold text-white tracking-tight truncate flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                                    <i class="fas fa-building text-sm"></i>
                                </div>
                                <?= htmlspecialchars($package['meta']['companyName'] ?? 'Unknown Company') ?>
                            </h2>
                            <span class="shrink-0 bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-mono font-black px-3 py-1.5 rounded-lg tracking-widest shadow-inner">
                                <?= htmlspecialchars($scrip) ?>
                            </span>
                        </div>

                        <div class="p-4 sm:p-5 flex flex-col gap-3">
                            <?php
                            if (isset($package['statuses']) && is_array($package['statuses'])):

                                // 1. THE MAGIC SORTING ALGORITHM
                                // Forces high-priority statuses to the top of the loop
                                $statusPriority = [
                                    'Alloted'     => 1,
                                    'Rejected'    => 2,
                                    'Unverified'  => 3, // Needs attention soon
                                    'Verified'    => 4, // Waiting for allotment
                                    'Not Alloted' => 5  // Dead end, lowest priority
                                ];

                                uksort($package['statuses'], function ($a, $b) use ($statusPriority) {
                                    $pA = $statusPriority[$a] ?? 99;
                                    $pB = $statusPriority[$b] ?? 99;
                                    return $pA <=> $pB;
                                });

                                // 2. RENDER THE SORTED GROUPS
                                foreach ($package['statuses'] as $statusGroup => $accountsList):

                                    // Configuration for Badge Colors
                                    $badgeConfig = 'bg-gray-900 text-gray-400 border-gray-800';
                                    if ($statusGroup === 'Alloted') $badgeConfig = 'bg-emerald-950 text-emerald-400 border-emerald-800/80 shadow-[0_0_10px_rgba(16,185,129,0.1)]';
                                    elseif ($statusGroup === 'Verified') $badgeConfig = 'bg-blue-950/50 text-blue-400 border-blue-900/60';
                                    elseif ($statusGroup === 'Rejected') $badgeConfig = 'bg-red-950/40 text-red-400 border-red-900/50';
                                    elseif ($statusGroup === 'Unverified') $badgeConfig = 'bg-amber-950/40 text-amber-400 border-amber-900/50';
                                    elseif ($statusGroup === 'Not Alloted') $badgeConfig = 'bg-gray-900/40 text-gray-500 border-gray-800';

                                    // Determine if this section should be open by default
                                    $isOpen = in_array($statusGroup, ['Alloted', 'Rejected', 'Unverified']) ? 'open' : '';
                            ?>

                                    <details class="group bg-gray-900/30 border border-gray-800/60 rounded-xl overflow-hidden transition-all duration-300" <?= $isOpen ?>>

                                        <summary class="flex items-center justify-between cursor-pointer list-none p-3 hover:bg-gray-800/40 transition-colors select-none">
                                            <div class="flex items-center gap-3">
                                                <span class="px-3 py-1 rounded text-[11px] font-mono font-bold uppercase tracking-widest border <?= $badgeConfig ?>">
                                                    <?= htmlspecialchars($statusGroup) ?>
                                                </span>
                                                <span class="text-xs font-bold text-gray-500 font-mono bg-gray-950 px-2 py-0.5 rounded-md border border-gray-800">
                                                    <?= count($accountsList) ?> Accounts
                                                </span>
                                            </div>
                                            <div class="w-6 h-6 rounded-full bg-gray-800 flex items-center justify-center text-gray-400 transition-transform duration-300 group-open:rotate-180">
                                                <i class="fas fa-chevron-down text-[10px]"></i>
                                            </div>
                                        </summary>

                                        <div class="p-3 pt-0">
                                            <div class="overflow-x-auto rounded-xl border border-gray-800 bg-gray-950/60">
                                                <table class="min-w-full text-xs lg:text-sm">
                                                    <thead class="bg-gray-900/80 border-b border-gray-800 hidden sm:table-header-group">
                                                        <tr class="text-gray-500 font-mono text-[10px] uppercase tracking-widest text-left">
                                                            <th class="px-5 py-3 font-semibold">Account Profile</th>
                                                            <th class="px-5 py-3 font-semibold">BOID Ref</th>
                                                            <th class="px-5 py-3 font-semibold text-right">Routing Trace</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-800/60">
                                                        <?php foreach ($accountsList as $ac):
                                                            $acName = $ac['name'] ?? 'Unknown Account';
                                                            $acDmat = $ac['dmat'] ?? '0000000000000000';
                                                            $acOld  = $ac['old'] ?? 'N/A';
                                                            $acKitta = $ac['kitta'] ?? '0';
                                                        ?>
                                                            <tr class="hover:bg-gray-800/40 transition-colors duration-150">

                                                                <td class="px-4 sm:px-5 py-3.5 align-middle">
                                                                    <div class="flex items-center gap-2 lg:gap-3">
                                                                        <span class="inline-flex items-center justify-center bg-gray-800 border border-gray-700 text-gray-400 text-[9px] font-mono font-bold px-1.5 py-0.5 rounded shadow-sm">
                                                                            <?= htmlspecialchars($scrip) ?>
                                                                        </span>
                                                                        <span class="font-semibold text-gray-200 text-sm lg:text-base tracking-tight">
                                                                            <?= htmlspecialchars($acName) ?>
                                                                        </span>
                                                                    </div>

                                                                    <div class="sm:hidden mt-1.5 flex items-center gap-2">
                                                                        <span class="font-mono text-gray-500 text-[10px]">
                                                                            ID: <?= htmlspecialchars(substr($acDmat, -8)) ?>
                                                                        </span>
                                                                    </div>
                                                                </td>

                                                                <td class="hidden sm:table-cell px-5 py-3.5 align-middle">
                                                                    <span class="font-mono text-gray-400 text-xs bg-gray-900/80 px-2 py-1 rounded border border-gray-800/80">
                                                                        <span class="opacity-40"><?= htmlspecialchars(substr($acDmat, 0, 8)) ?></span><span class="text-gray-300"><?= htmlspecialchars(substr($acDmat, -8)) ?></span>
                                                                    </span>
                                                                </td>

                                                                <td class="px-4 sm:px-5 py-3.5 align-middle text-right whitespace-nowrap">
                                                                    <?php if ($statusGroup === 'Alloted'): ?>
                                                                        <span class="inline-flex items-center gap-1.5 bg-emerald-500/10 text-emerald-400 font-bold px-3 py-1.5 rounded-lg text-xs border border-emerald-500/20 shadow-sm animate-pulse">
                                                                            <i class="fas fa-check-circle text-[10px]"></i>
                                                                            <?= htmlspecialchars($acKitta) ?> KITTA
                                                                        </span>
                                                                    <?php else: ?>
                                                                        <?php
                                                                        $statusStyles = [
                                                                            'Rejected'    => 'text-rose-500 border-rose-500/20 bg-rose-500/5',
                                                                            'Unverified'  => 'text-amber-500 border-amber-500/20 bg-amber-500/5',
                                                                            'Not Alloted' => 'text-gray-500 border-gray-700/30 bg-gray-800/20',
                                                                            'Verified'    => 'text-blue-400 border-blue-500/20 bg-blue-500/5'
                                                                        ];
                                                                        $activeStyle = $statusStyles[$statusGroup] ?? 'text-blue-400 border-blue-500/20 bg-blue-500/5';
                                                                        ?>

                                                                        <div class="text-[10px] font-mono inline-flex items-center gap-1.5 px-2.5 py-1 rounded border <?= $activeStyle ?>">
                                                                            <span class="line-through opacity-50"><?= htmlspecialchars($acOld) ?></span>
                                                                            <span class="text-gray-600">➔</span>
                                                                            <span class="font-bold"><?= htmlspecialchars($statusGroup) ?></span>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </details>
                            <?php endforeach;
                            endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php
    }
}

// 3. Instantiate and execute the class
$report = new IpoResultReport($db, $_GET['token'] ?? '');
$report->render();
