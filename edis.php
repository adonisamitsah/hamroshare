<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("edis");


?>

<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-6">
    <div class="flex items-center gap-4">
        <div class="p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-2xl text-indigo-400 shadow-[inset_0_0_15px_rgba(99,102,241,0.1)]">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">EDIS Settlement Ledger</h1>
            <p class="text-slate-400 text-sm mt-1">Per-user automation matrix for WACC, Holdings, and Transfers.</p>
        </div>
    </div>

    <div class="flex flex-wrap gap-3">
        <button id="btn_waterfall" onclick="runWaterfall()" 
                class="bg-indigo-600 hover:bg-indigo-500 text-white px-6 py-3 rounded-xl text-xs font-black uppercase tracking-widest transition-all shadow-lg shadow-indigo-900/30 flex items-center justify-center gap-2 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
            <svg id="icon_waterfall" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
            </svg>
            <span id="text_waterfall">Run All (Waterfall)</span>
        </button>
    </div>
</div>

<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-xl relative">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-900/80 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest w-12">SN</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest w-1/4">Account Profile</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Live Status Log</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right w-32">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60" id="edis_table_body">

<?php 
$query = "SELECT * FROM users WHERE is_active=1;";
$result = $db->query($query);
$sn = 1;
while($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $name = htmlspecialchars($row['name'] ?? $row['username']);
    $dmat = htmlspecialchars($row['dmat_num']);
    
    echo "
    <tr class='hover:bg-slate-800/20 transition-colors group' data-dmat='{$dmat}'>
        <td class='px-6 py-4 text-xs text-slate-600 font-mono align-top pt-5'>".sprintf("%02d", $sn)."</td>
        
        <td class='px-6 py-4 align-top'>
            <span class='text-sm font-semibold text-slate-200 block'>{$name}</span>
            <span class='text-[10px] text-slate-500 font-mono'>ID: ".substr($dmat, 0, 8)."<span class='text-slate-400'>".substr($dmat, -8)."</span></span>
        </td>
        
        <td class='px-6 py-4 align-top' id='log_{$dmat}'>
            <div class='text-xs font-mono text-slate-500 py-1'>Ready for execution.</div>
        </td>

        <td class='px-6 py-4 align-top text-right'>
            <button id='btn_{$dmat}' onclick=\"processSingleUser('{$dmat}')\" 
                    class='edis-row-btn w-full bg-slate-800 hover:bg-slate-700 text-slate-200 text-[10px] font-bold py-2 px-4 rounded-lg transition-all border border-slate-700 active:scale-95 flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed'>
                <svg xmlns='http://www.w3.org/2000/svg' class='h-3.5 w-3.5 text-indigo-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M13 10V3L4 14h7v7l9-11h-7z'/>
                </svg>
                Process EDIS
            </button>
        </td>
    </tr>";
    $sn++;
}
?>

            </tbody>
        </table>
    </div>
</div>

<script>
/**
 * UI Log Formatting Helpers
 */
const HTML_SPINNER = `<div class="flex items-center gap-2 text-xs font-mono text-indigo-400"><svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span>Running Pipeline...</span></div>`;

function generateLogHTML(response) {
    if (response.status === 'error') {
        return `<div class="text-xs font-mono text-rose-500 bg-rose-500/10 border border-rose-500/20 px-3 py-1.5 rounded-lg inline-block">🚨 ${response.message}</div>`;
    }

    let logs = [];
    
    if (response.transfers_done > 0) {
        logs.push(`<div class="text-xs font-mono text-emerald-400 mb-1">✅ Transferred: ${response.transfers_done} scrips</div>`);
    } else if (response.danger_obligations.length === 0 && response.errors.length === 0) {
        logs.push(`<div class="text-xs font-mono text-slate-400 mb-1">✓ No pending EDIS obligations.</div>`);
    }

    if (response.danger_obligations && response.danger_obligations.length > 0) {
        logs.push(`<div class="text-xs font-mono text-rose-400 bg-rose-500/10 border border-rose-500/20 px-2 py-1 rounded mt-1">🚨 WACC/Holding Action Required: ${response.danger_obligations.join(', ')}</div>`);
    }

    if (response.errors && response.errors.length > 0) {
        response.errors.forEach(err => {
            logs.push(`<div class="text-xs font-mono text-amber-500 mt-1">⚠️ ${err}</div>`);
        });
    }

    return logs.join('');
}

/**
 * Executes EDIS for a single user via AJAX
 */
async function processSingleUser(dmat) {
    const logCell = document.getElementById(`log_${dmat}`);
    const btn = document.getElementById(`btn_${dmat}`);

    // Set UI to loading
    btn.disabled = true;
    logCell.innerHTML = HTML_SPINNER;

    try {
        const response = await fetch('ajax_single_edis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ dmat: dmat })
        });
        
        const res = await response.json();
        
        // Render Output
        logCell.innerHTML = generateLogHTML(res);
        
        // Modify button to show completion
        btn.innerHTML = `<svg xmlns='http://www.w3.org/2000/svg' class='h-3.5 w-3.5 text-emerald-400' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'/></svg> Done`;
        btn.classList.replace('bg-slate-800', 'bg-emerald-900/20');
        btn.classList.replace('border-slate-700', 'border-emerald-900/50');
        
    } catch (error) {
        logCell.innerHTML = generateLogHTML({ status: 'error', message: 'Network connection failed.' });
        btn.disabled = false;
        btn.innerHTML = "Retry";
    }
}

/**
 * Executes all rows sequentially (Waterfall pattern)
 */
async function runWaterfall() {
    const mainBtn = document.getElementById('btn_waterfall');
    const mainText = document.getElementById('text_waterfall');
    const mainIcon = document.getElementById('icon_waterfall');
    
    mainBtn.disabled = true;
    mainText.innerText = "Processing Waterfall...";
    mainIcon.classList.add('animate-bounce');

    // Get all rows
    const rows = document.querySelectorAll('#edis_table_body tr');
    
    // Process them sequentially using a standard for...of loop
    for (const row of rows) {
        const dmat = row.getAttribute('data-dmat');
        const btn = document.getElementById(`btn_${dmat}`);
        
        // Skip if already processed (disabled)
        if (btn && !btn.disabled) {
            // Await forces it to finish before moving to the next row
            await processSingleUser(dmat);
            
            // Optional: slight delay between requests to simulate human pacing and respect CDSC rate limits
            await new Promise(r => setTimeout(r, 1000)); 
        }
    }

    // Reset Master Button
    mainText.innerText = "Waterfall Complete";
    mainIcon.classList.remove('animate-bounce');
    mainBtn.classList.replace('bg-indigo-600', 'bg-emerald-600');
}
</script>

<?php include('footer.php'); ?>