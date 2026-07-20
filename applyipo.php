<?php
require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("applyipo");

$query1 = "SELECT value FROM constant WHERE key='appliedKitta' LIMIT 1;";
$result = $db->query($query1);
while ($row = $result->fetchArray()) {
    $kittaqty = $row['value'];
}
?>

<!-- Header & Global Settings Section -->
<div class="mb-8 flex flex-col lg:flex-row gap-6">

    <!-- Update Page Title Section -->
    <div class="flex-1">
        <h1 class="text-2xl font-bold text-white tracking-tight">Bulk IPO Application</h1>
        <div class="flex items-center gap-3 mt-2">
            <p class="text-slate-400 text-sm max-w-xl leading-relaxed">Automate your IPO applications across all linked accounts.</p>

        </div>
    </div>

    <!-- Kitta Configuration Card -->
    <div class="bg-[#161b22] border border-slate-800 p-5 rounded-2xl shadow-sm min-w-[300px]">
        <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-widest mb-3">Default Share Quantity (Kitta)</label>
        <div class="flex gap-2">
            <input type="number" id="kitta" value="<?php echo $kittaqty; ?>"
                class="bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-white w-full focus:ring-2 focus:ring-blue-600/50 outline-none transition-all" required />
            <button onclick="updateKitta();"
                class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-all active:scale-95 shadow-lg shadow-blue-900/20">
                Update
            </button>
        </div>
        <p id="log_updateKitta" class="mt-2 text-[10px] text-green-400 font-mono"></p>
    </div>
</div>

<!-- Search & Filter Area -->
<div class="mb-6">
    <div class="relative max-w-md">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-search text-xs"></i>
        </span>
        <input type="text" id="table_search"
            class="block w-full pl-10 pr-3 py-2.5 bg-[#161b22] border border-slate-700 rounded-xl text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-600/50 focus:border-blue-600 transition-all"
            placeholder="Search by name or demat..." />
    </div>
</div>
<!-- In your Header Section -->
<div id="global-engine-control" class="bg-[#11151c] border border-slate-800 p-4 rounded-2xl mb-6 flex items-center justify-between animate-in fade-in zoom-in duration-500">
    <div class="flex items-center gap-4 flex-1">
        <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center">
            <i id="engine-icon" class="fas fa-robot text-blue-500"></i>
        </div>
        <div class="flex-1 max-w-md">
            <h3 class="text-sm font-bold text-white uppercase tracking-tight">IPO Automation Engine</h3>
            <!-- Lively Status Text -->
            <p id="engine-status" class="text-[10px] text-slate-500 font-mono uppercase tracking-wider">System Idle // Ready for handshake</p>

            <!-- Global Progress Bar (Hidden by default) -->
            <div id="global-progress-container" class="hidden w-full bg-slate-800 h-1 rounded-full overflow-hidden mt-2">
                <div id="global-progress-bar" class="bg-blue-500 h-full w-0 transition-all duration-300"></div>
            </div>
        </div>
    </div>

    <div id="engine-actions">
        <button id="main-bulk-btn" onclick="startIntegratedWaterfall()"
            class="bg-blue-600 hover:bg-blue-500 text-white text-[11px] font-black px-6 py-2.5 rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-900/20 uppercase tracking-widest">
            Initialize Bulk Login
        </button>
    </div>
</div>

