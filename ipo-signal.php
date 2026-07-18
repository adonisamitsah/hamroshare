<?php 
// error_reporting(1);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';


// Execute Logic
$signalData = get_market_signals(__DIR__ . '/market_cache');
$globalStats = $signalData['stats'];
$analyzedScrips = $signalData['scrips'];

echo sudo_get_header("ipo-signal");
?>



<div class="mb-8 flex flex-col lg:flex-row gap-6 items-start justify-between">
    <div class="flex-1">
        <div class="flex items-center gap-3 mb-2">
            <div class="p-2 bg-blue-500/10 border border-blue-500/20 rounded-lg text-blue-500">
                <i class="fas fa-network-wired text-xl"></i>
            </div>
            <h1 class="text-2xl font-black text-white tracking-tight uppercase">IPO Trade Signals</h1>
        </div>
        <p class="text-slate-400 text-sm max-w-2xl leading-relaxed">
            Real-time quantitative risk assessment. Data evaluated locally requiring zero external API credits.
        </p>
    </div>

    <div class="relative w-full lg:w-72 mt-2 lg:mt-0">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-search"></i>
        </span>
        <input type="text" id="matrix_search" placeholder="Filter scrip symbol..." 
               class="w-full pl-10 pr-4 py-2.5 bg-[#161b22] border border-slate-700 rounded-xl text-sm text-slate-300 font-mono focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-all placeholder-slate-600 shadow-inner"/>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-5 relative overflow-hidden shadow-lg">
        <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Tracked Assets</div>
        <div class="text-2xl font-black text-white font-mono"><?php echo $globalStats['total_tracked']; ?></div>
    </div>
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-5 relative overflow-hidden shadow-lg">
        <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Critical Sell Signals</div>
        <div class="text-2xl font-black text-rose-500 font-mono"><?php echo $globalStats['total_strong_sell']; ?></div>
    </div>
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-5 relative overflow-hidden shadow-lg">
        <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Average ROI</div>
        <div class="text-2xl font-black font-mono <?php echo $globalStats['avg_roi'] >= 0 ? 'text-emerald-400' : 'text-rose-400'; ?>">
            +<?php echo number_format($globalStats['avg_roi'], 1); ?>%
        </div>
    </div>
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-5 relative overflow-hidden shadow-lg">
        <div class="text-[10px] uppercase tracking-widest text-slate-500 font-bold mb-1">Max System Drawdown</div>
        <div class="text-2xl font-black text-amber-400 font-mono">-<?php echo number_format($globalStats['highest_drawdown'], 1); ?>%</div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="matrix_grid">
    <?php render_signal_cards($analyzedScrips); ?>
</div>

<?php include('footer.php'); ?>

