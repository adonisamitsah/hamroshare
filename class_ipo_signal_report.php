<?php
class IpoSignalReport extends BaseReportViewer {
    
    protected string $prefix = 'ipo_signal_';
    protected string $themeColor = 'indigo';
    protected string $pageTitle = 'IPO Market Signals & Valuations';

    public function __construct(SQLite3 $db, string $token) {
        $this->db = $db;
        $this->token = trim($token);
        $this->baseUrl = $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?: '/';
        
        $this->payload = ['status' => 'live_data'];
        $this->executionTime = date('Y-m-d H:i:s');
        $this->historyList = []; 
    }

    /**
     * REUSABLE ENGINE: Generates fully processed market intelligence
     * Call this method from anywhere to get the raw analytical arrays.
     */
    public function generateMarketIntelligence(): array {
        // 1. Fetch Nepse Names
        $marketJsonRaw = @file_get_contents("https://raw.githubusercontent.com/Shubhamnpk/yonepse/refs/heads/main/data/nepse_data.json");
        $companyNameMap = [];
        if ($marketJsonRaw !== FALSE) {
            foreach (json_decode($marketJsonRaw, true) as $asset) {
                $companyNameMap[strtoupper($asset['symbol'])] = $asset['name'];
            }
        }

        // 2. Fetch Active Users & Extract Portfolio Quantities
        $skipClients = array_map('trim', explode(',', $_ENV['SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION'] ?? getenv('SKIP_CLIENTS_FOR_PORTFOLIO_VALUATION') ?? ''));
        $query = "SELECT u.username, u.name, u.myshare, i.scrip FROM users u JOIN ipo_results i ON u.dmat_num = i.dmat_num WHERE u.is_active = 1 AND i.statusName IN ('Alloted', 'Allotted')";
        $results = $this->db->query($query);
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
                    if (!isset($uniqueScrips[$row['scrip']])) $uniqueScrips[$row['scrip']] = ['users' => [], 'total_shares' => 0];
                    if (!in_array($clientName, $uniqueScrips[$row['scrip']]['users'])) {
                        $uniqueScrips[$row['scrip']]['users'][] = $clientName;
                        $uniqueScrips[$row['scrip']]['total_shares'] += floatval($portfolioItem['currentBalance'] ?? $portfolioItem['shareQty'] ?? 10);
                    }
                }
            }
        }

        // 3. Technical Analytics Engine
        $cacheDir = __DIR__ . '/market_cache';
        $analyzedScrips = [];
        $totals = ['portfolioValue' => 0, 'unrealizedProfit' => 0, 'assets' => 0];

        foreach ($uniqueScrips as $scrip => $scripMeta) {
            $scrip = strtoupper($scrip);
            $cacheFile = $cacheDir . "/{$scrip}.json";
            
            if (file_exists($cacheFile)) {
                $cacheJson = json_decode(file_get_contents($cacheFile), true);
                $history = $cacheJson['history'] ?? [];
                
                if (!empty($history)) {
                    // Reverse to Chronological (Oldest -> Newest) for sequential math
                    $chrono = array_reverse($history);
                    $closes = array_column($chrono, 'close');
                    $highs = array_column($chrono, 'high');
                    $lows = array_column($chrono, 'low');
                    $volumes = array_column($chrono, 'volume');
                    
                    $ltp = end($closes);
                    $days = count($closes);
                    
                    // Valuations
                    $assetValue = $scripMeta['total_shares'] * $ltp;
                    $assetProfit = $scripMeta['total_shares'] * ($ltp - 100);
                    $totals['portfolioValue'] += $assetValue;
                    $totals['unrealizedProfit'] += $assetProfit;

                    // Advanced Technicals
                    $peak = max($highs);
                    $drawdown = $peak > 0 ? (($peak - $ltp) / $peak) * 100 : 0;
                    $roi = (($ltp - 100) / 100) * 100;
                    
                    $sma14 = $this->calculateSMA($closes, 14);
                    $sma30 = $this->calculateSMA($closes, 30);
                    $rsi14 = $this->calculateRSI($closes, 14);
                    
                    $recentLows = array_slice($lows, -30);
                    $recentHighs = array_slice($highs, -30);
                    $support = !empty($recentLows) ? min($recentLows) : $ltp;
                    $resistance = !empty($recentHighs) ? max($recentHighs) : $ltp;

                    // Volume Analysis
                    $vol14 = array_slice($volumes, -14);
                    $avgVol = !empty($vol14) ? array_sum($vol14)/count($vol14) : 1;
                    $todayVol = end($volumes);

                    // Smart Tags & Verdict Scoring
                    $score = 50; // Neutral baseline
                    $tags = [];
                    
                    if ($rsi14 > 70) { $score -= 20; $tags[] = ['text' => 'Overbought', 'color' => 'rose']; }
                    if ($rsi14 < 30) { $score += 20; $tags[] = ['text' => 'Oversold', 'color' => 'emerald']; }
                    if ($ltp > $sma14) { $score += 15; $tags[] = ['text' => 'Bull Trend', 'color' => 'emerald']; }
                    if ($ltp < $sma14) { $score -= 15; $tags[] = ['text' => 'Bear Trend', 'color' => 'rose']; }
                    if ($todayVol > ($avgVol * 1.5)) { $tags[] = ['text' => '🔥 Volume Surge', 'color' => 'indigo']; }
                    if ($ltp >= ($peak * 0.98)) { $tags[] = ['text' => 'Near ATH', 'color' => 'amber']; }

                    // Chart Arrays Generation (Price + SMA overlay)
                    $chartPrices = []; $chartSMA14 = []; $chartDates = [];
                    foreach ($chrono as $idx => $day) {
                        $chartPrices[] = $day['close'];
                        $chartDates[] = date('M d', strtotime($day['f_date']));
                        $slice = array_slice($closes, max(0, $idx - 13), min(14, $idx + 1));
                        $chartSMA14[] = round(array_sum($slice) / count($slice), 2);
                    }

                    $analyzedScrips[$scrip] = [
                        'name' => $companyNameMap[$scrip] ?? 'Unknown Asset',
                        'users' => $scripMeta['users'],
                        'shares' => $scripMeta['total_shares'],
                        'assetValue' => $assetValue,
                        'assetProfit' => $assetProfit,
                        'ltp' => $ltp, 'roi' => $roi, 'peak' => $peak, 'drawdown' => $drawdown,
                        'sma14' => $sma14, 'sma30' => $sma30, 'rsi14' => round($rsi14, 1),
                        'support' => $support, 'resistance' => $resistance,
                        'score' => $score, 'tags' => $tags,
                        'date' => $chrono[$days-1]['f_date'],
                        'chartPrices' => $chartPrices, 'chartSMA14' => $chartSMA14, 'chartDates' => $chartDates
                    ];
                }
            }
        }
        
        $totals['assets'] = count($analyzedScrips);
        // Sort highest ROI first
        uasort($analyzedScrips, function($a, $b) { return $b['roi'] <=> $a['roi']; });

        return ['totals' => $totals, 'scrips' => $analyzedScrips];
    }

    // Mathematical Helpers
    private function calculateSMA(array $prices, int $period): float {
        if (count($prices) == 0) return 0;
        $slice = array_slice($prices, -$period);
        return array_sum($slice) / count($slice);
    }

    private function calculateRSI(array $prices, int $period = 14): float {
        if (count($prices) <= $period) return 50.0;
        $gains = 0; $losses = 0;
        for ($i = 1; $i <= $period; $i++) {
            $diff = $prices[$i] - $prices[$i-1];
            if ($diff > 0) $gains += $diff; else $losses += abs($diff);
        }
        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        for ($i = $period + 1; $i < count($prices); $i++) {
            $diff = $prices[$i] - $prices[$i-1];
            $gain = $diff > 0 ? $diff : 0;
            $loss = $diff < 0 ? abs($diff) : 0;
            $avgGain = (($avgGain * 13) + $gain) / 14;
            $avgLoss = (($avgLoss * 13) + $loss) / 14;
        }
        if ($avgLoss == 0) return 100.0;
        $rs = $avgGain / $avgLoss;
        return 100.0 - (100.0 / (1.0 + $rs));
    }


    protected function renderMainContent(): void {
        $intelligence = $this->generateMarketIntelligence();
        $totals = $intelligence['totals'];
        $scrips = $intelligence['scrips'];
        ?>
        
        <!-- Inject ApexCharts -->
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

        <!-- Master Terminal Dashboard -->
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 lg:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute -top-24 -right-10 w-64 h-64 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col lg:flex-row justify-between gap-6 relative z-10">
                <div class="flex-1">
                    <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight text-white mb-2">
                        Quantitative Terminal
                    </h1>
                    <p class="text-xs lg:text-sm text-gray-400 font-mono flex flex-wrap items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Status: <span class="text-emerald-400 font-bold">Algorithms Online</span>
                        <span class="text-gray-600 hidden sm:inline">|</span>
                        <span class="text-gray-500">Scan: <?= htmlspecialchars($this->executionTime) ?></span>
                    </p>

                    <div class="mt-5 relative max-w-sm">
                        <i class="fas fa-search absolute left-3.5 top-3.5 text-gray-500 text-sm"></i>
                        <input type="text" id="ipoSearchInput" placeholder="Filter symbol or algorithm..." class="w-full bg-gray-950 border border-gray-800 focus:border-indigo-500/50 rounded-xl py-2.5 pl-10 pr-4 text-sm text-white font-mono placeholder-gray-600 focus:outline-none transition-all shadow-inner">
                    </div>
                </div>

                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 w-full lg:w-auto self-end">
                    <div class="bg-gray-950 border border-gray-800 p-4 rounded-xl text-center min-w-[100px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-gray-500 mb-1">Assets</span>
                        <span class="text-xl font-black text-white font-mono"><?= $totals['assets'] ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-4 rounded-xl text-center min-w-[130px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-indigo-500 mb-1">Global Value</span>
                        <span class="text-xl font-black text-indigo-400 font-mono">Rs. <?= number_format($totals['portfolioValue']) ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-4 rounded-xl text-center min-w-[130px] col-span-2 lg:col-span-1">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-emerald-500 mb-1">Est. Profit</span>
                        <span class="text-xl font-black text-emerald-400 font-mono">+<?= number_format($totals['unrealizedProfit']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($scrips)): ?>
            <div class="bg-gray-900/50 border border-indigo-900/30 border-dashed rounded-2xl p-16 text-center flex flex-col items-center mt-6">
                <h3 class="text-lg font-bold text-indigo-400 mb-2">No Analytic Data Found</h3>
                <p class="text-gray-400 font-mono text-sm">Please backfill historical data or ensure user portfolios are linked.</p>
            </div>
        <?php else: ?>
            <div class="space-y-6 mt-6" id="ipoCardsContainer">
                <?php foreach ($scrips as $scrip => $data): ?>
                    <?php 
                        // Master Verdict Generator
                        if ($data['score'] >= 65) { $verdict = "STRONG BUY / HOLD"; $badge = "bg-emerald-500/10 text-emerald-400 border-emerald-500/20"; $color = "#10b981"; }
                        elseif ($data['score'] <= 35) { $verdict = "SELL SIGNAL"; $badge = "bg-rose-500/10 text-rose-400 border-rose-500/20"; $color = "#f43f5e"; }
                        else { $verdict = "NEUTRAL / HOLD"; $badge = "bg-amber-500/10 text-amber-400 border-amber-500/20"; $color = "#f59e0b"; }

                        // RSI Bar Color
                        $rsiColor = $data['rsi14'] > 70 ? 'bg-rose-500' : ($data['rsi14'] < 30 ? 'bg-emerald-500' : 'bg-amber-500');
                    ?>
                    
                    <div class="ipo-card bg-gray-900 border border-gray-800 rounded-2xl shadow-2xl overflow-hidden" data-search="<?= strtolower($scrip . ' ' . $data['name'] . ' ' . $verdict) ?>">
                        
                        <!-- Tier 1: Wealth Header -->
                        <div class="px-5 py-4 flex flex-col lg:flex-row justify-between gap-4 bg-gradient-to-r from-gray-850 to-gray-900 border-b border-gray-800">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-xl bg-gray-950 border border-gray-700 flex items-center justify-center text-indigo-400 font-black text-xl shadow-inner shrink-0">
                                    <?= substr($scrip, 0, 2) ?>
                                </div>
                                <div>
                                    <h2 class="text-xl font-bold text-white tracking-tight flex items-center gap-2">
                                        <?= htmlspecialchars($scrip) ?>
                                        <span class="text-xs font-mono text-gray-500 px-2 py-0.5 bg-gray-950 rounded-md border border-gray-800 shadow-sm">
                                            Rs. <?= number_format($data['ltp'], 2) ?>
                                        </span>
                                    </h2>
                                    <p class="text-sm text-gray-400 truncate max-w-sm" title="<?= htmlspecialchars($data['name']) ?>">
                                        <?= htmlspecialchars($data['name']) ?>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between lg:justify-end gap-6 bg-gray-950/50 p-3 rounded-xl border border-gray-800/50 w-full lg:w-auto">
                                <div class="text-center px-2">
                                    <span class="block text-[10px] uppercase font-bold text-gray-500 tracking-widest">Group Holding</span>
                                    <span class="text-lg font-black text-white font-mono"><?= number_format($data['shares']) ?> <span class="text-xs font-sans text-gray-600 font-medium">units</span></span>
                                </div>
                                <div class="w-px h-8 bg-gray-800"></div>
                                <div class="text-center px-2">
                                    <span class="block text-[10px] uppercase font-bold text-indigo-500 tracking-widest">Asset Value</span>
                                    <span class="text-lg font-black text-indigo-400 font-mono">Rs. <?= number_format($data['assetValue']) ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Smart Tags Bar -->
                        <div class="bg-gray-850 px-5 py-2 flex flex-wrap items-center justify-between border-b border-gray-800 gap-3">
                            <div class="flex gap-2">
                                <?php foreach($data['tags'] as $tag): ?>
                                    <span class="bg-<?= $tag['color'] ?>-500/10 text-<?= $tag['color'] ?>-400 border border-<?= $tag['color'] ?>-500/20 px-2 py-1 rounded text-[9px] font-bold uppercase tracking-wider">
                                        <?= $tag['text'] ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <span class="inline-flex items-center gap-1.5 font-bold px-3 py-1 rounded text-[10px] border tracking-widest shadow-sm <?= $badge ?>">
                                <i class="fas fa-robot"></i> AI Verdict: <?= $verdict ?>
                            </span>
                        </div>

                        <!-- Tier 2: Chart Overlay -->
                        <div class="relative w-full bg-gray-900 border-b border-gray-800">
                            <div class="absolute top-4 left-5 z-10 flex flex-col gap-1">
                                <span class="text-[10px] uppercase font-bold text-gray-500 tracking-widest font-mono">Price Action vs SMA-14</span>
                                <span class="text-xs font-bold <?= $data['roi'] >= 0 ? 'text-emerald-400' : 'text-rose-400' ?>">ROI: <?= number_format($data['roi'], 1) ?>%</span>
                            </div>
                            <div id="chart_<?= $scrip ?>" class="w-full"></div>
                        </div>

                        <!-- Tier 3: Technical Indicators Grid -->
                        <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-y md:divide-y-0 divide-gray-800 bg-gray-950/50">
                            
                            <!-- Drawdown & ATH -->
                            <div class="p-4 flex flex-col justify-center">
                                <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-1">Peak Drawdown</span>
                                <div class="flex items-end gap-2">
                                    <span class="text-xl font-black <?= $data['drawdown'] > 15 ? 'text-rose-400' : 'text-amber-400' ?> font-mono">-<?= number_format($data['drawdown'], 1) ?>%</span>
                                    <span class="text-xs text-gray-600 font-mono mb-1">ATH: <?= number_format($data['peak']) ?></span>
                                </div>
                            </div>

                            <!-- RSI 14 -->
                            <div class="p-4 flex flex-col justify-center">
                                <div class="flex justify-between items-end mb-1.5">
                                    <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500">RSI (14)</span>
                                    <span class="text-sm font-black text-white font-mono"><?= $data['rsi14'] ?></span>
                                </div>
                                <div class="w-full bg-gray-800 rounded-full h-1.5 relative overflow-hidden">
                                    <div class="<?= $rsiColor ?> h-1.5 rounded-full absolute left-0 top-0 transition-all" style="width: <?= min(100, max(0, $data['rsi14'])) ?>%"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-[8px] text-gray-600 font-mono"><span>0</span><span>Oversold</span><span>Overbought</span><span>100</span></div>
                            </div>

                            <!-- Moving Averages -->
                            <div class="p-4 flex flex-col justify-center">
                                <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">Trend Lines</span>
                                <div class="flex justify-between items-center text-xs font-mono">
                                    <span class="text-gray-400">SMA-14</span>
                                    <span class="<?= $data['ltp'] > $data['sma14'] ? 'text-emerald-400' : 'text-rose-400' ?>"><?= number_format($data['sma14'], 1) ?></span>
                                </div>
                                <div class="flex justify-between items-center text-xs font-mono mt-1">
                                    <span class="text-gray-400">SMA-30</span>
                                    <span class="text-gray-300"><?= number_format($data['sma30'], 1) ?></span>
                                </div>
                            </div>

                            <!-- Support / Resistance -->
                            <div class="p-4 flex flex-col justify-center">
                                <span class="text-[10px] uppercase font-bold tracking-widest text-gray-500 mb-2">30D Key Levels</span>
                                <div class="flex justify-between items-center text-xs font-mono">
                                    <span class="text-rose-500/70">Resistance</span>
                                    <span class="text-white"><?= number_format($data['resistance']) ?></span>
                                </div>
                                <div class="flex justify-between items-center text-xs font-mono mt-1">
                                    <span class="text-emerald-500/70">Support</span>
                                    <span class="text-white"><?= number_format($data['support']) ?></span>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Chart Instantiation -->
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            var options = {
                                series: [
                                    { name: 'Close Price', type: 'area', data: <?= json_encode($data['chartPrices']) ?> },
                                    { name: 'SMA-14', type: 'line', data: <?= json_encode($data['chartSMA14']) ?> }
                                ],
                                chart: { type: 'line', height: 160, sparkline: { enabled: true }, animations: { enabled: false } },
                                stroke: { curve: 'smooth', width: [2, 1], dashArray: [0, 4] }, // Dash the SMA line
                                fill: { type: ["gradient", "solid"], gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.0, stops: [0, 100] } },
                                colors: ['<?= $color ?>', '#818cf8'], // Primary color + Indigo for SMA
                                tooltip: { theme: 'dark', fixed: { enabled: false }, x: { show: true, categories: <?= json_encode($data['chartDates']) ?> } },
                                xaxis: { categories: <?= json_encode($data['chartDates']) ?>, crosshairs: { width: 1 } }
                            };
                            new ApexCharts(document.querySelector("#chart_<?= $scrip ?>"), options).render();
                        });
                    </script>

                <?php endforeach; ?>
            </div>
            
            <script>
                document.getElementById('ipoSearchInput').addEventListener('keyup', function(e) {
                    const term = e.target.value.toLowerCase();
                    document.querySelectorAll('.ipo-card').forEach(card => {
                        card.style.display = card.getAttribute('data-search').includes(term) ? '' : 'none';
                    });
                });
            </script>
        <?php endif; ?>
        <?php
    }
}

?>