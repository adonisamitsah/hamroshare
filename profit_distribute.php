<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// 1. Fetch All Active Users for Dropdown Menu
$users_stmt = $db->query("SELECT name, username, dmat_num, profit_dist_split_para FROM users ORDER BY name ASC, username ASC;");
$all_users = [];
while ($u = $users_stmt->fetchArray(SQLITE3_ASSOC)) {
    $all_users[] = $u;
}

// 2. Handle Global Profit Distribution Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_profit_distribution'])) {
    $target_dmat = $_POST['dmat_num'];
    $dist_date = trim($_POST['date']);
    $scrip_name = strtoupper(trim($_POST['scrip_name']));
    $invest_amt = floatval($_POST['invest_amt']);
    $net_receivable = floatval($_POST['net_receivable']);


    
    // Split snapshot parameters explicitly frozen at transaction time
    $f_m_pct = floatval($_POST['manager_pct']);
    $f_c_pct = floatval($_POST['client_pct']);
    $f_a_pct = floatval($_POST['agent_pct']);
    
    // Profit distributions calculated exactly from form payloads
    $manager_profit = floatval($_POST['manager_profit']);
    $client_profit = floatval($_POST['client_profit']);
    $agent_profit = floatval($_POST['agent_profit']);
    
    $status = $_POST['status'];

    if (empty($target_dmat)) {
        echo "<script>alert('Error: You must select a user profile to log the distribution against.');</script>";
    } elseif (($f_m_pct + $f_c_pct + $f_a_pct) !== 100.0) {
        echo "<script>alert('Error: Splitting scheme values configuration mathematical mismatch.');</script>";
    } else {
        $insert_stmt = $db->prepare("
            INSERT INTO profit_distributions (
                dmat_num, date, scrip_name, invest_amt, net_receivable, 
                manager_pct, client_pct, agent_pct, 
                manager_profit, client_profit, agent_profit, status
            ) VALUES (
                :dmat, :date, :scrip, :invest, :net, 
                :mpct, :cpct, :apct, 
                :mprof, :cprof, :aprof, :status
            );
        ");
        
        $insert_stmt->bindValue(':dmat', $target_dmat, SQLITE3_TEXT);
        $insert_stmt->bindValue(':date', $dist_date, SQLITE3_TEXT);
        $insert_stmt->bindValue(':scrip', $scrip_name, SQLITE3_TEXT);
        $insert_stmt->bindValue(':invest', $invest_amt, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':net', $net_receivable, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':mpct', $f_m_pct, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':cpct', $f_c_pct, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':apct', $f_a_pct, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':mprof', $manager_profit, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':cprof', $client_profit, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':aprof', $agent_profit, SQLITE3_FLOAT);
        $insert_stmt->bindValue(':status', $status, SQLITE3_TEXT);

        if ($insert_stmt->execute()) {
    usleep(300000); // Sleep for 0.3 seconds to ensure DB write completion before ledger entry
            //ledger entry for sold share
            initiateLedgerEntry(
        $db, 
        $target_dmat, 
        "Sold shares for " . $scrip_name , 
        $net_receivable,                 // Deposit
        0,   // Withdraw
        'IPO_SOLD'    // Type
    );


            header("Location: profit_distribute.php");
            exit();
        } else {
            echo "<script>alert('Error: Critical breakdown logging allocation details.');</script>";
        }
    }
}

