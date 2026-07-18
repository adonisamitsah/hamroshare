<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("ipo-result");
?>

<!-- Header Section -->
<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">IPO Result Terminal</h1>
        
        <p class="text-slate-400 text-sm mt-1">
            Bulk-verify allotment status. Discover application history and sync with Meroshare servers.
        </p>
        <a href="view-all-ipo-results.php" class="text-xs font-bold font-mono tracking-widest uppercase text-blue-400 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/20 px-3 py-1 rounded-lg transition-all shadow-inner">
    <i class="fas fa-database mr-1"></i> Live Ledger
</a>
    </div>

</div>

<!-- Search & Export Utility -->
<div class="mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="relative flex-1 max-w-md">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-search text-xs"></i>
        </span>
        <input type="text" id="table_search" 
               class="block w-full pl-10 pr-3 py-2.5 bg-[#161b22] border border-slate-800 rounded-xl text-sm text-slate-300 placeholder-slate-600 focus:ring-1 focus:ring-blue-500 outline-none transition-all" 
               placeholder="Search by investor or scrip..." />
    </div>
    
    <div class="flex gap-2">
        <button onclick="convertIpoResultTableToImage()" class="p-2.5 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-400 hover:text-white transition-colors" title="Export Report">
            <i class="fas fa-camera text-sm"></i>
        </button>
    </div>
</div>
<!-- Master Engine Controller -->
<div id="global-engine-control" class="bg-[#11151c] border border-slate-800 p-4 rounded-2xl mb-6 flex items-center justify-between animate-in fade-in zoom-in duration-500">
    <div class="flex items-center gap-4 flex-1">
        <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center">
            <i id="engine-icon" class="fas fa-microchip text-blue-500"></i>
        </div>
        <div class="flex-1 max-w-xl">
            <h3 class="text-sm font-bold text-white uppercase tracking-tight">IPO Result Engine</h3>
            <!-- Live Status Text -->
            <p id="engine-status" class="text-[10px] text-slate-500 font-mono uppercase tracking-wider">Engine Standby // Waiting for Sync or Verification</p>
            
            <!-- Global Progress Bar -->
            <div id="global-progress-container" class="hidden w-full bg-slate-800 h-1 rounded-full overflow-hidden mt-2">
                <div id="global-progress-bar" class="bg-blue-500 h-full w-0 transition-all duration-300"></div>
            </div>
        </div>
    </div>

    <div id="engine-actions" class="flex gap-2">
        <button id="main-sync-btn" onclick="waterfallSyncHistory()" 
                class="bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black px-5 py-2.5 rounded-xl transition-all active:scale-95 uppercase tracking-widest shadow-lg shadow-blue-900/20">
            Sync All History
        </button>
    </div>
</div>

<!-- Dynamic Summary Section (Hidden initially) -->
<div id="summary-section" class="hidden grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 animate-in slide-in-from-top-4">
    <!-- Stats cards injected here by JS -->
</div>

<!-- Results Table -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table id="ipo-result-table" class="w-full text-left border-collapse">
            <thead>
    <tr class="bg-slate-800/30 border-b border-slate-800">
        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest w-16">SN</th>
        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest w-64">Investor Profile</th>
        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
        <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Detailed Allotment Data</th>
    </tr>