<!-- Table Container -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">SN</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Name</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Demat Account</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Process</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Live Status Log</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php
                $query1 = "SELECT * FROM users WHERE is_active=1;";
                $result = $db->query($query1);
                $sn = 1;
                while ($row = $result->fetchArray()) {
                    echo '
                    <tr class="hover:bg-slate-800/20 transition-colors group">
                        <td class="px-6 py-4 text-xs text-slate-500 font-mono">' . sprintf("%02d", $sn) . '</td>
                        <td class="px-6 py-4">
    <a href="user.php?dmat=' . $row['dmat_num'] . '" class="group block">
        <div class="flex flex-col">
            <!-- Name: Gets brighter on hover -->
            <span class="text-sm font-semibold text-slate-200 group-hover:text-blue-400 transition-colors">
                ' . $row['name'] . '
            </span>
            
            <!-- DP Name: Subtle underline or arrow hint on hover -->
            <span class="text-[10px] text-blue-500 font-mono uppercase tracking-tighter flex items-center gap-1">
                ' . $row['dpName'] . '
                <i class="fas fa-arrow-right opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-[8px]"></i>
            </span>
        </div>
    </a>
</td>
                        <td class="px-6 py-4">
                            <span class="text-xs text-slate-400 font-mono">' . $row['dmat_num'] . '</span>
                        </td>
                        <td class="px-6 py-4 text-center" id="btn_' . $row['dmat_num'] . '">
                            <button class="bg-slate-700 hover:bg-blue-600 text-white text-xs font-bold py-2 px-6 rounded-lg transition-all active:scale-95 shadow-md shadow-black/20" 
                                    id="applyipo_' . $row['dmat_num'] . '" 
                                    onclick="generateButtons(\'' . $row['dmat_num'] . '\');">
                                Start Session
                            </button>
                        </td>
                        <td class="px-6 py-4">
                            <div id="log_' . $row['dmat_num'] . '" class="text-[11px] font-mono text-slate-500 italic">Idle...</div>
                        </td>
                    </tr>';
                    $sn++;
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
    /**
     * IPO Automation Engine - Integrated Waterfall Logic
     * Modern Tech Theme / Google Dark Style
     */

    // Global State
    window.bulkEngine = {
        queue: [],
        total: 0
    };

    // 1. PHASE 1: LOGIN WATERFALL
    window.startIntegratedWaterfall = function() {
        console.log("Engine: Initializing Waterfall...");
        bulkEngine.queue = [];

        // Select only 'Start Session' buttons
        const targetButtons = $("button[id^='applyipo_']").filter(function() {
            return $(this).text().trim().toLowerCase() === "start session";
        });

        bulkEngine.total = targetButtons.length;
        if (bulkEngine.total === 0) {
            alert("No 'Start Session' targets found in the table.");
            return;
        }

        targetButtons.each(function() {
            const dmat = $(this).attr('id').split('_')[1];
            bulkEngine.queue.push(dmat);
        });

        // UI Transformation
        $('#main-bulk-btn').fadeOut(200);
        $('#global-progress-container').removeClass('hidden').show();
        $('#engine-icon').addClass('fa-spin text-blue-400').removeClass('text-blue-500');

        runWaterfallCycle();
    };

    window.runWaterfallCycle = function() {
        if (bulkEngine.queue.length === 0) {
            finalizeSessionPhase();
            return;
        }

        const dmat = bulkEngine.queue.shift();
        const current = bulkEngine.total - bulkEngine.queue.length;
        const percent = (current / bulkEngine.total) * 100;

        // Update Header Status
        $('#engine-status').html(`<span class="text-blue-400 animate-pulse">Connecting:</span> ${dmat} <span class="text-slate-700">// [${current}/${bulkEngine.total}]</span>`);
        $('#global-progress-bar').css('width', percent + '%');

        // Trigger the background table login logic (from your php_function)
        if (typeof generateButtons === "function") {
            generateButtons(dmat);
        } else {
            console.error("Function generateButtons() not found. Ensure php_function.php is working.");
        }

        // Delay to prevent API congestion
        setTimeout(runWaterfallCycle, 3200);
    };

    // 2. PHASE 2: COMPANY INDEXING
    window.finalizeSessionPhase = function() {
        $('#engine-icon').removeClass('fa-spin text-blue-400').addClass('text-emerald-500');
        $('#engine-status').html(`<span class="text-emerald-500 font-bold">Handshake Complete</span> // Choose company to apply bulk`);

        // Scrape all unique company names that appeared in the table
        const companies = new Set();
        $("button[onclick^='applyIPO']").each(function() {
            const txt = $(this).text().trim();
            if (txt && txt.toLowerCase() !== "start session") {
                companies.add(txt);
            }
        });

        if (companies.size === 0) {
            $('#engine-status').html(`<span class="text-amber-500">No active issues found</span> // All sessions idle or failed`);
            $('#main-bulk-btn').fadeIn(200).text('Retry Bulk Login');
        } else {
            // Build Action Buttons
            let html = `<div class="flex flex-wrap gap-2 animate-in slide-in-from-right duration-500">`;
            companies.forEach(company => {
                html += `
                <button onclick="executeBulkApply('${company}')" 
                        class="bg-slate-800 hover:bg-blue-600 border border-slate-700 text-white text-[10px] font-bold px-4 py-2 rounded-lg transition-all active:scale-95 shadow-md uppercase tracking-wider">
                    Apply ${company}
                </button>`;
            });
            html += `</div>`;
            $('#engine-actions').hide().html(html).fadeIn(400);
        }
    };

    // 3. PHASE 3: BULK APPLICATION WATERFALL
    window.executeBulkApply = function(company) {
        // Collect all rows that actually have the button for this company
        const targets = [];
        $("button").filter(function() {
            return $(this).text().trim() === company && $(this).attr('onclick').includes('applyIPO');
        }).each(function() {
            const idParts = $(this).attr('id').split('_');
            if (idParts[1]) targets.push(idParts[1]);
        });

        const totalApply = targets.length;
        if (totalApply === 0) {
            $('#engine-status').html(`<span class="text-rose-500">Error:</span> Could not locate ${company} targets.`);
            return;
        }

        // Reset Progress Bar for Application Phase
        $('#engine-icon').addClass('fa-spin text-blue-400').removeClass('text-emerald-500');
        $('#global-progress-bar').css('width', '0%').removeClass('bg-blue-500').addClass('bg-emerald-500');

        targets.forEach((dmat, i) => {
            setTimeout(() => {
                const current = i + 1;
                const percent = (current / totalApply) * 100;

                // Lively Status Update
                $('#engine-status').html(`<span class="text-emerald-400 animate-pulse">Applying ${company}:</span> ${dmat} <span class="text-slate-700">// [${current}/${totalApply}]</span>`);
                $('#global-progress-bar').css('width', percent + '%');

                // Find the specific button in the table cell and FORCE CLICK
                const btn = $(`#btn_${dmat} button`).filter(function() {
                    return $(this).text().trim() === company;
                });

                if (btn.length > 0) {
                    btn[0].click(); // Triggers the inline applyIPO() function

                    // Visual row feedback
                    const row = btn.closest('tr');
                    row.addClass('bg-emerald-500/5');
                    setTimeout(() => row.removeClass('bg-emerald-500/5'), 1500);
                }

                // Final Completion
                if (current === totalApply) {
                    setTimeout(() => {
                        $('#engine-icon').removeClass('fa-spin text-blue-400').addClass('text-emerald-500');
                        $('#engine-status').html(`<span class="text-emerald-500 font-bold">Success</span> // ${totalApply} applications processed`);

                        // Cleanup progress bar after 5 seconds
                        setTimeout(() => {
                            $('#global-progress-container').fadeOut();
                        }, 5000);
                    }, 1000);
                }
            }, i * 2000); // 2-second stagger for MeroShare stability
        });
    };
</script>