// 3. Fetch Global Historical Distributions across ALL Profiles
$history_stmt = $db->query("
    SELECT p.*, COALESCE(u.name, u.username, p.dmat_num) as display_name 
    FROM profit_distributions p 
    LEFT JOIN users u ON p.dmat_num = u.dmat_num 
    ORDER BY p.date DESC, p.id DESC;
");

// Extract unique identifiers for the advanced dropdown filters
$unique_names = [];
$unique_scrips = [];
$history_rows = [];

while ($row = $history_stmt->fetchArray(SQLITE3_ASSOC)) {
    $history_rows[] = $row;
    if (!in_array($row['display_name'], $unique_names)) $unique_names[] = $row['display_name'];
    if (!in_array($row['scrip_name'], $unique_scrips)) $unique_scrips[] = $row['scrip_name'];
}
sort($unique_names);
sort($unique_scrips);

echo sudo_get_header("profit_distribution");
?>

<!-- html2canvas Library for Snapshot Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Header Breadcrumb Section -->
<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        
        <h1 class="text-2xl font-bold text-white tracking-tight">Master Profit Distributions</h1>
        <p class="text-slate-400 text-sm mt-0.5">Manage, log, and filter capital returns across the entire portfolio matrix.</p>
    </div>
</div>

<!-- Allocation Matrix Log Calculator Entry Form -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 mb-8 shadow-sm relative overflow-hidden">
    <!-- Decorative background element -->
    <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>
    
    <h3 class="text-xs font-bold text-emerald-500 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
        <i class="fas fa-plus-circle"></i> Log New Sales For Distribution
    </h3>
    
    <form id="distributionForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6 relative z-10">
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
            <!-- Account Target Selector -->
            <div class="space-y-1 lg:col-span-2">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Target Account Profile</label>
                <select name="dmat_num" id="user_select" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-emerald-600/50 outline-none transition-all cursor-pointer text-sm">
                    <option value="" data-split='{"manager_pct":80,"client_pct":20,"agent_pct":0}'>-- Select Target Profile --</option>
                    <?php foreach($all_users as $u): ?>
                        <option value="<?php echo $u['dmat_num']; ?>" data-split='<?php echo htmlspecialchars($u['profit_dist_split_para'] ?? '{"manager_pct":80,"client_pct":20,"agent_pct":0}', ENT_QUOTES, 'UTF-8'); ?>'>
                            <?php echo htmlspecialchars($u['name'] ?? $u['username']); ?> (DMAT: <?php echo substr($u['dmat_num'], -6); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Entry Date -->
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Date</label>
                <input id="nepali-date-pick" type="text" placeholder="e.g. 2082.11.08" name="date" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-emerald-600/50 outline-none transition-all font-mono placeholder:text-slate-600"/>
            </div>

            <!-- Scrip Identifier -->
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Scrip Name</label>
                <input type="text" placeholder="e.g. HRL" name="scrip_name" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-emerald-600/50 outline-none transition-all uppercase placeholder:text-slate-600"/>
            </div>

            <!-- Status Configuration -->
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Settlement State</label>
                <select name="status" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-emerald-600/50 outline-none transition-all cursor-pointer text-sm">
                    <option value="PENDING" selected>Pending</option>    
                    <option disabled value="WAITING_WITHDRAWAL">Waiting W/D</option>
                    <option disabled value="W&D" >Settled</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Capital Invested Amount -->
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Invested Amount (NRs.)</label>
                <input type="number" step="0.01" min="0.00" placeholder="0.00" id="invest_amt" name="invest_amt" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-emerald-600/50 outline-none transition-all font-mono placeholder:text-slate-600"/>
            </div>

            <!-- Total Net Receivable -->
            <div class="space-y-1">
    <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Net Receivable (NRs.)</label>
    <div class="flex items-center gap-2">
        <input type="number" step="0.01" min="0.00" placeholder="0.00" id="net_receivable" name="net_receivable" required
               class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-emerald-600/50 outline-none transition-all font-mono placeholder:text-slate-600"/>
        
        <button type="button" onclick="openCalcModal()" title="Calculate Net Receivable"
                class="bg-slate-800 border border-slate-700 p-3 rounded-xl text-emerald-500 hover:text-white hover:bg-emerald-600 transition-all cursor-pointer">
            <i class="fas fa-calculator text-sm"></i>
        </button>
    </div>
</div>
        </div>

        <!-- Dynamic Computation Sandbox Border Split Allocator Details -->
        <div class="p-5 bg-slate-900/40 border border-slate-800 rounded-xl">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider mb-2 border-b border-slate-800/80 pb-2">
    <i class="fas fa-calculator text-emerald-500"></i> Split Parameters: <span class="normal-case font-normal text-slate-500 ml-1">Net Profit Pool: NRs. <span id="net_profit_display" class="font-bold font-mono text-slate-300">0.00</span></span>
</div>

<!-- Simple Info Note -->
<div class="mb-4 p-3 bg-blue-500/5 border border-blue-500/20 rounded-xl flex items-start gap-2 text-xs text-slate-400">
    <i class="fas fa-info-circle text-blue-400 mt-0.5"></i>
    <div>
        <span class="font-bold text-slate-200">Note:</span> 
        To change these values, go to Ledger of specific client where you can change percentages by clicking the edit icon.
    </div>
</div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Manager Segment -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label class="text-[11px] text-blue-400 font-bold uppercase tracking-tight">Manager Cut</label>
                        <div class="flex items-center text-xs font-mono text-slate-500"><input type="number" step="0.1" id="m_pct_input" name="manager_pct" class="w-12 bg-transparent text-right outline-none text-blue-400 font-bold border-b border-transparent focus:border-blue-500" value="80.0">%</div>
                    </div>
                    <div class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm flex justify-between items-center h-[42px]">
                        <span class="text-slate-600 text-[10px]">PROFIT:</span>
                        <span class="font-bold text-slate-200">NRs. <span id="val_m_profit">0.00</span></span>
                    </div>
                    <input type="hidden" id="input_m_profit" name="manager_profit" value="0.00">
                </div>

                <!-- Client Segment -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label class="text-[11px] text-emerald-400 font-bold uppercase tracking-tight">Client Cut</label>
                        <div class="flex items-center text-xs font-mono text-slate-500"><input type="number" step="0.1" id="c_pct_input" name="client_pct" class="w-12 bg-transparent text-right outline-none text-emerald-400 font-bold border-b border-transparent focus:border-emerald-500" value="20.0">%</div>
                    </div>
                    <div class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm flex justify-between items-center h-[42px]">
                        <span class="text-slate-600 text-[10px]">PROFIT:</span>
                        <span class="font-bold text-slate-200">NRs. <span id="val_c_profit">0.00</span></span>
                    </div>
                    <input type="hidden" id="input_c_profit" name="client_profit" value="0.00">
                </div>

                <!-- Agent Segment -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center px-1">
                        <label class="text-[11px] text-amber-400 font-bold uppercase tracking-tight">Agent Cut</label>
                        <div class="flex items-center text-xs font-mono text-slate-500"><input type="number" step="0.1" id="a_pct_input" name="agent_pct" class="w-12 bg-transparent text-right outline-none text-amber-400 font-bold border-b border-transparent focus:border-amber-500" value="0.0">%</div>
                    </div>
                    <div class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm flex justify-between items-center h-[42px]">
                        <span class="text-slate-600 text-[10px]">PROFIT:</span>
                        <span class="font-bold text-slate-200">NRs. <span id="val_a_profit">0.00</span></span>
                    </div>
                    <input type="hidden" id="input_a_profit" name="agent_profit" value="0.00">
                </div>
            </div>
            
            <div id="split_math_warning" class="text-[10px] font-bold text-rose-400 uppercase tracking-wider mt-3 pl-1 hidden">
                <i class="fas fa-exclamation-circle"></i> Allocation configuration metrics error: Value sums must equal exactly 100%
            </div>
        </div>

        <!-- Submission Controls Pipeline -->
        <div class="pt-4 border-t border-slate-800 flex justify-end">
            <button type="submit" id="submitBtn" name="submit_profit_distribution" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-emerald-900/20 transition-all active:scale-95 cursor-pointer text-sm">
                Save Profit Distribution
            </button>
        </div>
    </form>
</div>

<!-- Advanced Multi-Level Filter Matrix -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 mb-6 shadow-sm">
    <div class="flex items-center justify-between border-b border-slate-800/80 pb-3 mb-4">
        <div class="flex items-center gap-2 text-xs font-bold text-slate-400 uppercase tracking-wider">
            <i class="fas fa-filter text-blue-500"></i> Advanced Operational Filtering (Multi-Select)
        </div>
        <!-- Dropdown Wrapper Component Matrix -->

    
<div class="relative inline-block text-left group">
    <button type="button" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-md shadow-blue-900/10 flex items-center gap-2 cursor-pointer">
        <i class="fas fa-download"></i> Download Table <i class="fas fa-chevron-down text-[10px] ml-0.5 transition-transform group-hover:rotate-180"></i>
    </button>
    <div class="absolute right-0 mt-2 w-48 bg-[#161b22] border border-slate-800 rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-150 z-50 overflow-hidden">
        <div class="py-1">
            <button type="button" onclick="exportTableToExcel()" class="w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-emerald-400 transition-colors flex items-center gap-2.5 font-sans font-semibold cursor-pointer">
                <i class="fas fa-file-excel text-emerald-500 w-4"></i> Export to Excel (.XLS)
            </button>
            <button type="button" onclick="exportTableToPDF()" class="w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-rose-400 transition-colors flex items-center gap-2.5 font-sans font-semibold cursor-pointer border-t border-slate-800/50">
                <i class="fas fa-file-pdf text-rose-500 w-4"></i> Export to PDF (.PDF)
            </button>
            <button type="button" onclick="exportTableToImage()" class="w-full text-left px-4 py-2.5 text-xs text-slate-300 hover:bg-slate-800 hover:text-blue-400 transition-colors flex items-center gap-2.5 font-sans font-semibold cursor-pointer border-t border-slate-800/50">
                <i class="fas fa-image text-blue-500 w-4"></i> Export to Image (.PNG)
            </button>
        </div>
    </div>
</div>

    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Target Profiles Filter (Multiple) -->
        <div class="space-y-1">
            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Profiles / Accounts</label>
            <select id="filter_names" multiple class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-slate-300 focus:outline-none focus:border-slate-600 transition-all text-xs cursor-pointer h-24 custom-scrollbar">
                <?php foreach($unique_names as $n): ?>
                    <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="text-[9px] text-slate-600 ml-1 italic">Ctrl/Cmd + Click to select multiple</p>
        </div>

        <!-- Scrip Filter (Multiple) -->
        <div class="space-y-1">
            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Scrips / Companies</label>
            <select id="filter_scrips" multiple class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-slate-300 focus:outline-none focus:border-slate-600 transition-all text-xs cursor-pointer h-24 custom-scrollbar">
                <?php foreach($unique_scrips as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Status Filter (Multiple) -->
        <div class="space-y-1">
            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Settlement States</label>
            <select id="filter_status" multiple class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 text-slate-300 focus:outline-none focus:border-slate-600 transition-all text-xs cursor-pointer h-24 custom-scrollbar">
                <option value="W&D">Settled (W&D)</option>
                <option value="WAITING_WITHDRAWAL">Waiting for Withdrawal</option>
                <option value="PENDING">Pending</option>
            </select>
        </div>

        <!-- Margin Type Filter (Single) -->
        <div class="space-y-1">
            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Net Margin Threshold</label>
            <select id="filter_margin" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-slate-300 focus:outline-none focus:border-slate-600 transition-all text-xs cursor-pointer h-[34px]">
                <option value="ALL">All Outcomes</option>
                <option value="PROFIT">Profitable Ventures (> NRs. 0)</option>
                <option value="LOSS">Loss-making (<= NRs. 0)</option>
            </select>
            
            <div class="mt-3">
                <button onclick="clearFilters()" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-400 px-4 py-2 rounded-xl text-[10px] uppercase font-bold transition-all shadow-sm">
                    Clear Filters
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Interactive Batch Action Command Bar -->
<!-- Simple Helper Note for Create Withdraw Button -->
<div id="withdraw_tip_note" class="mb-4 p-3 bg-amber-500/5 border border-amber-500/20 rounded-xl flex items-start gap-2 text-xs text-slate-400">
    <i class="fas fa-info-circle text-amber-500 mt-0.5"></i>
    <div>
        <span class="font-bold text-slate-200">Tip:</span> 
        To create a withdraw request, select one or more transactions by checking the boxes on the left side of the table below.
    </div>
</div>

<div class="flex justify-between items-center w-full mb-4">
    
    <div id="batch_action_bar" class="hidden animate-fadeIn">
        <button type="button" onclick="launchWithdrawRequestModal()" 
                class="bg-amber-600 hover:bg-amber-500 text-white px-5 py-2.5 rounded-xl text-xs font-bold transition-all shadow-lg shadow-amber-900/20 flex items-center gap-2 cursor-pointer">
            <i class="fas fa-file-invoice-dollar"></i> Create Withdraw Request (<span id="selected_count">0</span>)
        </button>
    </div>

    <div id="withdraw_request_list" class="animate-fadeIn">
        <a href="withdraw-request.php" 
           class="bg-slate-700 hover:bg-slate-600 text-slate-200 px-5 py-2.5 rounded-xl text-xs font-bold transition-all shadow-lg shadow-slate-900/20 flex items-center gap-2 cursor-pointer border border-slate-600">
            <i class="fas fa-list-ul"></i> 
            Withdraw Request List
        </a>
    </div>

</div>
<!-- Frozen Historical Distributions Ledger Matrix Container -->
<div id="snapshot-target" class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm p-1">
    <div class="overflow-x-auto">
 <table class="w-full text-left border-collapse" id="distributionTable">
    <thead>
        <tr class="bg-slate-800/30 border-b border-slate-800">
            <!-- Selector -->
            <th class="px-3 py-3 text-center w-8">
                <input type="checkbox" id="select_all_distributions" class="w-3.5 h-3.5 rounded bg-slate-900 border-slate-700 text-blue-600 focus:ring-blue-500 cursor-pointer">
            </th>
            <!-- Primary Meta -->
            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest w-20">Date</th>
            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Profile / Scrip</th>
            
            <!-- Consolidated Activity Pool Header -->
            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right w-36">Capital (Invest / Return)</th>
            <th class="px-3 py-3 text-[10px] font-bold text-emerald-500 uppercase tracking-widest text-right w-24">Net Profit</th>
            
            <!-- Split Allocations Header (Currency Specified Nationally) -->
            <th class="px-3 py-3 text-[10px] font-bold text-blue-400 uppercase tracking-widest text-right bg-blue-500/[0.02] w-24">Manager Cut</th>
            <th class="px-3 py-3 text-[10px] font-bold text-emerald-400 uppercase tracking-widest text-right bg-emerald-500/[0.02] w-24">Client Cut</th>
            <th class="px-3 py-3 text-[10px] font-bold text-amber-400 uppercase tracking-widest text-right bg-amber-500/[0.02] w-24">Agent Cut</th>
            
            <!-- Controls Status -->
            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center w-20">Status</th>
            <th class="px-3 py-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center w-16">Action</th>
        </tr>
        <!-- Value Currency Anchor Subtitle Indicator Header Row -->
        <tr class="bg-slate-900/40 border-b border-slate-800 text-[9px] text-slate-600 uppercase font-semibold">
            <td colspan="3"></td>
            <td class="px-3 py-1 text-right">Values in NRs.</td>
            <td class="px-3 py-1 text-right">Profit Pool</td>
            <td class="px-3 py-1 text-right bg-blue-500/[0.01]">Manager Pool</td>
            <td class="px-3 py-1 text-right bg-emerald-500/[0.01]">Client Pool</td>
            <td class="px-3 py-1 text-right bg-amber-500/[0.01]">Agent Pool</td>
            <td colspan="2"></td>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-800/60 font-mono text-[11px]">
        <?php 
        $hasDistRows = false;
        foreach ($history_rows as $row): 
            $hasDistRows = true;
            $calculated_profit = $row['net_receivable'] - $row['invest_amt'];
        ?>
            <tr class="hover:bg-slate-800/10 transition-colors target-row" 
                data-name="<?php echo htmlspecialchars($row['display_name']); ?>"
                data-dmat-num="<?php echo htmlspecialchars($row['dmat_num']); ?>"
                data-scrip="<?php echo htmlspecialchars($row['scrip_name']); ?>"
                data-status="<?php echo htmlspecialchars($row['status']); ?>" 
                data-profit="<?php echo $calculated_profit; ?>">
                
                <!-- Checkbox Action Column Selector Mapping Raw Data Bundles -->
                <td class="px-3 py-2.5 text-center">
    <?php 
        $isPending = ($row['status'] === 'PENDING');
    ?>
    
    <div class="inline-block cursor-not-allowed" 
         <?= !$isPending ? 'title="Action only available for PENDING records"' : '' ?>>
        
        <input type="checkbox" 
               class="distribution-selector w-3.5 h-3.5 rounded bg-slate-900 border-slate-700 text-blue-600 focus:ring-blue-500 <?= $isPending ? 'cursor-pointer' : 'opacity-30 cursor-not-allowed' ?>"
               data-id="<?= $row['id'] ?>"
               data-name="<?= htmlspecialchars($row['display_name']) ?>"
               data-dmat-num="<?= $row['dmat_num'] ?>"
               data-scrip="<?= htmlspecialchars($row['scrip_name']) ?>"
               data-calculated-profit="<?= $calculated_profit ?>"
               data-m-profit="<?= $row['manager_profit'] ?>"
               data-c-profit="<?= $row['client_profit'] ?>"
               data-a-profit="<?= $row['agent_profit'] ?>"
               <?= !$isPending ? 'disabled' : '' ?>>
    </div>
</td>

                <!-- Date -->
                <td class="px-3 py-2.5 text-slate-400 whitespace-nowrap"><?php echo htmlspecialchars($row['date']); ?></td>
                
                <!-- Profile & Scrip Identity Stacked -->
                <td class="px-3 py-2.5 whitespace-nowrap">
                    <div class="flex flex-col">
                        <span class="text-blue-400 font-sans font-bold text-xs leading-tight"><?php echo htmlspecialchars($row['display_name']); ?></span>
                        <span class="text-slate-400 font-sans text-[10px] mt-0.5 tracking-wide uppercase font-medium">Scrip: <span class="text-slate-200 font-bold"><?php echo htmlspecialchars($row['scrip_name']); ?></span></span>
                    </div>
                </td>
                
                <!-- Consolidated Investment Base & Net Return Column Matrix -->
                <td class="px-3 py-2.5 text-right whitespace-nowrap">
                    <div class="flex flex-col">
                        <!-- Raw targets preserved inside child components for JavaScript calculations matching -->
                        <span class="text-slate-200 font-bold val-return" data-raw="<?php echo $row['net_receivable']; ?>"><?php echo number_format($row['net_receivable'], 2); ?></span>
                        <span class="text-[9px] text-slate-500 val-invest" data-raw="<?php echo $row['invest_amt']; ?>">Base: <?php echo number_format($row['invest_amt'], 0); ?></span>
                    </div>
                </td>
                
                <!-- Net Profit Portfolio Outcome Status -->
                <td class="px-3 py-2.5 text-right font-bold whitespace-nowrap val-profit <?php echo $calculated_profit >= 0 ? 'text-emerald-400' : 'text-rose-400'; ?>" data-raw="<?php echo $calculated_profit; ?>">
                    <?php echo number_format($calculated_profit, 2); ?>
                </td>
                
                <!-- Isolated Columns Layout Spreads -->
                <!-- Manager Cut -->
                <td class="px-3 py-2.5 text-right text-blue-400 bg-blue-500/[0.01] val-m-profit whitespace-nowrap" data-raw="<?php echo $row['manager_profit']; ?>">
                    <div class="flex flex-col leading-tight">
                        <span class="font-bold"><?php echo number_format($row['manager_profit'], 2); ?></span>
                        <span class="text-[9px] text-slate-600 font-sans">M: <?php echo number_format($row['manager_pct'], 0); ?>%</span>
                    </div>
                </td>
                
                <!-- Client Cut -->
                <td class="px-3 py-2.5 text-right text-emerald-400 bg-emerald-500/[0.01] val-c-profit whitespace-nowrap" data-raw="<?php echo $row['client_profit']; ?>">
                    <div class="flex flex-col leading-tight">
                        <span class="font-bold"><?php echo number_format($row['client_profit'], 2); ?></span>
                        <span class="text-[9px] text-slate-600 font-sans">C: <?php echo number_format($row['client_pct'], 0); ?>%</span>
                    </div>
                </td>
                
                <!-- Agent Cut -->
                <td class="px-3 py-2.5 text-right text-amber-400 bg-amber-500/[0.01] val-a-profit whitespace-nowrap" data-raw="<?php echo $row['agent_profit']; ?>">
                    <div class="flex flex-col leading-tight">
                        <?php if ($row['agent_profit'] > 0): ?>
                            <span class="font-bold"><?php echo number_format($row['agent_profit'], 2); ?></span>
                            <span class="text-[9px] text-slate-600 font-sans">A: <?php echo number_format($row['agent_pct'], 0); ?>%</span>
                        <?php else: ?>
                            <span class="text-slate-700 font-normal text-right pr-1">-</span>
                        <?php endif; ?>
                    </div>
                </td>
                
                <!-- Status Badge -->
                <td class="px-3 py-2.5 text-center font-sans whitespace-nowrap">
                    <?php if ($row['status'] === "W&D"): ?>
                        <span class="px-1.5 py-0.5 rounded-sm text-[8px] font-bold bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 uppercase">Settled</span>
                    <?php elseif ($row['status'] === "WAITING_WITHDRAWAL"): ?>
                        <span class="px-1.5 py-0.5 rounded-sm text-[8px] font-bold bg-blue-500/10 border border-blue-500/20 text-blue-400 uppercase">Waiting</span>
                    <?php else: ?>
                        <span class="px-1.5 py-0.5 rounded-sm text-[8px] font-bold bg-amber-500/10 border border-amber-500/20 text-amber-400 uppercase animate-pulse">Pending</span>
                    <?php endif; ?>
                </td>

                <?php
$isPending = ($row['status'] === 'PENDING'); // Adjust based on your DB value
$isWaiting = ($row['status'] === 'WAITING_WITHDRAWAL');
$isSettled = ($row['status'] === 'W&D');

// Buttons are active if Pending OR Waiting
$canEdit = ($isPending || $isWaiting);
// Delete is ONLY active if Pending
$canDelete = ($isPending);
?>

                <!-- Operational Actions Pipeline Buttons -->
                <td class="px-3 py-2.5 text-center whitespace-nowrap">
                    <div class="flex items-center justify-center gap-1.5">
                    <?php if ($canEdit): ?>    
                    <button type="button" 
                                onclick="openEditDistributionModal(
                                    '<?= $row['id'] ?>', 
                                    '<?= urlencode($row['dmat_num']) ?>', 
                                    '<?= addslashes($row['date']) ?>', 
                                    '<?= addslashes($row['scrip_name']) ?>', 
                                    '<?= $row['invest_amt'] ?>', 
                                    '<?= $row['net_receivable'] ?>', 
                                    '<?= $row['manager_pct'] ?>', 
                                    '<?= $row['client_pct'] ?>', 
                                    '<?= $row['agent_pct'] ?>', 
                                    '<?= $row['manager_profit'] ?>', 
                                    '<?= $row['client_profit'] ?>', 
                                    '<?= $row['agent_profit'] ?>', 
                                    '<?= htmlspecialchars($row['status']) ?>'
                                )"
                                class="inline-flex items-center justify-center w-6 h-6 rounded bg-slate-800 hover:bg-blue-500/10 text-slate-400 hover:text-blue-400 border border-slate-700/60 transition-all cursor-pointer">
                            <i class="fas fa-edit text-[10px]"></i>
                        </button>
                        <?php else: ?>
        <div class="cursor-not-allowed" title="Cannot edit settled transactions">
            <button type="button" disabled 
                    class="inline-flex items-center justify-center w-6 h-6 rounded bg-slate-800/50 text-slate-600 border border-slate-800 transition-all opacity-50">
                <i class="fas fa-edit text-[10px]"></i>
            </button>
        </div>
    <?php endif; ?>
    <?php if ($canDelete): ?>
                        <button type="button" 
                                onclick="confirmDeleteDistribution('<?= $row['id'] ?>', '<?= addslashes($row['scrip_name']) ?>', '<?= addslashes($row['display_name']) ?>')" 
                                class="inline-flex items-center justify-center w-6 h-6 rounded bg-slate-800 hover:bg-rose-500/10 text-slate-400 hover:text-rose-500 border border-slate-700/60 transition-all cursor-pointer">
                            <i class="fas fa-trash text-[10px]"></i>
                        </button>
                        <?php else: ?>
        <div class="cursor-not-allowed" title="Only pending records can be deleted">
            <button type="button" disabled 
                    class="inline-flex items-center justify-center w-6 h-6 rounded bg-slate-800/50 text-slate-600 border border-slate-800 transition-all opacity-50">
                <i class="fas fa-trash text-[10px]"></i>
            </button>
        </div>
    <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; 

        if (!$hasDistRows) {
            echo '<tr id="no-data-fallback"><td colspan="10" class="px-4 py-8 text-center text-xs text-slate-500 italic"><i class="fas fa-folder-open mb-2 text-lg block"></i>No historical distributions logged in the system yet.</td></tr>';
        }
        ?>
    </tbody>
    
    <!-- Reactive Dynamic Summary Footer Row (Updated to match structural indices) -->
    <tfoot>
        <tr class="bg-slate-800/50 font-bold border-t border-slate-600 shadow-inner font-mono text-[11px]">
            <td class="px-3 py-3 font-sans text-slate-400 text-xs" colspan="3">Filtered Aggregations</td>
            <td class="px-3 py-3 text-right text-slate-300 whitespace-nowrap">
                <div class="flex flex-col text-[10px] leading-normal font-normal">
                    <span>Ret: <span id="f-total-return" class="font-bold text-slate-200">0.00</span></span>
                    <span class="text-slate-500 text-[9px]">Inv: <span id="f-total-invest" class="text-slate-400">0.00</span></span>
                </div>
            </td>
            <td id="f-total-profit" class="px-3 py-3 text-right whitespace-nowrap">0.00</td>
            <td id="f-total-m-profit" class="px-3 py-3 text-right text-blue-400 bg-blue-500/[0.02] whitespace-nowrap">0.00</td>
            <td id="f-total-c-profit" class="px-3 py-3 text-right text-emerald-400 bg-emerald-500/[0.02] whitespace-nowrap">0.00</td>
            <td id="f-total-a-profit" class="px-3 py-3 text-right text-amber-400 bg-amber-500/[0.02] whitespace-nowrap">0.00</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
    </div>
</div>

<div id="calcModal" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-[#161b22] border border-slate-700 w-full max-w-sm rounded-2xl p-6 shadow-2xl">
        <h3 class="text-white font-bold text-sm mb-4">Net Receivable Calculator</h3>
        <div class="space-y-3">
            <input type="number" id="calc_kitta" placeholder="Kitta (K2)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-white text-xs"/>
            <input type="number" id="calc_rate" placeholder="Rate (L2)" class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2.5 text-white text-xs"/>
            <div class="flex gap-2">
                <button onclick="document.getElementById('calcModal').style.display = 'none'" class="flex-1 bg-slate-800 hover:bg-slate-700 text-white py-2 rounded-lg text-xs font-bold transition-all">Cancel</button>
                <button onclick="performCalculation()" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-white py-2 rounded-lg text-xs font-bold transition-all">Calculate</button>
            </div>
        </div>
    </div>
</div>
<?php include('footer.php'); ?>

<script>
// --- SNAPSHOT EXPORT LOGIC ---
function takeSnapshot() {
    const target = document.getElementById('snapshot-target');
    
    // Save state layout configurations
    const originalOverflow = target.style.overflow;
    target.style.overflow = 'visible';
    
    html2canvas(target, {
        scale: 4,                        // 1. Force 4x extreme pixel density scaling
        backgroundColor: '#161b22', 
        useCORS: true,                   // 2. Prevent cross-origin asset blurring
        logging: false,
        allowTaint: false,
        imageTimeout: 0,                 // 3. Prevent asset drop-outs during deep render
        
        // 4. Hook straight into the internal context definition to force highest resolution filters
        onclone: function(clonedDoc) {
            // Optional: Modify specific styles on the cloned DOM right before rasterization if needed
        }
    }).then(canvas => {
        // Restore layout overflow definitions smoothly
        target.style.overflow = originalOverflow;
        
        // 5. Force the rendering engine to use maximum smoothing steps
        const ctx = canvas.getContext('2d');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        
        // 6. Automatically download the high-definition asset
        let link = document.createElement('a');
        let timestamp = new Date().toISOString().slice(0,10).replace(/-/g,"");
        link.download = `HD_Profit_Matrix_${timestamp}.png`;
        link.href = canvas.toDataURL("image/png");
        link.click();
    });
}

function clearFilters() {
    $('#filter_names, #filter_scrips, #filter_status').val([]);
    $('#filter_margin').val('ALL');
    $('#filter_names, #filter_scrips, #filter_status, #filter_margin').trigger('change');
}

$(document).ready(function() {

    // --- FORM: DYNAMIC SPLIT PARAMETER INJECTION ON PROFILE SELECTION ---
    $('#user_select').on('change', function() {
        let selectedOption = $(this).find(':selected');
        let splitData = selectedOption.data('split'); // Automatically parses valid inline JSON from attribute
        
        if (splitData) {
            let m = parseFloat(splitData.manager_pct) || 0;
            let c = parseFloat(splitData.client_pct) || 0;
            let a = parseFloat(splitData.agent_pct) || 0;
            
            $("#m_pct_input").val(m.toFixed(1));
            $("#c_pct_input").val(c.toFixed(1));
            $("#a_pct_input").val(a.toFixed(1));
            
            calculateDistributionSplits();
        }
    });


    // --- FORM: CALCULATION ENGINE ---
    function calculateDistributionSplits() {
        let investAmt = parseFloat($("#invest_amt").val()) || 0.00;
        let receivableAmt = parseFloat($("#net_receivable").val()) || 0.00;
        
        let netProfit = receivableAmt - investAmt;
        $("#net_profit_display").text(netProfit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        let m_pct = parseFloat($("#m_pct_input").val()) || 0;
        let c_pct = parseFloat($("#c_pct_input").val()) || 0;
        let a_pct = parseFloat($("#a_pct_input").val()) || 0;

        let sumVerification = m_pct + c_pct + a_pct;
        if (Math.abs(sumVerification - 100) > 0.01 && (investAmt > 0 || receivableAmt > 0)) {
            $("#split_math_warning").removeClass('hidden');
            $("#submitBtn").prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
        } else {
            $("#split_math_warning").addClass('hidden');
            $("#submitBtn").prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
        }

        // 1. Calculate base percentages
let totalPct = m_pct + c_pct + a_pct;
let baseM = (netProfit > 0) ? (netProfit * (m_pct / totalPct)) : 0.00;
let baseC = (netProfit > 0) ? (netProfit * (c_pct / totalPct)) : 0.00;
let baseA = (netProfit > 0) ? (netProfit * (a_pct / totalPct)) : 0.00;

// 2. Round computed_c up to the nearest multiple of 10
let computed_c = Math.ceil(baseC / 10) * 10;

// 3. Calculate the difference introduced by rounding C
let diff = computed_c - baseC;

// 4. Distribute the difference across M and A 
// We subtract the difference proportionally based on their original share
let remaining = netProfit - computed_c;
let combinedMA = baseM + baseA;

let computed_m = (combinedMA > 0) ? (baseM - (diff * (baseM / combinedMA))) : 0.00;
let computed_a = (combinedMA > 0) ? (baseA - (diff * (baseA / combinedMA))) : 0.00;

// Ensure no negative values if rounding diff is extreme
computed_m = Math.max(0, computed_m);
computed_a = Math.max(0, computed_a);

        $("#val_m_profit").text(computed_m.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $("#val_c_profit").text(computed_c.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $("#val_a_profit").text(computed_a.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        $("#input_m_profit").val(computed_m.toFixed(2));
        $("#input_c_profit").val(computed_c.toFixed(2));
        $("#input_a_profit").val(computed_a.toFixed(2));
    }

    $("#invest_amt, #net_receivable, #m_pct_input, #c_pct_input, #a_pct_input").on("input change", calculateDistributionSplits);


    // --- ADVANCED MULTI-LEVEL MATRIX FILTERING ---
    function evaluateMatrixReconciliation() {
        let totalInvest = 0, totalReturn = 0, totalProfit = 0;
        let totalMProfit = 0, totalCProfit = 0, totalAProfit = 0;
        let visibleCount = 0;

        // Retrieve raw array data from multiple select dropdowns
        let selectedNames = $("#filter_names").val() || [];
        let selectedScrips = $("#filter_scrips").val() || [];
        let selectedStatus = $("#filter_status").val() || [];
        let selectedMargin = $("#filter_margin").val(); // single select

        $(".target-row").each(function() {
            let row = $(this);
            
            // Extract attributes to match
            let rowName = row.data('name');
            let rowScrip = row.data('scrip');
            let rowStatus = row.data('status'); 
            let rowProfit = parseFloat(row.data('profit')) || 0;

            // Logic matching evaluations against array constraints
            let matchesName = (selectedNames.length === 0) || selectedNames.includes(rowName);
            let matchesScrip = (selectedScrips.length === 0) || selectedScrips.includes(rowScrip);
            let matchesStatus = (selectedStatus.length === 0) || selectedStatus.includes(rowStatus);
            
            let matchesMargin = (selectedMargin === "ALL") ||
                                (selectedMargin === "PROFIT" && rowProfit > 0) ||
                                (selectedMargin === "LOSS" && rowProfit <= 0);

            // Row toggle resolution state
            if (matchesName && matchesScrip && matchesStatus && matchesMargin) {
                row.show();
                visibleCount++;

                // Safely aggregate summary bounds
                totalInvest += parseFloat(row.find('.val-invest').data('raw')) || 0;
                totalReturn += parseFloat(row.find('.val-return').data('raw')) || 0;
                totalProfit += rowProfit;
                totalMProfit += parseFloat(row.find('.val-m-profit').data('raw')) || 0;
                totalCProfit += parseFloat(row.find('.val-c-profit').data('raw')) || 0;
                totalAProfit += parseFloat(row.find('.val-a-profit').data('raw')) || 0;
            } else {
                row.hide();
            }
        });

        if (visibleCount === 0 && $(".target-row").length > 0) {
            if (!$("#filter-empty-fallback").length) {
                $("#distributionTable tbody").append('<tr id="filter-empty-fallback"><td colspan="10" class="px-4 py-8 text-center text-xs text-slate-600 italic"><i class="fas fa-filter mb-2 text-md block"></i>No records align with current multi-level filtering criteria.</td></tr>');
            }
        } else {
            $("#filter-empty-fallback").remove();
        }

        // Format and print totals securely into the small-text footer string layouts
        function formatVal(value) {
            return 'NRs. ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        $("#f-total-invest").text(formatVal(totalInvest));
        $("#f-total-return").text(formatVal(totalReturn));
        
        let profitElement = $("#f-total-profit");
        profitElement.text(formatVal(totalProfit));
        if (totalProfit >= 0) {
            profitElement.removeClass('text-rose-400').addClass('text-emerald-400');
        } else {
            profitElement.removeClass('text-emerald-400').addClass('text-rose-400');
        }

        $("#f-total-m-profit").text(formatVal(totalMProfit));
        $("#f-total-c-profit").text(formatVal(totalCProfit));
        $("#f-total-a-profit").text(formatVal(totalAProfit));
    }

    $("#filter_names, #filter_scrips, #filter_status, #filter_margin").on("change", evaluateMatrixReconciliation);
    
    // Initial evaluation sweep on DOM ready
    evaluateMatrixReconciliation();
});
// --- DYNAMIC MODAL GENERATION: EDIT DISTRIBUTION ---
function openEditDistributionModal(id, dmat, date, scrip, invest, net, m_pct, c_pct, a_pct, m_prof, c_prof, a_prof, status) {
    // Safely decode DMAT string configuration bounds
    const decodedDmat = decodeURIComponent(dmat);

    let optionsHtml = '';

    if (status === 'PENDING') {
        // Show all 3
        optionsHtml = `
            <option value="PENDING" selected>Pending</option>
            <option disabled value="WAITING_WITHDRAWAL">Waiting W/D</option>
            <option disabled value="W&D">Settled (W&D)</option>
        `;
    } else if (status === 'WAITING_WITHDRAWAL') {
        // Only show W&D
        optionsHtml = `
            <option disabled value="WAITING_WITHDRAWAL" selected>Waiting W/D</option>
            <option disabled value="W&D">Settled (W&D)</option>
        `;
    } else if (status === 'W&D') {
        // Only show W&D and disable the select
        optionsHtml = `<option disabled value="W&D" selected>Settled (W&D)</option>`;

    } 


    
    const modalHtml = `
        <div id="edit-dist-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm overflow-y-auto">
            <div class="bg-[#161b22] border border-blue-500/30 w-full max-w-xl rounded-2xl shadow-2xl p-6 border-t-4 border-t-blue-600 my-8">
                
                <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500">
                            <i class="fas fa-coins text-md"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold tracking-tight text-sm">Modify Share Log Entry</h3>
                            <p class="text-blue-500/70 text-[9px] uppercase tracking-widest font-black">Distribution Identifier: #${id}</p>
                        </div>
                    </div>
                    <button onclick="closeEditDistModal()" class="text-slate-500 hover:text-white transition-colors cursor-pointer text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Feedback Status Notification Logging Sandbox Context -->
                <div id="modal_msg_box" class="hidden mb-4 p-3 rounded-xl text-xs font-semibold flex items-center gap-2"></div>

                <form id="editDistributionForm" class="space-y-4 text-left">
                    <input type="hidden" name="id" value="${id}">
                    <input type="hidden" name="dmat_num" value="${decodedDmat}">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Distribution Date</label>
                            <input type="text" name="date" value="${date}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-slate-200 font-mono text-xs focus:ring-1 focus:ring-blue-500 outline-none"/>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Scrip Name</label>
                            <input type="text" name="scrip_name" value="${scrip}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-slate-200 uppercase text-xs focus:ring-1 focus:ring-blue-500 outline-none font-bold"/>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Settlement Status</label>
                            <select name="status" id="edit_status_select" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-slate-200 text-xs focus:ring-1 focus:ring-blue-500 outline-none cursor-pointer h-[34px]">
                            ${optionsHtml}    
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Invested Amount (NRs.)</label>
                            <input type="number" step="0.01" min="0" id="edit_invest_amt" name="invest_amt" value="${invest}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-slate-200 font-mono text-xs focus:ring-1 focus:ring-blue-500 outline-none"/>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] text-slate-500 font-bold uppercase ml-1">Net Receivable (NRs.)</label>
                            <input type="number" step="0.01" min="0" id="edit_net_receivable" name="net_receivable" value="${net}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-slate-200 font-mono text-xs focus:ring-1 focus:ring-blue-500 outline-none"/>
                        </div>
                    </div>

                    <!-- Sandbox Allocation Grid internal to Modal Component -->
                    <div class="p-4 bg-slate-900/50 border border-slate-800 rounded-xl space-y-3">
                        <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex justify-between border-b border-slate-800 pb-1.5">
                            <span>Computed Splits Calculations</span>
                            <span>Profit: NRs. <span id="edit_net_profit_display" class="font-bold text-slate-200">0.00</span></span>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <div class="flex justify-between text-[9px] uppercase font-bold text-blue-400 px-1 mb-1">
                                    <span>Manager %</span>
                                    <input type="number" step="0.1" id="edit_m_pct" name="manager_pct" value="${m_pct}" class="w-8 bg-transparent text-right outline-none font-bold text-blue-400 border-b border-transparent focus:border-blue-500">%
                                </div>
                                <div class="bg-slate-900 border border-slate-800 text-[11px] font-mono font-bold text-slate-300 p-2 rounded-lg text-center h-[34px] flex items-center justify-center">
                                    NRs.&nbsp;<span id="edit_val_m_profit">0.00</span>
                                </div>
                                <input type="hidden" id="edit_input_m_profit" name="manager_profit" value="${m_prof}">
                            </div>

                            <div>
                                <div class="flex justify-between text-[9px] uppercase font-bold text-emerald-400 px-1 mb-1">
                                    <span>Client %</span>
                                    <input type="number" step="0.1" id="edit_c_pct" name="client_pct" value="${c_pct}" class="w-8 bg-transparent text-right outline-none font-bold text-emerald-400 border-b border-transparent focus:border-emerald-500">%
                                </div>
                                <div class="bg-slate-900 border border-slate-800 text-[11px] font-mono font-bold text-slate-300 p-2 rounded-lg text-center h-[34px] flex items-center justify-center">
                                    NRs.&nbsp;<span id="edit_val_c_profit">0.00</span>
                                </div>
                                <input type="hidden" id="edit_input_c_profit" name="client_profit" value="${c_prof}">
                            </div>

                            <div>
                                <div class="flex justify-between text-[9px] uppercase font-bold text-amber-400 px-1 mb-1">
                                    <span>Agent %</span>
                                    <input type="number" step="0.1" id="edit_a_pct" name="agent_pct" value="${a_pct}" class="w-8 bg-transparent text-right outline-none font-bold text-amber-400 border-b border-transparent focus:border-amber-500">%
                                </div>
                                <div class="bg-slate-900 border border-slate-800 text-[11px] font-mono font-bold text-slate-300 p-2 rounded-lg text-center h-[34px] flex items-center justify-center">
                                    NRs.&nbsp;<span id="edit_val_a_profit">0.00</span>
                                </div>
                                <input type="hidden" id="edit_input_a_profit" name="agent_profit" value="${a_prof}">
                            </div>
                        </div>
                        <div id="edit_split_math_warning" class="text-[9px] font-bold text-rose-400 uppercase tracking-wider hidden pt-1">
                            <i class="fas fa-exclamation-circle"></i> Matrix mathematical compilation failure: Percentage configurations must sum up to exactly 100%
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2" id="edit_modal_actions_bar">
                        <button type="button" onclick="closeEditDistModal()" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all cursor-pointer">
                            Discard
                        </button>
                        <button type="submit" id="editSubmitBtn" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-600/20 cursor-pointer">
                            Update Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
    
    // Bind dynamic calculation handlers local to this modal instance structure
    $("#edit_invest_amt, #edit_net_receivable, #edit_m_pct, #edit_c_pct, #edit_a_pct").on("input change", calculateModalDistributionSplits);
    calculateModalDistributionSplits(); // Run standard initialization sweep
}

function closeEditDistModal() {
    $('#edit-dist-modal').remove();
}

// --- CALCULATION HOOK INTERNAL TO EDIT VIEW CONTINUUM ---
function calculateModalDistributionSplits() {
    let investAmt = parseFloat($("#edit_invest_amt").val()) || 0.00;
    let receivableAmt = parseFloat($("#edit_net_receivable").val()) || 0.00;
    
    let netProfit = receivableAmt - investAmt;
    $("#edit_net_profit_display").text(netProfit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

    let m_pct = parseFloat($("#edit_m_pct").val()) || 0;
    let c_pct = parseFloat($("#edit_c_pct").val()) || 0;
    let a_pct = parseFloat($("#edit_a_pct").val()) || 0;

    let sumVerification = m_pct + c_pct + a_pct;
    if (Math.abs(sumVerification - 100) > 0.01 && (investAmt > 0 || receivableAmt > 0)) {
        $("#edit_split_math_warning").removeClass('hidden');
        $("#editSubmitBtn").prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
    } else {
        $("#edit_split_math_warning").addClass('hidden');
        $("#editSubmitBtn").prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
    }

    // 1. Calculate base percentages
let totalPct = m_pct + c_pct + a_pct;
let baseM = (netProfit > 0) ? (netProfit * (m_pct / totalPct)) : 0.00;
let baseC = (netProfit > 0) ? (netProfit * (c_pct / totalPct)) : 0.00;
let baseA = (netProfit > 0) ? (netProfit * (a_pct / totalPct)) : 0.00;

// 2. Round computed_c up to the nearest multiple of 10
let computed_c = Math.ceil(baseC / 10) * 10;

// 3. Calculate the difference introduced by rounding C
let diff = computed_c - baseC;

// 4. Distribute the difference across M and A 
// We subtract the difference proportionally based on their original share
let remaining = netProfit - computed_c;
let combinedMA = baseM + baseA;

let computed_m = (combinedMA > 0) ? (baseM - (diff * (baseM / combinedMA))) : 0.00;
let computed_a = (combinedMA > 0) ? (baseA - (diff * (baseA / combinedMA))) : 0.00;

// Ensure no negative values if rounding diff is extreme
computed_m = Math.max(0, computed_m);
computed_a = Math.max(0, computed_a);

    $("#edit_val_m_profit").text(computed_m.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    $("#edit_val_c_profit").text(computed_c.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    $("#edit_val_a_profit").text(computed_a.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

    $("#edit_input_m_profit").val(computed_m.toFixed(2));
    $("#edit_input_c_profit").val(computed_c.toFixed(2));
    $("#edit_input_a_profit").val(computed_a.toFixed(2));
}

// --- SUBMIT COMPLETED RECORD RECONCILIATION TO API ---
$(document).on('submit', '#editDistributionForm', function(e) {
    e.preventDefault();
    const form = $(this);
    const msgBox = $('#modal_msg_box');
    const submitBtn = $('#editSubmitBtn');

    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Writing...');

    $.ajax({
        type: "GET", // Aligned with system configuration profile logic
        url: "json-api.php",
        data: form.serialize() + "&action=edit_profit_distribution",
        dataType: "json",
        success: function(response) {
            if (response.status === 'success') {
                msgBox.removeClass('hidden bg-rose-600/10 text-rose-400 border border-rose-500/20')
                      .addClass('bg-emerald-600/10 text-emerald-400 border border-emerald-500/20')
                      .html(`<i class="fas fa-check-circle"></i> ${response.message}`);
                
                $('#edit_modal_actions_bar').html(`
                    <button type="button" onclick="window.location.reload();" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-emerald-600/20 cursor-pointer">
                        Reload Main Workspace
                    </button>
                `);
            } else {
                submitBtn.prop('disabled', false).text('Update Record');
                msgBox.removeClass('hidden bg-emerald-600/10 text-emerald-400 border border-emerald-500/20')
                      .addClass('bg-rose-600/10 text-rose-400 border border-rose-500/20')
                      .html(`<i class="fas fa-exclamation-triangle"></i> Execution Rejected: ${response.message}`);
            }
        },
        error: function() {
            submitBtn.prop('disabled', false).text('Update Record');
            msgBox.removeClass('hidden bg-emerald-600/10 text-emerald-400 border border-emerald-500/20')
                  .addClass('bg-rose-600/10 text-rose-400 border border-rose-500/20')
                  .html('<i class="fas fa-ban"></i> Network Protocol Interrupted.');
        }
    });
});

// --- DYNAMIC MODAL GENERATION: DELETION CONFIRMATION ---
function confirmDeleteDistribution(id, scrip, name) {
    const modalHtml = `
        <div id="delete-dist-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#161b22] border border-rose-500/30 w-full max-w-sm rounded-2xl shadow-2xl p-6 border-t-4 border-t-rose-600 text-left">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-500 text-xl">
                        <i class="fas fa-trash-alt"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold tracking-tight text-sm">Purge Distribution Entry</h3>
                        <p class="text-rose-500/70 text-[9px] uppercase tracking-widest font-black">Destructive Action Pipeline</p>
                    </div>
                </div>
                
                <div class="text-slate-300 text-xs leading-relaxed mb-6">
                    You are about to completely remove the historical allocation data mapping for scrip <b class="text-white">${scrip}</b> assigned to profile <b>${name}</b>. This process changes database logs permanently and cannot be reverted.
                </div>

                <div id="delete_modal_msg_box" class="hidden mb-4 p-3 rounded-xl text-[11px] font-semibold flex items-center gap-2"></div>

                <div class="flex gap-3" id="delete_modal_actions_bar">
                    <button type="button" onclick="closeDeleteDistModal()" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all cursor-pointer">
                        Cancel
                    </button>
                    <button type="button" onclick="executeDistributionDeletion('${id}')" id="deleteSubmitBtn" class="flex-1 py-2.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-rose-600/20 cursor-pointer">
                        Confirm Purge
                    </button>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
}

function closeDeleteDistModal() {
    $('#delete-dist-modal').remove();
}

function executeDistributionDeletion(id) {
    const msgBox = $('#delete_modal_msg_box');
    const submitBtn = $('#deleteSubmitBtn');

    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Dropping...');

    $.ajax({
        type: "GET",
        url: "json-api.php",
        data: { action: "delete_profit_distribution", id: id },
        dataType: "json",
        success: function(response) {
            if (response.status === 'success') {
                msgBox.removeClass('hidden bg-rose-600/10 text-rose-400 border border-rose-500/20')
                      .addClass('bg-emerald-600/10 text-emerald-400 border border-emerald-500/20')
                      .html(`<i class="fas fa-check-circle"></i> ${response.message}`);
                
                $('#delete_modal_actions_bar').html(`
                    <button type="button" onclick="window.location.reload();" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-emerald-600/20 cursor-pointer">
                        Update Workspace Table
                    </button>
                `);
            } else if (response.status === 'warn') {
                msgBox.removeClass('hidden bg-rose-600/10 text-rose-400 border border-rose-500/20')
                      .addClass('bg-amber-600/10 text-amber-400 border border-amber-500/20')
                      .html(`<i class="fas fa-exclamation-triangle"></i> ${response.message}`);

                      $('#delete_modal_actions_bar').html(`
                    <button type="button" onclick="window.location.reload();" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-emerald-600/20 cursor-pointer">
                        Update Workspace Table
                    </button>
                `);

            } else {
                submitBtn.prop('disabled', false).text('Confirm Purge');
                msgBox.removeClass('hidden bg-emerald-600/10 text-emerald-400 border border-emerald-500/20')
                      .addClass('bg-rose-600/10 text-rose-400 border border-rose-500/20')
                      .html(`<i class="fas fa-times-circle"></i> Error: ${response.message}`);
            }
        },
        error: function() {
            submitBtn.prop('disabled', false).text('Confirm Purge');
            msgBox.removeClass('hidden bg-emerald-600/10 text-emerald-400 border border-emerald-500/20')
                  .addClass('bg-rose-600/10 text-rose-400 border border-rose-500/20')
                  .html('<i class="fas fa-ban"></i> Server Endpoint Communication Error.');
        }
    });
}

$(document).ready(function() {
    // --- BATCH SELECTION MECHANISM ---
    function synchronizeBatchSelectionState() {
        let selectedCheckboxes = $('.distribution-selector:checked');
        let count = selectedCheckboxes.length;
        
        if (count > 0) {
            $('#selected_count').text(count);
            $('#batch_action_bar').removeClass('hidden');
        } else {
            $('#batch_action_bar').addClass('hidden');
        }
    }

    // Toggle all items simultaneously (Only enabled ones)
$('#select_all_distributions').on('change', function() {
    // We filter by :enabled so that disabled (non-PENDING) checkboxes remain unchecked
    $('.distribution-selector:visible:enabled').prop('checked', this.checked);
    synchronizeBatchSelectionState();
});

// Sync individual selection state
$(document).on('change', '.distribution-selector', function() {
    synchronizeBatchSelectionState();
});

// Clear selections smoothly when dynamic filtering changes
$("#filter_names, #filter_scrips, #filter_status, #filter_margin").on("change", function() {
    $('.distribution-selector').prop('checked', false);
    $('#select_all_distributions').prop('checked', false);
    synchronizeBatchSelectionState();
});
});

// --- RENDER MACRO WITHDRAW SETTLEMENT CONTAINER MODAL ---
/// --- RENDER LINE-EDITABLE WITHDRAW MODAL WITH BALANCING LOGIC ---
let currentGroupedData = null;
async function launchWithdrawRequestModal() {
    let groupedData = {}; // This will hold the organized JSON structure
    let uniqueDmats = [];
    currentGroupedData = groupedData;
    let globalTotalNet = 0, globalTotalM = 0, globalTotalC = 0, globalTotalA = 0; // Initialize global totals

    $('.distribution-selector:checked').each(function() {
        let el = $(this);
        let dmat = el.data('dmat-num');
        
        if (!groupedData[dmat]) {
            groupedData[dmat] = {
                name: el.data('name'),
                dmat: dmat,
                scrips: [],
                totalNet: 0,
                totalM: 0,
                totalC: 0,
                totalA: 0,
            ledgerBalance: 0, // This will be calculated as we process line items
                lineItems: [] // Store individual records here
            };
        }
        
        let rowNet = parseFloat(el.data('calculated-profit')) || 0;
        let rowM = parseFloat(el.data('m-profit')) || 0;
        let rowC = parseFloat(el.data('c-profit')) || 0;
        let rowA = parseFloat(el.data('a-profit')) || 0;
        
                
        globalTotalNet += rowNet;
        globalTotalM += rowM;
        globalTotalC += rowC;
        globalTotalA += rowA;

        groupedData[dmat].scrips.push(el.data('scrip'));
        groupedData[dmat].totalNet += rowNet;
        groupedData[dmat].totalM += rowM;
        groupedData[dmat].totalC += rowC;
        groupedData[dmat].totalA += rowA;
        groupedData[dmat].lineItems.push({
            id: el.data('id'),
            scrip: el.data('scrip'),
            name: el.data('name'),
            dmat: dmat,
            net: rowNet,
            m: rowM,
            c: rowC,
            a: rowA
        });
    });

uniqueDmats = Object.keys(groupedData);
try {
        const response = await $.ajax({
            url: 'json-api.php',
            type: 'POST',
            data: {
                action: 'get_ledger_balances',
                dmats: uniqueDmats
            },
            dataType: 'json'
        });

        // Map the returned balances to your groupedData
        response.forEach(item => {
            if (groupedData[item.dmat]) {
                groupedData[item.dmat].ledgerBalance = parseFloat(item.balance) || 0;
            }
        });
} catch (err) {
        console.error("Failed to fetch ledger balances", err);
    }


    let tableRowsHtml = "";
    Object.keys(groupedData).forEach(dmat => {
        let d = groupedData[dmat];
        // 1. Calculate the rounding adjustment
let roundedDownNet = Math.floor(d.totalNet / 100) * 100;
let roundingDiff = d.totalNet - roundedDownNet;

// 2. Overwrite the original values in the object
d.totalNet = roundedDownNet;
d.totalM = Math.max(0, d.totalM - roundingDiff);

// Now, any downstream code using d.totalNet or d.totalM 
// will automatically use the updated, rounded figures.
        tableRowsHtml += `
            <tr class="modal-calc-row border-b border-slate-800 text-[11px]" 
           
                 data-name="${d.lineItems[0].name}"
                 data-dmat-num="${d.lineItems[0].dmat}"
                 data-scrip="${d.scrips.join(', ')}"
                 
                 data-c-profit="${d.totalC.toFixed(2)}" 
                 data-a-profit="${d.totalA.toFixed(2)}"
            data-dmat="${dmat}">
                <td class="px-4 py-2.5 text-slate-500 font-mono">MULTI</td>
                <td class="px-4 py-2.5 font-sans font-bold text-slate-300">${d.name}</td>
                <td class="px-4 py-2.5 text-slate-500 font-mono">${d.dmat}</td>
                <td class="px-4 py-2.5 text-blue-400 font-semibold">${d.scrips.join(', ')}</td>
                <td class="px-4 py-2 whitespace-nowrap flex items-center gap-2">
    <span class="group relative cursor-help" title="Ledger Balance: NRs. ${d.ledgerBalance.toLocaleString()}">
        <svg class="w-4 h-4 text-slate-500 hover:text-blue-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
    </span>
                <input type="number" step="0.01" value="${d.totalNet.toFixed(2)}" 
           class="w-28 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-right text-amber-400 font-mono text-xs md-row-net-input"/>
    
    
</td>
                <td class="px-4 py-2.5 text-right font-mono text-blue-400 md-row-m-prof" data-raw="${d.totalM.toFixed(2)}">NRs. ${d.totalM.toFixed(2)}</td>
                <td class="px-4 py-2.5 text-right font-mono text-emerald-400 md-row-c-prof" data-raw="${d.totalC.toFixed(2)}">NRs. ${d.totalC.toFixed(2)}</td>
                <td class="px-4 py-2.5 text-right font-mono text-amber-400 md-row-a-prof" data-raw="${d.totalA.toFixed(2)}">NRs. ${d.totalA.toFixed(2)}</td>
            </tr>
        `;
    });
    
        const modalHtml = `
        <div id="withdraw-req-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm overflow-y-auto">
            <div class="bg-[#161b22] border border-amber-500/30 w-full max-w-5xl rounded-2xl shadow-2xl p-6 border-t-4 border-t-amber-500 my-8">
                
                <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                            <i class="fas fa-file-invoice-dollar text-md"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold tracking-tight text-sm">Withdraw Request Statement Sandbox</h3>
                            <p class="text-amber-500/70 text-[9px] uppercase tracking-widest font-black">Line-Item Editable Settlement Ledger</p>
                        </div>
                    </div>
                    <button onclick="closeWithdrawModal()" class="text-slate-500 hover:text-white transition-colors cursor-pointer text-sm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Snapshot Matrix Preview Table Core Content -->
                <div class="border border-slate-800 rounded-xl overflow-hidden max-h-96 overflow-y-auto custom-scrollbar mb-6">
                    <table class="w-full text-left border-collapse" id="modalAggregateTable">
                        <thead>
                            <tr class="bg-slate-900 text-slate-500 text-[10px] font-bold uppercase tracking-wider border-b border-slate-800">
                                <th class="px-4 py-3 w-16">ID</th>
                                <th class="px-4 py-3">Account Name</th>
                                <th class="px-4 py-3">DMAT Number</th>
                                <th class="px-4 py-3">Scrip</th>
                                <th class="px-4 py-3 text-right w-36">Withdraw Amount (Editable)</th>
                                <th class="px-4 py-3 text-right">Manager Cut</th>
                                <th class="px-4 py-3 text-right">Client Cut</th>
                                <th class="px-4 py-3 text-right">Agent Cut</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            ${tableRowsHtml}
                        </tbody>
                        <tfoot>
                            <tr class="bg-slate-900 font-bold border-t border-slate-700 text-[11px]">
                                <td class="px-4 py-3 font-sans text-slate-400" colspan="4">Aggregated Balance Outturns</td>
                                <td id="mdl-foot-total-net" class="px-4 py-3 text-right text-amber-400 font-mono">NRs. ${globalTotalNet.toFixed(2)}</td>
                                <td id="mdl-foot-total-m" class="px-4 py-3 text-right text-blue-400 font-mono">NRs. ${globalTotalM.toFixed(2)}</td>
                                <td id="mdl-foot-total-c" class="px-4 py-3 text-right text-emerald-400 font-mono">NRs. ${globalTotalC.toFixed(2)}</td>
                                <td id="mdl-foot-total-a" class="px-4 py-3 text-right text-amber-400 font-mono">NRs. ${globalTotalA.toFixed(2)}</td>
                            </tr>
                            <tr class="bg-slate-950 font-bold text-[10px] text-slate-400 border-t border-slate-800">
                                <td class="px-4 py-2.5 font-sans uppercase tracking-widest text-right" colspan="4">Total Portfolio Cash Pool Distribution:</td>
                                <td id="mdl-foot-total-pool" class="px-4 py-2.5 text-left text-emerald-400 font-mono pl-6" colspan="4">NRs. ${(globalTotalM + globalTotalC + globalTotalA).toFixed(2)}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="flex gap-3 justify-end">
                    <button type="button" onclick="closeWithdrawModal()" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all cursor-pointer">
                        Close
                    </button>
                    
                    <button type="button" onclick="saveWithdrawRequest()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-600/20 flex items-center gap-2 cursor-pointer">
                        <i class="fas fa-download"></i> Save & Download
                    </button>
                </div>
            </div>
        </div>
    `;
    $('body').append(modalHtml);

    // Bind real-time execution loops to update computations dynamically
    $(document).on('input change', '.md-row-net-input', function() {
        recalculateModalLiveFooters();
    });
}
// function launchWithdrawRequestModal() {
//     let tableRowsHtml = "";
//     let globalTotalNet = 0;
//     let globalTotalM = 0;
//     let globalTotalC = 0;
//     let globalTotalA = 0;
    
//     $('.distribution-selector:checked').each(function() {
//         let el = $(this);
//         let id = el.data('id');
//         let name = el.data('name');
//         let dmat = el.data('dmat-num');
//         let scrip = el.data('scrip');
//         let rowNet = parseFloat(el.data('calculated-profit')) || 0;
//         let rowM = parseFloat(el.data('m-profit')) || 0;
//         let rowC = parseFloat(el.data('c-profit')) || 0;
//         let rowA = parseFloat(el.data('a-profit')) || 0;

//         globalTotalNet += rowNet;
//         globalTotalM += rowM;
//         globalTotalC += rowC;
//         globalTotalA += rowA;

//         tableRowsHtml += `
//             <tr class="modal-calc-row border-b border-slate-800 text-[11px] hover:bg-slate-900/40" 
//                 data-id="${id}" 
//                 data-name="${escapeHtml(name)}"
//                 data-dmat-num="${escapeHtml(dmat)}"
//                 data-scrip="${escapeHtml(scrip)}"
//                 data-initial-m-profit="${rowM}"
//                 data-c-profit="${rowC}" 
//                 data-a-profit="${rowA}">
//                 <td class="px-4 py-2.5 text-slate-500 font-mono">#${id}</td>
//                 <td class="px-4 py-2.5 font-sans font-bold text-slate-300 md-row-name">${name}</td>
//                 <td class="px-4 py-2.5 text-slate-500 font-mono md-row-dmat">${dmat}</td>
//                 <td class="px-4 py-2.5 font-sans text-blue-400 font-semibold md-row-scrip">${scrip}</td>
//                 <td class="px-4 py-2">
//                     <input type="number" step="0.01" min="0" value="${rowNet.toFixed(2)}" 
//                            class="w-28 bg-slate-900 border border-slate-700 rounded-lg px-2 py-1 text-right text-amber-400 font-mono text-xs focus:ring-1 focus:ring-amber-500 outline-none font-bold md-row-net-input"/>
//                 </td>
//                 <!-- Added a tracking data-raw attribute directly onto the Manager cell for live state monitoring -->
//                 <td class="px-4 py-2.5 text-right font-mono text-blue-400 md-row-m-prof" data-raw="${rowM}">NRs. ${rowM.toFixed(2)}</td>
//                 <td class="px-4 py-2.5 text-right font-mono text-emerald-400 md-row-c-prof">NRs. ${rowC.toFixed(2)}</td>
//                 <td class="px-4 py-2.5 text-right font-mono text-amber-400 md-row-a-prof">NRs. ${rowA.toFixed(2)}</td>
//             </tr>
//         `;
//     });

//     const modalHtml = `
//         <div id="withdraw-req-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm overflow-y-auto">
//             <div class="bg-[#161b22] border border-amber-500/30 w-full max-w-5xl rounded-2xl shadow-2xl p-6 border-t-4 border-t-amber-500 my-8">
                
//                 <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-3">
//                     <div class="flex items-center gap-3">
//                         <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
//                             <i class="fas fa-file-invoice-dollar text-md"></i>
//                         </div>
//                         <div>
//                             <h3 class="text-white font-bold tracking-tight text-sm">Withdraw Request Statement Sandbox</h3>
//                             <p class="text-amber-500/70 text-[9px] uppercase tracking-widest font-black">Line-Item Editable Settlement Ledger</p>
//                         </div>
//                     </div>
//                     <button onclick="closeWithdrawModal()" class="text-slate-500 hover:text-white transition-colors cursor-pointer text-sm">
//                         <i class="fas fa-times"></i>
//                     </button>
//                 </div>

//                 <!-- Snapshot Matrix Preview Table Core Content -->
//                 <div class="border border-slate-800 rounded-xl overflow-hidden max-h-96 overflow-y-auto custom-scrollbar mb-6">
//                     <table class="w-full text-left border-collapse" id="modalAggregateTable">
//                         <thead>
//                             <tr class="bg-slate-900 text-slate-500 text-[10px] font-bold uppercase tracking-wider border-b border-slate-800">
//                                 <th class="px-4 py-3 w-16">ID</th>
//                                 <th class="px-4 py-3">Account Name</th>
//                                 <th class="px-4 py-3">DMAT Number</th>
//                                 <th class="px-4 py-3">Scrip</th>
//                                 <th class="px-4 py-3 text-right w-36">Withdraw Amount (Editable)</th>
//                                 <th class="px-4 py-3 text-right">Manager Cut</th>
//                                 <th class="px-4 py-3 text-right">Client Cut</th>
//                                 <th class="px-4 py-3 text-right">Agent Cut</th>
//                             </tr>
//                         </thead>
//                         <tbody class="divide-y divide-slate-800/60">
//                             ${tableRowsHtml}
//                         </tbody>
//                         <tfoot>
//                             <tr class="bg-slate-900 font-bold border-t border-slate-700 text-[11px]">
//                                 <td class="px-4 py-3 font-sans text-slate-400" colspan="4">Aggregated Balance Outturns</td>
//                                 <td id="mdl-foot-total-net" class="px-4 py-3 text-right text-amber-400 font-mono">NRs. ${globalTotalNet.toFixed(2)}</td>
//                                 <td id="mdl-foot-total-m" class="px-4 py-3 text-right text-blue-400 font-mono">NRs. ${globalTotalM.toFixed(2)}</td>
//                                 <td id="mdl-foot-total-c" class="px-4 py-3 text-right text-emerald-400 font-mono">NRs. ${globalTotalC.toFixed(2)}</td>
//                                 <td id="mdl-foot-total-a" class="px-4 py-3 text-right text-amber-400 font-mono">NRs. ${globalTotalA.toFixed(2)}</td>
//                             </tr>
//                             <tr class="bg-slate-950 font-bold text-[10px] text-slate-400 border-t border-slate-800">
//                                 <td class="px-4 py-2.5 font-sans uppercase tracking-widest text-right" colspan="4">Total Portfolio Cash Pool Distribution:</td>
//                                 <td id="mdl-foot-total-pool" class="px-4 py-2.5 text-left text-emerald-400 font-mono pl-6" colspan="4">NRs. ${(globalTotalM + globalTotalC + globalTotalA).toFixed(2)}</td>
//                             </tr>
//                         </tfoot>
//                     </table>
//                 </div>

//                 <div class="flex gap-3 justify-end">
//                     <button type="button" onclick="closeWithdrawModal()" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all cursor-pointer">
//                         Close
//                     </button>
                    
//                     <button type="button" onclick="saveWithdrawRequest()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-600/20 flex items-center gap-2 cursor-pointer">
//                         <i class="fas fa-download"></i> Save & Download
//                     </button>
//                 </div>
//             </div>
//         </div>
//     `;
//     $('body').append(modalHtml);

//     // Bind real-time execution loops to update computations dynamically
//     $(document).on('input change', '.md-row-net-input', function() {
//         recalculateModalLiveFooters();
//     });
// }

// --- HELPER STRIP PATTERNS FOR STRING ESCAPING ---
function escapeHtml(string) {
    return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// --- AUTO-BALANCING ALGORITHM LOGIC HOOK (FIXED) ---
function recalculateModalLiveFooters() {
    let aggregatedNet = 0;
    let aggregatedM = 0;
    let aggregatedC = 0;
    let aggregatedA = 0;

    $(".modal-calc-row").each(function() {
        let row = $(this);
        
        // Fetch values safely as floating numbers
        let liveWithdrawValue = parseFloat(row.find('.md-row-net-input').val()) || 0.00;
        let cProf = parseFloat(row.data('c-profit')) || 0.00;
        let aProf = parseFloat(row.data('a-profit')) || 0.00;

        // Auto-balancing calculation: Manager absorbs the delta perfectly
        let dynamicMProfit = liveWithdrawValue - (cProf + aProf);

        // Explicitly update both the text display AND the internal data property of the row
        row.find('.md-row-m-prof')
           .attr('data-raw', dynamicMProfit.toFixed(2)) // Force attribute string synchronization
           .data('raw', dynamicMProfit)                  // Update jQuery internal object state
           .text("NRs. " + dynamicMProfit.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

        // Accumulate running totals for the table footer
        aggregatedNet += liveWithdrawValue;
        aggregatedM += dynamicMProfit;
        aggregatedC += cProf;
        aggregatedA += aProf;
    });

    // Write aggregated totals directly back to the modal's footer tags
    $("#mdl-foot-total-net").text("NRs. " + aggregatedNet.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    $("#mdl-foot-total-m").text("NRs. " + aggregatedM.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    $("#mdl-foot-total-c").text("NRs. " + aggregatedC.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    $("#mdl-foot-total-a").text("NRs. " + aggregatedA.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
    
    let totalPoolOutturn = aggregatedM + aggregatedC + aggregatedA;
    $("#mdl-foot-total-pool").text("NRs. " + totalPoolOutturn.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
}

// --- DRIVER: DOWNLOAD CLEAN EXCEL SUMMARY SHEET (FIXED RECONCILIATION) ---
function triggerCleanExcelPdfDownload() {
  
    let dateStamp = new Date().toLocaleDateString('en-GB');
    let matrixRowsHtml = "";
    
    let sumNet = 0;
    let sumM = 0;
    let sumC = 0;
    let sumA = 0;

    $(".modal-calc-row").each(function() {
        let row = $(this);
        let name = row.data('name');
        
        // Scrip extraction from current input fields
        let liveNetValue = parseFloat(row.find('.md-row-net-input').val()) || 0.00;
        let cProf = parseFloat(row.data('c-profit')) || 0.00;
        let aProf = parseFloat(row.data('a-profit')) || 0.00;
        
        // Fix: Read directly from the updated attribute string fallback to prevent reading a stale 0 default state
        let mProf = parseFloat(row.find('.md-row-m-prof').attr('data-raw')) || (liveNetValue - (cProf + aProf));

        sumNet += liveNetValue;
        sumM += mProf;
        sumC += cProf;
        sumA += aProf;

        // Formats data perfectly to matching structural layouts requested
        matrixRowsHtml += `
            <tr>
                <td style="padding: 8px; font-weight: bold; border: 1px solid #111;">${name}</td>
                <td style="padding: 8px; text-align: right; border: 1px solid #111; font-family: monospace;">${liveNetValue.toFixed(2)}</td>
                <td style="padding: 8px; text-align: right; border: 1px solid #111; font-family: monospace;">${cProf.toFixed(2)}</td>
                <td style="padding: 8px; text-align: right; border: 1px solid #111; font-family: monospace;">${aProf.toFixed(2)}</td>
                <td style="padding: 8px; text-align: right; border: 1px solid #111; font-family: monospace;">${mProf.toFixed(2)}</td>
            </tr>
        `;
    });

    // Build plain, presentation-ready accounting matrix frame
    let documentHtml = `
        <div style="font-family: Arial, sans-serif; padding: 10px; color: #000;">
            <h2 style="margin-bottom: 5px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Withdrawal Request Summary Statement</h2>
            <p style="font-size: 11px; margin-top: 0; color: #333;"><b>Export Date:</b> ${dateStamp}</p>
            
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse: collapse; width: 100%; font-size: 11px; text-align: left; margin-top: 15px;">
                <thead>
                    <tr style="background-color: #f2f2f2; font-weight: bold;">
                        <th style="padding: 8px; border: 1px solid #111;">Account Holder Name</th>
                        <th style="padding: 8px; border: 1px solid #111; text-align: right;">Withdraw Amount</th>
                        <th style="padding: 8px; border: 1px solid #111; text-align: right;">Client Cut</th>
                        <th style="padding: 8px; border: 1px solid #111; text-align: right;">Agent Cut</th>
                        <th style="padding: 8px; border: 1px solid #111; text-align: right;">Manager Cut</th>
                    </tr>
                </thead>
                <tbody>
                    ${matrixRowsHtml}
                    <!-- Consolidated Matrix Summary Totals Row -->
                    <tr style="background-color: #f9f9f9; font-weight: bold; border-top: 2px solid #111;">
                        <td style="padding: 8px; border: 1px solid #111; text-transform: uppercase;">TOTALS</td>
                        <td style="padding: 8px; border: 1px solid #111; text-align: right; font-family: monospace;">${sumNet.toFixed(2)}</td>
                        <td style="padding: 8px; border: 1px solid #111; text-align: right; font-family: monospace;">${sumC.toFixed(2)}</td>
                        <td style="padding: 8px; border: 1px solid #111; text-align: right; font-family: monospace;">${sumA.toFixed(2)}</td>
                        <td style="padding: 8px; border: 1px solid #111; text-align: right; font-family: monospace;">${sumM.toFixed(2)}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    `;

    let printWindow = window.open('', '_blank', 'width=850,height=700');
    printWindow.document.write('<html><head><title>Withdrawal Statement Summary</title>');
    printWindow.document.write('<style>body{margin:0;padding:5px;} @page{size: auto; margin: 15mm;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(documentHtml);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.focus();
    
    printWindow.print();
    printWindow.close();
}

// --- HIGH-QUALITY CLEAN STATEMENT EXPORTER ---
function triggerCleanExcelXlsDownload() {
    let dateStamp = new Date().toLocaleDateString('en-GB');
    let matrixRowsHtml = "";
    
    let sumNet = 0;
    let sumM = 0;
    let sumC = 0;
    let sumA = 0;

    // 1. Scrape live data values from our custom modal rows
    $(".modal-calc-row").each(function() {
        let row = $(this);
        let name = row.data('name');
        let liveNetValue = parseFloat(row.find('.md-row-net-input').val()) || 0.00;
        let cProf = parseFloat(row.data('c-profit')) || 0.00;
        let aProf = parseFloat(row.data('a-profit')) || 0.00;
        let mProf = parseFloat(row.find('.md-row-m-prof').attr('data-raw')) || (liveNetValue - (cProf + aProf));

        sumNet += liveNetValue;
        sumM += mProf;
        sumC += cProf;
        sumA += aProf;

        matrixRowsHtml += `
            <tr>
                <td style="padding: 10px; font-weight: bold; border: 1px solid #111; color: #000; background-color: #ffffff;">${name}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #111; font-family: monospace; color: #000; background-color: #ffffff;">${liveNetValue.toFixed(2)}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #111; font-family: monospace; color: #000; background-color: #ffffff;">${cProf.toFixed(2)}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #111; font-family: monospace; color: #000; background-color: #ffffff;">${aProf.toFixed(2)}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #111; font-family: monospace; color: #000; background-color: #ffffff;">${mProf.toFixed(2)}</td>
            </tr>
        `;
    });

    // 2. Build standard spreadsheet structural template code
    let excelTemplate = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="content-type" content="text/plain; charset=UTF-8">
            <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Withdrawal Statement</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { border-collapse: collapse; width: 100%; }
                th { background-color: #f2f2f2; font-weight: bold; border: 1px solid #111; padding: 10px; }
                td { border: 1px solid #111; padding: 10px; }
                .totals-row { background-color: #f9f9f9; font-weight: bold; }
            </style>
        </head>
        <body>
            <h2 style="font-size: 16px; text-transform: uppercase; margin-bottom: 5px;">Withdrawal Request Summary Statement</h2>
            <p style="font-size: 11px; color: #333; margin-top: 0; margin-bottom: 20px;"><b>Export Date:</b> ${dateStamp}</p>
            
            <table>
                <thead>
                    <tr>
                        <th>Account Holder Name</th>
                        <th style="text-align: right;">Withdraw Amount</th>
                        <th style="text-align: right;">Client Cut</th>
                        <th style="text-align: right;">Agent Cut</th>
                        <th style="text-align: right;">Manager Cut</th>
                    </tr>
                </thead>
                <tbody>
                    ${matrixRowsHtml}
                    <tr class="totals-row">
                        <td style="border: 1px solid #111; text-transform: uppercase;">TOTALS</td>
                        <td style="text-align: right; border: 1px solid #111; font-family: monospace;">${sumNet.toFixed(2)}</td>
                        <td style="text-align: right; border: 1px solid #111; font-family: monospace;">${sumC.toFixed(2)}</td>
                        <td style="text-align: right; border: 1px solid #111; font-family: monospace;">${sumA.toFixed(2)}</td>
                        <td style="text-align: right; border: 1px solid #111; font-family: monospace;">${sumM.toFixed(2)}</td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
    `;

    // 3. Create blob payload with matching application headers
    let dataBlob = new Blob([excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    let blobUrl = URL.createObjectURL(dataBlob);
    
    // 4. Trigger seamless download link allocation tracking pipeline
    let downloadAnchor = document.createElement('a');
    let timestamp = new Date().toISOString().slice(0,10).replace(/-/g,"");
    
    downloadAnchor.href = blobUrl;
    downloadAnchor.download = `Withdrawal_Request_Sheet_${timestamp}.xls`;
    
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    
    // Clean up temporary object storage allocations smoothly
    document.body.removeChild(downloadAnchor);
    URL.revokeObjectURL(blobUrl);
}


async function saveWithdrawRequest() { // Removed [] from parameter name

if (!currentGroupedData) return;
groupedData = currentGroupedData;

    // 1. Scrape the updated withdraw amounts from the UI
    $(".modal-calc-row").each(function() {
        let row = $(this);
        let dmat = row.data('dmat'); // Ensure this matches your HTML data-dmat
        let withdrawAmount = parseFloat(row.find('.md-row-net-input').val()) || 0;
        
        // Update the groupedData object with the user-edited amount
        if (groupedData[dmat]) {
            groupedData[dmat].withdrawAmount = withdrawAmount;
            groupedData[dmat].updatedManagerCut = groupedData[dmat].withdrawAmount - (groupedData[dmat].totalC + groupedData[dmat].totalA); // Recalculate manager cut based on new withdraw amount
            groupedData[dmat].date = getNepaliDateString(new Date());
            groupedData[dmat].ledgerEntryDone = false;
        }
    });

    let requestData = Object.values(groupedData);
    console.log("Prepared request data for submission:", requestData);
    try {
        // Set dataType to 'json' so jQuery parses the response automatically
        let response = await $.ajax({
            url: 'json-api.php',
            type: 'POST',
            dataType: 'json', 
            data: {
                action: 'save_withdraw_request',
                payload: JSON.stringify(requestData)
            }
        });

        if (response.status === 'success') {
            console.log("Save successful:", response);
            triggerCleanExcelImageDownload(requestData);
            closeWithdrawModal();
            location.reload(); // Refresh to reflect changes
        } else {
            alert("Error: " + (response.message || "Unknown error"));
        }
    } catch (err) {
        alert("Server error. Please check your network.");
        console.error(err);
    }
}

function closeWithdrawModal() {
    $('#withdraw-req-modal').remove();
}
// Global Shared Helper: Outputs structural file timestamp hooks
function getExportTimestamp() {
    return new Date().toISOString().slice(0,10).replace(/-/g,"");
}

// ==========================================
// CENTRAL CORE WORKHORSE: CLONE DOM & EXTRACT STANDALONE LAYOUT
// ==========================================
function getSimplifiedTableHtml() {
    let dateStamp = new Date().toLocaleDateString('en-GB');
    
    // Clone the active live table element box
    let originalElement = document.getElementById('snapshot-target');
    let clonedElement = originalElement.cloneNode(true);
    
    let table = clonedElement.querySelector('table');
    if (!table) return originalElement.innerHTML;
    
    // Enforce basic, clean spreadsheet borders and explicit colors
    table.removeAttribute('id');
    table.setAttribute('border', '1');
    table.setAttribute('cellpadding', '6');
    table.setAttribute('style', 'border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; text-align: left; background-color: #ffffff; color: #000000; border: 1px solid #111111;');
    
    let tableHeader = table.querySelector('thead');
    if (tableHeader) tableHeader.setAttribute('style', 'background-color: #f2f2f2; font-weight: bold;');

    let tableFooter = table.querySelector('tfoot');
    if (tableFooter) tableFooter.setAttribute('style', 'background-color: #f9f9f9; font-weight: bold; border-top: 2px solid #111111;');

    // Clean up cells and slice columns safely
    table.querySelectorAll('tr').forEach(row => {
        let cells = row.cells;
        if (cells.length > 0) {
            row.deleteCell(0); // Drop original Checkboxes column
            row.deleteCell(cells.length - 1); // Drop original Actions control column
        }
        
        row.querySelectorAll('th, td').forEach(cell => {
            cell.setAttribute('style', 'padding: 8px 6px; border: 1px solid #111111; color: #000000; background-color: #ffffff;');
            
            // Re-index all nested styling text layers back to solid black ink printing parameters
            cell.querySelectorAll('span, div').forEach(el => {
                el.setAttribute('style', 'color: #000000; background: transparent; font-weight: inherit;');
                el.className = ''; 
            });
        });
    });

    return `
        <div style="background-color: #ffffff; padding: 20px; font-family: Arial, sans-serif; color: #000000; width: 980px; box-sizing: border-box;">
            <h2 style="margin: 0 0 5px 0; font-size: 15px; text-transform: uppercase; letter-spacing: 0.5px; color: #000000;">Profit Distribution Audit Statement</h2>
            <p style="font-size: 11px; margin: 0 0 15px 0; color: #333333;"><b>Statement Generation Date:</b> ${dateStamp} | Values represented in NRs.</p>
            ${table.outerHTML}
        </div>
    `;
}

// ==========================================
// EXPORT TARGET 1: DATA SPREADSHEET BUILDER (.XLS)
// ==========================================
function exportTableToExcel() {
    const simplifiedHtml = getSimplifiedTableHtml();
    
    let excelTemplate = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta http-equiv="content-type" content="text/plain; charset=UTF-8"></head>
        <body>${simplifiedHtml}</body>
        </html>
    `;

    let dataBlob = new Blob([excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    let blobUrl = URL.createObjectURL(dataBlob);
    
    let link = document.createElement('a');
    link.href = blobUrl;
    link.download = `Profit_Distribution_Sheet_${getExportTimestamp()}.xls`;
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(blobUrl);
}

// ==========================================
// EXPORT TARGET 2: SHARP PNG GENERATOR VIA BACKSTAGE IFRAME
// ==========================================
function exportTableToImage() {
    let layoutHtmlString = getSimplifiedTableHtml();

    // Create a temporary, visible iframe but size it down to avoid visual layout jumps
    let iframe = document.createElement('iframe');
    iframe.setAttribute('style', 'position: fixed; bottom: 0; right: 0; width: 1020px; height: 800px; border: none; visibility: hidden; z-index: -1;');
    document.body.appendChild(iframe);

    let doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`<html><head><style>body{margin:0;background:#fff;}</style></head><body>${layoutHtmlString}</body></html>`);
    doc.close();

    // Wait briefly for the internal document thread to fully paint the layout structures
    setTimeout(function() {
        let targetNode = doc.body.firstElementChild;
        
        html2canvas(targetNode, {
            scale: 3, // Upscale 3x for crisp rendering
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            width: 1000,
            height: targetNode.offsetHeight
        }).then(canvas => {
            let link = document.createElement('a');
            link.download = `Profit_Ledger_Snapshot_${getExportTimestamp()}.png`;
            link.href = canvas.toDataURL("image/png");
            link.click();
            
            // Safely remove frame from layout trees
            iframe.remove();
        });
    }, 100);
}

// ==========================================
// EXPORT TARGET 3: HORIZONTAL PAGINATED PDF GENERATOR
// ==========================================
function exportTableToPDF() {
    const { jsPDF } = window.jspdf;
    let layoutHtmlString = getSimplifiedTableHtml();

    let iframe = document.createElement('iframe');
    iframe.setAttribute('style', 'position: fixed; bottom: 0; right: 0; width: 1020px; height: 800px; border: none; visibility: hidden; z-index: -1;');
    document.body.appendChild(iframe);

    let doc = iframe.contentWindow.document;
    doc.open();
    doc.write(`<html><head><style>body{margin:0;background:#fff;}</style></head><body>${layoutHtmlString}</body></html>`);
    doc.close();

    setTimeout(function() {
        let targetNode = doc.body.firstElementChild;
        
        html2canvas(targetNode, {
            scale: 2.5,
            backgroundColor: '#ffffff',
            useCORS: true,
            logging: false,
            width: 1000,
            height: targetNode.offsetHeight
        }).then(canvas => {
            iframe.remove();

            const pdf = new jsPDF('l', 'mm', 'a4'); 
            const imgWidth = 297; 
            const pageHeight = 210; 
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            
            const imgData = canvas.toDataURL('image/png');
            let position = 0;
            
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
            heightLeft -= pageHeight;
            
            while (heightLeft >= 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight, undefined, 'FAST');
                heightLeft -= pageHeight;
            }
            
            pdf.save(`Profit_Ledger_Audit_Report_${getExportTimestamp()}.pdf`);
        });
    }, 100);
}
function openCalcModal() {
    document.getElementById('calcModal').style.display = 'flex';
}

function performCalculation() {
    const k = parseFloat(document.getElementById('calc_kitta').value) || 0;
    const l = parseFloat(document.getElementById('calc_rate').value) || 0;
    
    // Formula: (K*L) - Broker(0.4%) - Sebj(0.015%) - FloorFee(25) - CGT(7.5% on gain over 100)
    const gross = k * l;
    const brokerComm = gross * 0.004;
    const sebjFee = gross * 0.00015;
    const floorFee = 25;
    
    const afterFees = gross - brokerComm - sebjFee - floorFee;
    const costBase = k * 100;
    const gain = afterFees - costBase;
    
    const cgt = gain > 0 ? (gain * 0.075) : 0;
    const net = afterFees - cgt;
    
    // 1. Update the value
    const netInput = document.getElementById('net_receivable');
    netInput.value = net.toFixed(2);
    
    // 2. TRIGGER THE EVENT MANUALLY
    // This tells your existing keyup/input listeners that a change occurred
    const event = new Event('input', { bubbles: true });
    netInput.dispatchEvent(event);
    
    // Close modal
    document.getElementById('calcModal').style.display = 'none';
}
</script>