</thead>
            <tbody id="ipo-result-table-body" class="divide-y divide-slate-800">
                <?php 
                $query1 = "SELECT * FROM users WHERE is_active=1;";
                $db->busyTimeout(10000);
                $result = $db->query($query1);
                $sn = 1;
                while($row = $result->fetchArray(SQLITE3_ASSOC)){
                    $dmat = $row['dmat_num'];
                    $name = $row['name'];
                    ?>
                    <tr class="hover:bg-slate-800/20 transition-colors" id="row_<?php echo $dmat; ?>">
                        <td class="px-6 py-4 text-xs text-slate-600 font-mono"><?php echo sprintf("%02d", $sn); ?></td>
                        


<td class="px-6 py-4">
    <a href="user.php?dmat=<?php echo $dmat; ?>" class="group block">
        <div class="flex flex-col">
            <!-- Name: Gets brighter on hover -->
            <span class="text-sm font-semibold text-slate-200 group-hover:text-blue-400 transition-colors">
                <?php echo $name; ?>
            </span>
            
            <!-- DP Name: Subtle underline or arrow hint on hover -->
            <span class="text-[10px] text-blue-500 font-mono uppercase tracking-tighter flex items-center gap-1">
                <?php echo $dmat; ?>
                <i class="fas fa-arrow-right opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-[8px]"></i>
            </span>
        </div>
    </a>
</td>


                        
                        <td class="px-6 py-4 text-center">
                            <button onclick="syncHistory('<?php echo $dmat; ?>')" 
                                    id="btn_sync_<?php echo $dmat; ?>"
                                    class="bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold py-1.5 px-3 rounded-lg transition-all border border-slate-700 flex items-center gap-2 mx-auto">
                                <i class="fas fa-cloud-download-alt opacity-60"></i> Fetch Report
                            </button>
                        </td>
                        <td class="px-6 py-4 min-w-[400px]" id="log_<?php echo $dmat; ?>">
    <!-- This container must stay empty until Sync is clicked -->
    <div class="text-[11px] text-slate-600 italic">Sync history to view report...</div>
</td>
                    </tr>
                    <?php
                    $sn++;
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
// Search Functionality
$("#table_search").on("keyup", function() {
    var value = $(this).val().toLowerCase();
    $("#ipo-result-table-body tr").filter(function() {
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
});

// Sync History logic
function syncHistory(dmat) {
    const btn = $(`#btn_sync_${dmat}`);
    const log = $(`#log_${dmat}`);
    
    btn.addClass('opacity-50 pointer-events-none').html('<i class="fas fa-spinner fa-spin"></i> Syncing...');
    
    $.getJSON(`sync_ipo_history.php?dmat_num=${dmat}`, function(data) {
        btn.removeClass('opacity-50 pointer-events-none').html('<i class="fas fa-cloud-download-alt opacity-60"></i> Refresh');
        
        if (data && data.length > 0) {
    let scripHtml = '';
    // We show the recent applications in the scrip container
    // Using slice(0, 5) to keep the UI clean, though you can use 100 if preferred
    data.slice(0, 5).forEach(item => {
        scripHtml += `<span class="bg-slate-900 border border-slate-800 text-slate-400 px-2 py-0.5 rounded text-[9px] font-bold">${item.scrip}</span>`;
    });
    $(`#scrip_container_${dmat}`).html(scripHtml);
    
    // Pass the entire data array to the display function
    updateResultDisplay(dmat, data);
} else {
    // Clear the loading states first
    $(`#scrip_container_${dmat}`).html('<span class="text-[10px] text-slate-600 italic">No record found</span>');
    $(`#log_${dmat}`).html('<span class="text-[10px] text-slate-500 italic">Discovery failed</span>');

    // Trigger the custom Sentinel Modal
    showSentinelModal(
        "No Data Received", 
        `The server returned an empty result for this DMAT. This typically happens if the session has timed out. <br><br>Please <b>re-login</b> to this account and then retry fetching the data.`, 
        "info"
    );
}
    });
}


function msCheckResult(dmat, formId, scrip) {
    // 1. Show a loading state inside the specific log area
    $(`#log_${dmat}`).html('<div class="flex items-center gap-2"><i class="fas fa-spinner fa-spin text-blue-500"></i> <span class="text-[10px] text-blue-500 font-bold uppercase">Updating Result...</span></div>');
    
    // 2. Perform the update
    $.getJSON(`refresh_single_result.php?dmat_num=${dmat}&formId=${formId}&scrip=${scrip}`, function(response) {
        // 3. IMPORTANT: Instead of showing 'response', we re-trigger syncHistory.
        // This fetches the top 5 from the DB (including the one we just updated) 
        // and redraws the full table.
        syncHistory(dmat); 
    }).fail(function() {
        // Log error to console for forensic tracing
        console.error("Failed to fetch result for DMAT:", dmat);
        
        // Inject our custom modal
        showSentinelModal(
            "Sync Failed", 
            `Could not update result for <b>${scrip}</b>. This is usually due to a session timeout or CDSC server load. Please try again in a few seconds or re-login.`, 
            "error"
        );
        syncHistory(dmat); // Revert to list on failure
    });
}

function waterfallSyncHistory() {
    const rows = $("#ipo-result-table-body tr");
    rows.each(function(index) {
        const dmat = this.id.replace('row_', '');
        setTimeout(() => {
            syncHistory(dmat);
        }, index * 500); // 1.5s delay to avoid firewall blocking
    });
}


window.engineState = {
    syncQueue: [],
    verifyQueue: [],
    neverChecked: [],
    unverified: [],
    total: 0
};

// --- PHASE 1: Syncing All History ---
window.waterfallSyncHistory = function() {
    window.engineState.syncQueue = [];
    const rows = $("#ipo-result-table-body tr");
    window.engineState.total = rows.length;

    rows.each(function() {
        window.engineState.syncQueue.push(this.id.replace('row_', ''));
    });

    if (window.engineState.total === 0) return;

    $('#main-sync-btn').fadeOut(200);
    $('#global-progress-container').removeClass('hidden').show();
    $('#engine-icon').addClass('fa-spin text-blue-400');
    
    processSyncWaterfall();
};

window.processSyncWaterfall = function() {
    if (window.engineState.syncQueue.length === 0) {
        // Wait for all AJAX processes to settle before counting
        setTimeout(finalizeSyncPhase, 2000);
        return;
    }

    const dmat = window.engineState.syncQueue.shift();
    const current = window.engineState.total - window.engineState.syncQueue.length;
    const percent = (current / window.engineState.total) * 100;

    $('#engine-status').html(`<span class="text-blue-400 animate-pulse">Syncing History:</span> ${dmat}`);
    $('#global-progress-bar').css('width', percent + '%');

    syncHistory(dmat);
    setTimeout(processSyncWaterfall, 1500); 
};

// --- PHASE 2: Precise Analysis ---
window.finalizeSyncPhase = function() {
    $('#engine-icon').removeClass('fa-spin text-blue-400').addClass('text-emerald-500');
    
    // 1. Initialize with 0 to prevent undefined/NaN
    let stats = {
        allotted: 0,
        notAllotted: 0,
        verified: 0,
        rejected: 0,
        unverified: [], 
        unknown: 0 // Ensure this is 0
    };

    console.log("--- Starting Analysis ---");

    $("#ipo-result-table-body tr[id^='row_']").each(function() {
        const dmat = this.id.replace('row_', '');
        const logCell = $(`#log_${dmat}`);
        const statusDots = logCell.find("span[id^='status-']");

        statusDots.each(function() {
            const dotElement = $(this);
            const statusId = dotElement.attr('id');
            const scrip = statusId.split('-')[1];
            
            // Clean extraction
            let container = dotElement.parent().clone();
            container.children().remove(); 
            const statusText = container.text().trim().toLowerCase();

            // DEBUG: See exactly what string we are testing against
            console.log(`DMAT: ${dmat} | Scrip: ${scrip} | Raw Text: "${statusText}"`);

            const verifyBtn = logCell.find(`#check-btn-${scrip}-dot-${dmat}`);

            // 3. CATEGORIZATION with spelling fix
            if (statusText === "allotted" || statusText === "alloted") {
                stats.allotted++;
                console.log("-> Matched: Allotted");
            } 
            else if (statusText === "not allotted" || statusText === "not alloted") {
                stats.notAllotted++;
                console.log("-> Matched: Not Allotted");
            } 
            else if (statusText === "verified") {
                stats.verified++;
                console.log("-> Matched: Verified");
            } 
             else if (statusText === "rejected") {
                stats.rejected++;
                console.log("-> Matched: Rejected");
            } 
            else if (statusText === "unverified") {
                const onclickAttr = verifyBtn.attr('onclick');
                if (onclickAttr) {
                    const args = onclickAttr.match(/'([^']+)'/g).map(s => s.replace(/'/g, ""));
                    stats.unverified.push({ dmat: args[0], formId: args[1], scrip: args[2] });
                    console.log("-> Matched: Unverified (Added to Queue)");
                }
            } 
            else {
                stats.unknown++;
                console.warn(`-> Unknown detected: "${statusText}"`);
            }
        });
    });

    console.log("Final Stats:", stats);
    renderSummaryGrid(stats);
};

