<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
// Note: Changed "Profit/Loss" to match your active check in header
echo sudo_get_header("pl"); 
?>

<!-- Header Section with SVG Graphic -->
<div class="mb-8 flex items-start gap-6">
    <div class="hidden md:flex w-16 h-16 bg-blue-600/10 border border-blue-500/20 rounded-2xl items-center justify-center text-blue-400">
        <!-- SVG: Growth Chart Icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
        </svg>
    </div>
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-3">
            Realized Profit & Loss
            <span class="flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-2 w-2 rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
        </h1>
        <p class="text-slate-400 text-sm mt-1 max-w-2xl">
            Analyze the performance of your closed positions. Use <strong class="text-blue-400">Fetch</strong> for cached reports or <strong class="text-white">Refresh</strong> for live Meroshare data.
        </p>
    </div>
</div>

<!-- Main Control Card -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-8 mb-8 shadow-sm relative overflow-hidden">
    <!-- Subtle Background SVG (Abstract data lines) -->
    <svg class="absolute right-0 bottom-0 opacity-[0.03] w-64 h-64 pointer-events-none" viewBox="0 0 200 200">
        <path fill="currentColor" d="M40,-62C53.3,-54.1,66.7,-45.3,74.2,-32.8C81.7,-20.3,83.3,-4,78.2,9.6C73,23.2,61,34.1,49.2,42.5C37.4,50.9,25.8,56.7,12.8,61C-0.3,65.3,-14.8,68,-28,64.2C-41.2,60.4,-53,50.1,-61.7,37.3C-70.5,24.4,-76.2,9,-75.4,-6.2C-74.6,-21.4,-67.2,-36.5,-55.9,-44.8C-44.5,-53.2,-29.2,-54.9,-15.8,-62.8C-2.4,-70.7,9.2,-84.9,15.8,-62.8Z" transform="translate(100 100)" />
    </svg>

    <div class="relative z-10">
        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-4">Select Account for Analysis</label>
        
        <div class="flex flex-col md:flex-row gap-4 items-center">
            <div class="relative w-full md:w-96">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-500">
                    <!-- SVG: User/Account Icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                </div>
                <select id="pl-select-menu" 
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-white focus:ring-2 focus:ring-blue-600/50 outline-none transition-all appearance-none cursor-pointer">
                    <?php echo sudo_get_dmat_as_options($db); ?>
                </select>
                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <button id="pl-select-fetch" onclick="pl_select_fetch();" 
                    class="w-full md:w-auto bg-blue-600 hover:bg-blue-500 text-white font-bold px-8 py-3 rounded-xl shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
                Fetch Report
            </button>
        </div>
        
        <p id="pl" class="mt-4 text-xs font-mono text-slate-500"></p>
    </div>
</div>

<!-- Report Output Container -->
<div class="space-y-6">
    <div id="refresh-btn-pl" class="flex justify-end">
        <!-- JS will inject Refresh button here; encourage it to use Tailwind bg-emerald-600 -->
    </div>

    <!-- Table Container -->
    <div id="pl-table" class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden min-h-[100px] transition-all">
        <!-- Initial State SVG -->
        <div class="flex flex-col items-center justify-center py-20 opacity-20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p class="text-sm font-medium tracking-widest uppercase">Waiting for Selection</p>
        </div>
    </div>   
</div>

<?php include('footer.php'); ?>