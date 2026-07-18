<?php
// cr_display_edis_results.php

require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_report_viewer.php';

class EdisReport extends BaseReportViewer
{

    // 1. Define the report's identity
    protected string $prefix = 'edis_report_';
    protected string $themeColor = 'emerald';
    protected string $pageTitle = 'EDIS Transfer';

    // 2. Define the Main Content
    protected function renderMainContent(): void
    {
        $companyPayload = $this->payload['companyPayload'] ?? [];

        // --- UI/UX Stats Analytics Parsing ---
        $totalAccountsProcessed = count($companyPayload);
        $totalTransfers = 0;
        $totalDanger = 0;
        $totalErrors = 0;

        foreach ($companyPayload as $dmat => $package) {
            $rep = $package['report'] ?? [];
            $totalTransfers += (int)($rep['transfers_done'] ?? 0);
            $totalDanger += count($rep['danger_obligations'] ?? []);
            $totalErrors += count($rep['errors'] ?? []);
        }
?>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 lg:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 relative z-10">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight text-white mb-2">
                        EDIS Automation Matrix
                    </h1>
                    <p class="text-xs lg:text-sm text-gray-400 font-mono flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live Execution: <span class="text-gray-300 font-bold"><?= htmlspecialchars($this->executionTime) ?></span>
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 lg:gap-4 w-full md:w-auto">
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[90px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-gray-500 mb-1">Profiles</span>
                        <span class="text-xl font-black text-white font-mono"><?= $totalAccountsProcessed ?></span>
                    </div>
                    <div class="bg-gray-950 border border-emerald-900/50 p-3 rounded-xl text-center min-w-[90px] shadow-[inset_0_0_20px_rgba(16,185,129,0.05)]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-emerald-500 mb-1">Transferred</span>
                        <span class="text-xl font-black text-emerald-400 font-mono"><?= $totalTransfers ?></span>
                    </div>
                    <div class="bg-gray-950 border border-red-900/50 p-3 rounded-xl text-center min-w-[90px] <?= $totalDanger > 0 ? 'shadow-[inset_0_0_20px_rgba(239,68,68,0.1)]' : '' ?>">
                        <span class="block text-[9px] uppercase font-bold tracking-widest <?= $totalDanger > 0 ? 'text-red-500' : 'text-gray-500' ?> mb-1">Action Req.</span>
                        <span class="text-xl font-black <?= $totalDanger > 0 ? 'text-red-500 animate-pulse' : 'text-gray-600' ?> font-mono"><?= $totalDanger ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($companyPayload)): ?>
            <div class="bg-gray-900/50 border border-gray-800 border-dashed rounded-2xl p-16 text-center flex flex-col items-center justify-center">
                <i class="fas fa-check-double text-4xl text-gray-600 mb-4"></i>
                <p class="text-gray-400 font-mono text-sm">Matrix evaluation complete. No transactional modifications or pending EDIS found.</p>
            </div>
        <?php else: ?>

            <div class="space-y-6">
                <?php foreach ($companyPayload as $dmat => $package):
                    $rep = $package['report'] ?? [];
                    $tDone = (int)($rep['transfers_done'] ?? 0);
                    $dangers = $rep['danger_obligations'] ?? [];
                    $errors = $rep['errors'] ?? [];

                    // Determine border color based on status priorities
                    $borderColor = 'border-gray-800';
                    if (!empty($dangers) || !empty($errors)) $borderColor = 'border-red-900/60';
                    elseif ($tDone > 0) $borderColor = 'border-emerald-900/60';
                ?>
                    <div class="bg-gray-900 border <?= $borderColor ?> rounded-2xl shadow-xl overflow-hidden group">

                        <div class="bg-gray-850 px-5 py-4 border-b border-gray-800 flex items-center justify-between">
                            <h2 class="text-base lg:text-lg font-bold text-white tracking-tight truncate flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                                    <i class="fas fa-user text-sm"></i>
                                </div>
                                <?= htmlspecialchars($package['name']) ?>
                            </h2>
                            <span class="shrink-0 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-mono font-black px-3 py-1.5 rounded-lg tracking-widest shadow-inner">
                                <span class="opacity-40"><?= htmlspecialchars(substr($dmat, 0, 8)) ?></span><?= htmlspecialchars(substr($dmat, -8)) ?>
                            </span>
                        </div>

                        <div class="p-5 space-y-3">

                            <?php if ($tDone === 0 && empty($dangers) && empty($errors)): ?>
                                <div class="flex items-center gap-3 p-3 bg-gray-900/50 border border-gray-800 rounded-xl text-sm font-mono text-gray-500">
                                    <i class="fas fa-minus text-gray-600"></i>
                                    No pending obligations or transfers.
                                </div>
                            <?php endif; ?>

                            <?php if ($tDone > 0): ?>
                                <div class="flex items-center gap-3 p-3 lg:p-4 bg-emerald-950/20 border border-emerald-900/50 rounded-xl text-sm text-emerald-400 font-medium">
                                    <div class="p-1.5 bg-emerald-500/10 rounded-lg shrink-0">
                                        <i class="fas fa-check text-emerald-400"></i>
                                    </div>
                                    <span class="font-mono"><strong><?= $tDone ?></strong> Successful EDIS Transfer(s) Completed and Confirmed.</span>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($dangers as $scrip): ?>
                                <div class="flex items-center gap-3 p-3 lg:p-4 bg-rose-950/20 border border-rose-900/50 rounded-xl text-sm text-rose-400 font-medium relative overflow-hidden">
                                    <div class="absolute inset-0 bg-[repeating-linear-gradient(45deg,transparent,transparent_10px,rgba(225,29,72,0.03)_10px,rgba(225,29,72,0.03)_20px)]"></div>
                                    <div class="p-1.5 bg-rose-500/10 rounded-lg shrink-0 relative z-10">
                                        <i class="fas fa-exclamation-triangle text-rose-400"></i>
                                    </div>
                                    <span class="relative z-10"><strong>ACTION REQUIRED:</strong> Pending WACC calculation or Holding Period conflict for <span class="font-mono font-bold text-rose-300 bg-rose-900/40 px-2 py-0.5 rounded border border-rose-800/50"><?= htmlspecialchars($scrip) ?></span></span>
                                </div>
                            <?php endforeach; ?>

                            <?php foreach ($errors as $error): ?>
                                <div class="flex items-center gap-3 p-3 lg:p-4 bg-orange-950/20 border border-orange-900/50 rounded-xl text-sm text-orange-400 font-medium">
                                    <div class="p-1.5 bg-orange-500/10 rounded-lg shrink-0">
                                        <i class="fas fa-times-circle text-orange-400"></i>
                                    </div>
                                    <span class="font-mono"><?= htmlspecialchars($error) ?></span>
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
$report = new EdisReport($db, $_GET['token'] ?? '');
$report->render();