// --- PHASE 3: The UI Display Function ---
window.renderSummaryGrid = function(stats) {
    const summaryHtml = `
    <style>
        @keyframes custom-pulse {
            0% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(1.2); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes ring-glow {
            0% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(249, 115, 22, 0); }
            100% { box-shadow: 0 0 0 0 rgba(249, 115, 22, 0); }
        }
        .animate-status-pulse { animation: custom-pulse 2s infinite; }
        .animate-status-glow { animation: ring-glow 2s infinite; }
    </style>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 w-full">
        <!-- Allotted -->
        <div class="bg-emerald-500/5 border border-emerald-500/20 p-3 rounded-xl">
            <div class="text-[9px] text-emerald-500 font-black uppercase flex items-center gap-1">
                <svg class="w-2 h-2" fill="currentColor" viewBox="0 0 20 20"><path d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 111.414-1.414L9 10.586l3.293-3.293a1 1 0 011.414 1.414z"/></svg>
                Allotted
            </div>
            <div class="text-lg font-mono text-emerald-400 font-bold">${stats.allotted}</div>
        </div>

        <!-- Not Allotted -->
        <div class="bg-rose-500/5 border border-rose-500/20 p-3 rounded-xl opacity-60">
            <div class="text-[9px] text-rose-500 font-black uppercase">Not Allotted</div>
            <div class="text-lg font-mono text-rose-400 font-bold">${stats.notAllotted}</div>
        </div>

        <!-- Verified -->
        <div class="bg-blue-500/5 border border-blue-500/20 p-3 rounded-xl">
            <div class="text-[9px] text-blue-500 font-black uppercase flex items-center gap-1">
                <svg class="w-2 h-2" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                Verified
            </div>
            <div class="text-lg font-mono text-blue-400 font-bold">${stats.verified}</div>
        </div>
        
        <!-- Unverified (Indigo Glow) -->
        <div class="bg-indigo-500/10 border border-indigo-400/40 p-3 rounded-xl shadow-[0_0_10px_rgba(99,102,241,0.1)]">
            <div class="text-[9px] text-indigo-400 font-black uppercase flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 animate-status-pulse"></span>
                Unverified
            </div>
            <div class="text-lg font-mono text-indigo-300 font-bold">${stats.unverified.length}</div>
        </div>
        
        <!-- Rejected (High Attention SVG) -->
        <div class="bg-orange-500/15 border-2 border-orange-500/60 p-3 rounded-xl animate-status-glow">
            <div class="text-[9px] text-orange-500 font-black uppercase flex items-center gap-1">
                <svg class="w-2.5 h-2.5 animate-status-pulse" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                Rejected
            </div>
            <div class="text-lg font-mono text-orange-400 font-bold">${stats.rejected}</div>
        </div>

        <!-- Unknown -->
        <div class="bg-slate-800/40 border border-slate-700 p-3 rounded-xl opacity-50">
            <div class="text-[9px] text-slate-500 font-black uppercase">Unknown</div>
            <div class="text-lg font-mono text-slate-400 font-bold">${stats.unknown}</div>
        </div>
    </div>
    
    <div class="mt-4 flex justify-end">
        ${stats.unverified.length > 0 ? `
            <button onclick="batchVerifyUnverified()" class="group relative bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-black px-8 py-3 rounded-xl transition-all shadow-xl shadow-indigo-900/30 uppercase tracking-widest active:scale-95 overflow-hidden">
                <div class="flex items-center gap-2 relative z-10">
                    <svg class="w-3 h-3 group-hover:rotate-180 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Run Bank Check (${stats.unverified.length})
                </div>
            </button>
        ` : ''}
    </div>
    `;
    $('#summary-section').html(summaryHtml).fadeIn();
    // --- ADD THIS LINE ---
window.engineState.unverified = stats.unverified; 

// Then call the grid
renderSummaryGrid(stats);
};

