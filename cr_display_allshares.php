<?php
// cr_display_allshares.php

require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_report_viewer.php';

class AllSharesReport extends BaseReportViewer
{

    // 1. Define the report's identity
    protected string $prefix = 'allshares_report_';
    protected string $themeColor = 'blue';
    protected string $pageTitle = 'All Shares';

    /**
     * Helper Method: Fetches the LTP from the exact report generated right before the current one.
     */
    private function getPreviousReportMetrics(): ?array
    {
        $currentToken = $_GET['token'] ?? '';
        $currentKey = $this->prefix . $currentToken;
        $currentGeneratedAt = $this->payload['generated_at'] ?? date('Y-m-d H:i:s');

        // Fetch all allshares reports EXCEPT the current one
        $query = "SELECT key, value FROM constant WHERE key LIKE :prefix AND key != :currentKey";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':prefix', $this->prefix . '%', SQLITE3_TEXT);
        $stmt->bindValue(':currentKey', $currentKey, SQLITE3_TEXT);
        $result = $stmt->execute();

        $latestPrevTime = 0;
        $prevMetrics = null;

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $data = json_decode($row['value'], true);
            if (!$data || !isset($data['generated_at'])) continue;

            $genTime = strtotime($data['generated_at']);
            $currTime = strtotime($currentGeneratedAt);