// Global variable to store original modal function
window.originalModalFunc = null;

window.batchVerifyUnverified = function() {
    // 1. Check if there is anything to verify BEFORE muting the modal
    const queue = [...window.engineState.unverified];

    if (queue.length === 0) {
        // Use your themed modal instead of the browser alert
        showSentinelModal(
            "No Actions Required", 
            "There are currently no 'Unverified' scrips in the list that require bank synchronization.", 
            "info"
        );
        return;
    }

    // 2. Setup Silent Mode for the batch process
    if (!window.originalModalFunc) window.originalModalFunc = window.showSentinelModal;
    
    // Mute modals during the loop: redirect text to the status bar
    window.showSentinelModal = function(title) { 
        $('#engine-status').html(`<span class="text-amber-400">Response:</span> ${title}`);
    };

    // 3. Start the process
    window.engineState.verifyQueue = queue;
    window.engineState.total = queue.length;
    
    // UI Feedback
    $('#engine-icon').addClass('fa-spin text-amber-400');
    $('#global-progress-bar').css('width', '0%').addClass('bg-amber-500').show();
    
    processSilentWaterfall();
};

window.processSilentWaterfall = function() {
    if (window.engineState.verifyQueue.length === 0) {
        // Restore modals for manual use
        if (window.originalModalFunc) window.showSentinelModal = window.originalModalFunc;
        
        $('#engine-icon').removeClass('fa-spin text-amber-400').addClass('text-emerald-500');
        $('#engine-status').html(`<span class="text-emerald-500 font-bold">Check Complete</span> // All unverified scrips updated`);
        return;
    }

    const item = window.engineState.verifyQueue.shift();
    const current = window.engineState.total - window.engineState.verifyQueue.length;
    const percent = (current / window.engineState.total) * 100;

    $('#global-progress-bar').css('width', percent + '%');
    $('#engine-status').html(`<span class="text-amber-400 animate-pulse">Checking Bank Approval:</span> ${item.scrip}`);

    // Call your existing MeroShare check function
    msCheckResult(item.dmat, item.formId, item.scrip);

    // 2.2 seconds delay to prevent rate-limiting
    setTimeout(processSilentWaterfall, 2200);
};
</script>