            // We want the most recent report BEFORE the current report's timestamp
            if ($genTime < $currTime && $genTime > $latestPrevTime) {
                $latestPrevTime = $genTime;
                $prevMetrics = [
                    'total_value_ltp' => $data['metrics']['total_value_ltp'] ?? 0,
                    'generated_at'    => $data['generated_at']
                ];
            }
        }

        return $prevMetrics;
    }

    // 2. Define the Main Content
    protected function renderMainContent(): void
    {
        $portfolios = $this->payload['companyPayload'] ?? [];
        $metrics = $this->payload['metrics'] ?? ['total_value_ltp' => 0, 'total_value_pcp' => 0, 'total_accounts' => 0];

        // Global calculations
        $globalLtp = (float)$metrics['total_value_ltp'];
        $globalPcp = (float)$metrics['total_value_pcp']; // Default to CDSC's previous close

        // --- NEW HISTORICAL BASELINE LOGIC ---
        $prevData = $this->getPreviousReportMetrics();
        $baselineSource = "CDSC Previous Close";

        if ($prevData !== null) {
            $globalPcp = (float)$prevData['total_value_ltp']; // Override with your historical LTP
            $baselineSource = "Prev. Report (" . date('M d, g:i A', strtotime($prevData['generated_at'])) . ")";
        }

        $globalDiff = $globalLtp - $globalPcp;
        $globalPct = ($globalPcp > 0) ? ($globalDiff / $globalPcp) * 100 : 0;
        $globalTrendColor = ($globalDiff >= 0) ? 'text-emerald-400' : 'text-rose-400';
        $globalTrendBg = ($globalDiff >= 0) ? 'bg-emerald-500/10 border-emerald-500/20' : 'bg-rose-500/10 border-rose-500/20';
?>

        <div class="bg-gray-900 border border-gray-800 rounded-3xl p-6 lg:p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute -top-32 -right-32 w-72 h-72 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 relative z-10">
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Aggregate Portfolio</h1>
                    <p class="text-sm text-gray-400 font-mono flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                        Synced: <span class="text-gray-300 font-bold"><?= htmlspecialchars($this->executionTime) ?></span>
                    </p>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 w-full md:w-auto">
                    <div class="bg-gray-950 border border-gray-800 p-4 rounded-2xl text-center">
                        <span class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-1">Accounts</span>
                        <span class="text-xl font-black text-white font-mono"><?= $metrics['total_accounts'] ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-4 rounded-2xl text-center">
                        <span class="block text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-1">Total Wealth</span>
                        <span class="text-xl font-black text-blue-400 font-mono">Rs. <?= number_format($globalLtp) ?></span>
                    </div>
                    <div class="col-span-2 lg:col-span-1 border p-4 rounded-2xl text-center <?= $globalTrendBg ?> cursor-help" title="Baseline: <?= htmlspecialchars($baselineSource) ?>">
                        <span class="block text-[10px] uppercase font-bold tracking-widest <?= $globalTrendColor ?> mb-1">
                            Change vs Prev <i class="fas fa-info-circle text-[8px] opacity-50 ml-1"></i>
                        </span>
                        <span class="text-xl font-black <?= $globalTrendColor ?> font-mono">
                            <?= ($globalDiff >= 0 ? '+' : '') . number_format($globalPct, 2) ?>%
                        </span>
                    </div>
                </div>
            </div>

            <?php if (!empty($metrics['skippedNamesList'])): ?>
                <div class="mt-6 pt-4 border-t border-gray-800/60 relative z-10">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                        <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500 shrink-0 mt-1">
                            <i class="fas fa-forward text-gray-600 mr-1"></i> Bypassed (<?= $metrics['skipped'] ?>)
                        </span>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($metrics['skippedNamesList'] as $skippedName):
                                $firstName = trim(explode(' ', $skippedName)[0]);
                            ?>
                                <span class="bg-gray-950 border border-gray-800 text-gray-400 text-[10px] px-2 py-1 rounded-md font-mono shadow-inner cursor-help" title="<?= htmlspecialchars($skippedName) ?>">
                                    <?= htmlspecialchars($firstName) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($portfolios)): ?>
            <div class="bg-gray-900/50 border border-gray-800 border-dashed rounded-2xl p-16 text-center flex flex-col items-center justify-center mt-6">
                <div class="w-16 h-16 bg-gray-800 rounded-full flex items-center justify-center mb-4 border border-gray-700">
                    <i class="fas fa-folder-open text-2xl text-gray-500"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-400 mb-2">No Portfolios Found</h3>
                <p class="text-gray-500 font-mono text-sm max-w-md mx-auto">There is no aggregate share data available in this execution cycle.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6 mt-6">
                <?php foreach ($portfolios as $dmat => $package):
                    $data = $package['portfolio'];

                    // SKIP THIS ACCOUNT ENTIRELY IF THERE ARE NO SHARES
                    if (empty($data['meroShareMyPortfolio'])) {
                        continue;
                    }

                    $totalLtp = (float)($data['totalValueOfLastTransPrice'] ?? 0);
                    $totalPcp = (float)($data['totalValueOfPrevClosingPrice'] ?? 0);
                    $diff = $totalLtp - $totalPcp;
                    $pct = ($totalPcp > 0) ? ($diff / $totalPcp) * 100 : 0;
                    $trendColor = ($diff >= 0) ? 'text-emerald-400' : 'text-rose-400';
                ?>
                    <div class="bg-gray-900 border border-gray-800 rounded-2xl shadow-lg overflow-hidden group">

                        <div class="bg-gray-950 px-5 py-4 border-b border-gray-800 flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-gray-800 border border-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                                    <i class="fas fa-wallet text-sm"></i>
                                </div>
                                <div>
                                    <h2 class="text-base font-bold text-white tracking-tight"><?= htmlspecialchars($package['name']) ?></h2>
                                    <span class="text-[10px] text-gray-500 font-mono tracking-widest">BOID: <span class="opacity-50"><?= substr($dmat, 0, 8) ?></span><?= substr($dmat, -8) ?></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-4 text-right">
                                <div>
                                    <span class="block text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-0.5">Valuation</span>
                                    <span class="font-mono text-sm font-bold text-white">Rs. <?= number_format($totalLtp, 2) ?></span>
                                </div>
                                <div class="pl-4 border-l border-gray-800">
                                    <span class="block text-[9px] uppercase tracking-widest text-gray-500 font-bold mb-0.5">Return</span>
                                    <span class="font-mono text-sm font-bold <?= $trendColor ?>">
                                        <?= ($diff >= 0 ? '+' : '') . number_format($pct, 2) ?>%
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto p-4 lg:p-5">
                            <table class="w-full text-left rounded-xl overflow-hidden border border-gray-800">
                                <thead class="bg-gray-950 text-[10px] uppercase tracking-widest text-gray-500 border-b border-gray-800">
                                    <tr>
                                        <th class="px-4 py-3 font-bold">Scrip</th>
                                        <th class="px-4 py-3 font-bold text-right">Units</th>
                                        <th class="px-4 py-3 font-bold text-right">LTP</th>
                                        <th class="px-4 py-3 font-bold text-right">Value (Rs.)</th>
                                        <th class="px-4 py-3 font-bold text-right">Change %</th>
                                    </tr>
                                </thead>
                                <tbody class="text-[11px] divide-y divide-gray-800/60 font-mono">
                                    <?php foreach ($data['meroShareMyPortfolio'] as $item):
                                        $ltpval = (float)$item['valueOfLastTransPrice'];
                                        $pcpval = (float)$item['valueOfPrevClosingPrice'];
                                        $itemDiff = $ltpval - $pcpval;

                                        $itemPctHtml = '<span class="text-gray-600">—</span>';
                                        if ($ltpval > $pcpval) {
                                            $itemPct = round(($itemDiff / $pcpval) * 100, 2);
                                            $itemPctHtml = '<span class="text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded">+' . $itemPct . '%</span>';
                                        } elseif ($ltpval < $pcpval && $pcpval > 0) {
                                            $itemPct = round(($itemDiff / $pcpval) * 100, 2);
                                            $itemPctHtml = '<span class="text-rose-400 font-bold bg-rose-500/10 px-2 py-0.5 rounded">' . $itemPct . '%</span>';
                                        }
                                    ?>
                                        <tr class="bg-gray-900 hover:bg-gray-800/40 transition-colors">
                                            <td class="px-4 py-3 font-bold text-blue-400" title="<?= htmlspecialchars($item['scriptDesc']) ?>">
                                                <?= htmlspecialchars($item['script']) ?>
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-300"><?= $item['currentBalance'] ?></td>
                                            <td class="px-4 py-3 text-right text-gray-400"><?= number_format($item['lastTransactionPrice'], 1) ?></td>
                                            <td class="px-4 py-3 text-right text-gray-200 font-bold"><?= number_format($ltpval, 2) ?></td>
                                            <td class="px-4 py-3 text-right"><?= $itemPctHtml ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
<?php
    }
}

// 3. Instantiate and execute the class
$report = new AllSharesReport($db, $_GET['token'] ?? '');
$report->render();
