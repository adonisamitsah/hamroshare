<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

if (!isset($_GET['dmat']) || empty($_GET['dmat'])) {
    die("Error: DMAT identifier missing from protocol pipeline.");
}

$target_dmat = $_GET['dmat'];

// 1. Fetch Profile Metadata & JSON Split Parameter Settings
$user_stmt = $db->prepare("SELECT name, username, dmat_num, profit_dist_split_para FROM users WHERE dmat_num = :dmat LIMIT 1;");
$user_stmt->bindValue(':dmat', $target_dmat, SQLITE3_TEXT);
$user_result = $user_stmt->execute();
$user_profile = $user_result->fetchArray(SQLITE3_ASSOC);

if (!$user_profile) {
    die("Error: Profile associated with DMAT target not found.");
}

$display_name = $user_profile['name'] ?? $user_profile['username'];

// Parse JSON Split Configuration
$split_json = json_decode($user_profile['profit_dist_split_para'] ?? '', true);
$m_pct = isset($split_json['manager_pct']) ? floatval($split_json['manager_pct']) : 80.00;
$c_pct = isset($split_json['client_pct']) ? floatval($split_json['client_pct']) : 20.00;
$a_pct = isset($split_json['agent_pct']) ? floatval($split_json['agent_pct']) : 0.00;

// 2. Pre-compute running balances and totals
$totals_stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(deposit_amt), 0.00) as total_deposits,
        COALESCE(SUM(withdraw_amt), 0.00) as total_withdrawals,
        (SELECT balance FROM ledgers WHERE dmat_num = :dmat ORDER BY date DESC, id DESC LIMIT 1) as latest_balance
    FROM ledgers 
    WHERE dmat_num = :dmat;
");
$totals_stmt->bindValue(':dmat', $target_dmat, SQLITE3_TEXT);
$totals_res = $totals_stmt->execute()->fetchArray(SQLITE3_ASSOC);

$cum_deposits = $totals_res['total_deposits'] ?? 0.00;
$cum_withdrawals = $totals_res['total_withdrawals'] ?? 0.00;
$running_balance = $totals_res['latest_balance'] ?? 0.00;

// 3. Handle Transaction Submission Form
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_ledger_entry'])) {
    $tx_date = trim($_POST['date']);
    $tx_particular = trim($_POST['particular']);
    $tx_flow = $_POST['flow_type'];
    $tx_amount = floatval($_POST['amount']);
    $tx_balance = floatval($_POST['balance']);

    $deposit_amt = ($tx_flow === "DEPOSIT") ? $tx_amount : 0.00;
    $withdraw_amt = ($tx_flow === "WITHDRAW") ? $tx_amount : 0.00;

    $insert_stmt = $db->prepare("
        INSERT INTO ledgers (dmat_num, date, particular, deposit_amt, withdraw_amt, balance, manager_pct, client_pct, agent_pct) 
        VALUES (:dmat, :date, :particular, :deposit, :withdraw, :balance, :mpct, :cpct, :apct);
    ");
    
    $insert_stmt->bindValue(':dmat', $target_dmat, SQLITE3_TEXT);
    $insert_stmt->bindValue(':date', $tx_date, SQLITE3_TEXT);
    $insert_stmt->bindValue(':particular', $tx_particular, SQLITE3_TEXT);
    $insert_stmt->bindValue(':deposit', $deposit_amt, SQLITE3_FLOAT);
    $insert_stmt->bindValue(':withdraw', $withdraw_amt, SQLITE3_FLOAT);
    $insert_stmt->bindValue(':balance', $tx_balance, SQLITE3_FLOAT);
    $insert_stmt->bindValue(':mpct', $m_pct, SQLITE3_FLOAT);
    $insert_stmt->bindValue(':cpct', $c_pct, SQLITE3_FLOAT);
    $insert_stmt->bindValue(':apct', $a_pct, SQLITE3_FLOAT);

    if ($insert_stmt->execute()) {
        header("Location: ledger_view.php?dmat=" . urlencode($target_dmat));
        exit();
    } else {
        echo "<script>alert('Error: Critical write operation pipeline breakdown.');</script>";
    }
}

// 4. Fetch complete history matrix context
$ledger_stmt = $db->prepare("SELECT * FROM ledgers WHERE dmat_num = :dmat ORDER BY date ASC, id ASC;");
$ledger_stmt->bindValue(':dmat', $target_dmat, SQLITE3_TEXT);
$ledger_results = $ledger_stmt->execute();

echo sudo_get_header("ledger");
?>

<!-- Header Breadcrumb Section -->
<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 uppercase tracking-widest mb-1 font-bold">
            <a href="ledger.php" class="hover:text-blue-500 transition-colors">Ledgers Matrix</a>
            <i class="fas fa-chevron-right text-[9px]"></i>
            <span class="text-slate-400">Profile Audit</span>
        </div>
        <h1 class="text-2xl font-bold text-white tracking-tight"><?php echo htmlspecialchars($display_name); ?></h1>
        <p class="text-slate-400 text-sm mt-0.5">DMAT Baseline Anchor: <span class="text-blue-400 font-mono"><?php echo htmlspecialchars($target_dmat); ?></span></p>
    </div>
    <div>
        <a href="ledger.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all border border-slate-700">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>
</div>

<!-- Refined Visual Hierarchy Stat Panels -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Active Safe Balance - Dominated Layout High Importance -->
    <div class="bg-[#161b22] border-2 border-blue-500/30 rounded-2xl p-6 flex items-center gap-5 shadow-lg shadow-blue-900/10 md:col-span-1">
        <div class="w-12 h-12 bg-blue-500 text-white rounded-xl flex items-center justify-center text-xl shadow-md shadow-blue-500/20">
            <i class="fas fa-vault"></i>
        </div>
        <div>
            <p class="text-blue-400 text-[10px] uppercase font-bold tracking-widest">Active Available Balance</p>
            <p class="text-2xl font-black text-white font-mono mt-1">NRs. <?php echo number_format($running_balance, 2); ?></p>
        </div>
    </div>

    <!-- Cumulative Inflows - Less Obvious Subtle Style -->
    <div class="bg-[#161b22]/40 border border-slate-800/80 rounded-2xl p-6 flex items-center gap-5 opacity-70 hover:opacity-100 transition-opacity">
        <div class="w-10 h-10 bg-slate-800 text-emerald-500 rounded-xl flex items-center justify-center text-md border border-slate-700/50">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div>
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Inward Deposits</p>
            <p class="text-md font-bold text-slate-300 font-mono mt-0.5">NRs. <?php echo number_format($cum_deposits, 2); ?></p>
        </div>
    </div>

    <!-- Cumulative Outflows - Less Obvious Subtle Style -->
    <div class="bg-[#161b22]/40 border border-slate-800/80 rounded-2xl p-6 flex items-center gap-5 opacity-70 hover:opacity-100 transition-opacity">
        <div class="w-10 h-10 bg-slate-800 text-rose-500 rounded-xl flex items-center justify-center text-md border border-slate-700/50">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div>
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Outward Withdrawals</p>
            <p class="text-md font-bold text-slate-300 font-mono mt-0.5">NRs. <?php echo number_format($cum_withdrawals, 2); ?></p>
        </div>
    </div>
</div>

<!-- Dynamic Parameters Metric Bar Configuration -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-4 mb-8 flex flex-wrap items-center justify-between gap-4 shadow-sm">
    <div class="flex items-center gap-4 flex-wrap">
        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider pl-2 flex items-center gap-2">
            <i class="fas fa-sliders-h text-blue-500"></i> Active Allocation Parameters:
        </div>
        <div class="flex items-center gap-2 font-mono text-xs">
            <span class="px-2.5 py-1 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 font-bold">
                Manager: <span id="lbl_manager_pct"><?php echo number_format($m_pct, 1); ?></span>%
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-bold">
                Client: <span id="lbl_client_pct"><?php echo number_format($c_pct, 1); ?></span>%
            </span>
            <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-400 font-bold">
                Agent: <span id="lbl_agent_pct"><?php echo number_format($a_pct, 1); ?></span>%
            </span>
        </div>
    </div>
    <button onclick="openSplitParameterModal(<?php echo $m_pct . ',' . $c_pct . ',' . $a_pct; ?>)"
            class="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-blue-400 border border-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer">
        <i class="fas fa-edit"></i> Edit Parameters
    </button>
</div>

<!-- Add Transaction Entry Card Layout Component -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 mb-8 shadow-sm">
    <h3 class="text-xs font-bold text-blue-500 uppercase tracking-[0.2em] mb-6">Log New Ledger Transaction</h3>
    <form id="ledgerEntryForm" method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?dmat=' . urlencode($target_dmat); ?>" class="space-y-6">
        <input type="hidden" id="current_running_balance" value="<?php echo $running_balance; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Transaction Date</label>
                <input id="nepali-date-pick" type="text" placeholder="e.g. 2082.11.08" name="date" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all font-mono placeholder:text-slate-600"/>
            </div>
            <div class="space-y-1 lg:col-span-2">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Particular Description</label>
                <input type="text" placeholder="e.g. Alloted / Sold / Deposit / Withdraw" name="particular" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all placeholder:text-slate-600"/>
            </div>
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Flow Type</label>
                <select id="flow_type" name="flow_type" required
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all cursor-pointer text-sm">
                    <option value="DEPOSIT" selected>Credit (Deposit Inflow)</option>
                    <option value="WITHDRAW">Debit (Withdrawal Outflow)</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Transaction Amount (NRs.)</label>
                <input type="number" step="0.01" min="0.01" placeholder="0.00" id="tx_amount" name="amount" required
                       class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all font-mono placeholder:text-slate-600"/>
            </div>
            <div class="space-y-1 lg:col-span-2">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Computed Post-Balance Output</label>
                <div class="w-full bg-slate-900/50 border border-slate-800 rounded-xl px-4 py-2.5 text-slate-400 font-mono text-sm flex items-center h-[46px]">
                    NRs.&nbsp;<span id="calculated_balance_preview" class="font-bold text-slate-300"><?php echo number_format($running_balance, 2); ?></span>
                </div>
                <input type="hidden" id="balance_submission_input" name="balance" value="<?php echo $running_balance; ?>">
            </div>
        </div>
        <div class="pt-4 border-t border-slate-800 flex justify-end">
            <button type="submit" name="submit_ledger_entry" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-900/20 transition-all active:scale-95 cursor-pointer text-sm">
                Commit Transaction
            </button>
        </div>
    </form>
</div>

<!-- Ledger Transaction Table Content Matrix -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table id="ledgerTable" class="w-full text-left border-collapse">
    <thead>
        <tr class="bg-slate-800/30 border-b border-slate-800">
            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Transaction Date</th>
            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Particular Description</th>
            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Credit (Deposit)</th>
            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Debit (Withdrawal)</th>
            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-right">Calculated Balance</th>
            <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center w-24">Actions</th>
        </tr>
    </thead>
<tbody class="divide-y divide-slate-800 font-mono text-xs">
        <?php while ($row = $ledger_results->fetchArray(SQLITE3_ASSOC)): ?>
            <tr class="hover:bg-slate-800/10 transition-colors">
                <td class="px-6 py-3.5 text-slate-400"><?php echo htmlspecialchars($row['date']); ?></td>
                <td class="px-6 py-3.5 text-slate-200 font-sans font-medium"><?php echo htmlspecialchars($row['particular']); ?></td>
                <td class="px-6 py-3.5 text-right text-emerald-500 font-bold">
                    <?php echo $row['deposit_amt'] > 0 ? 'NRs. ' . number_format($row['deposit_amt'], 2) : '<span class="text-slate-700 font-normal">-</span>'; ?>
                </td>
                <td class="px-6 py-3.5 text-right text-rose-500 font-bold">
                    <?php echo $row['withdraw_amt'] > 0 ? 'NRs. ' . number_format($row['withdraw_amt'], 2) : '<span class="text-slate-700 font-normal">-</span>'; ?>
                </td>
                <td class="px-6 py-3.5 text-right text-slate-100 font-bold bg-slate-900/20">
                    NRs. <?php echo number_format($row['balance'], 2); ?>
                </td>
                <!-- Action Controls Column -->
                <td class="px-6 py-3.5 text-center font-sans">
                    <div class="flex items-center justify-center gap-2">
                        <button type="button" 
                                onclick="openLedgerModal('edit', { id: '<?= $row['id'] ?>', date: '<?= addslashes($row['date']) ?>', particular: '<?= addslashes($row['particular']) ?>', deposit: '<?= $row['deposit_amt'] ?>', withdraw: '<?= $row['withdraw_amt'] ?>' })"
                                class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-blue-500/10 text-slate-400 hover:text-blue-400 border border-slate-700/50 flex items-center justify-center transition-all cursor-pointer" 
                                title="Edit Transaction">
                            <i class="fas fa-edit text-xs"></i>
                        </button>
                        <button type="button" 
                                onclick="openLedgerModal('delete', { id: '<?= $row['id'] ?>', particular: '<?= addslashes($row['particular']) ?>' })"
                                class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-rose-500/10 text-slate-400 hover:text-rose-500 border border-slate-700/50 flex items-center justify-center transition-all cursor-pointer" 
                                title="Delete Record">
                            <i class="fas fa-trash text-xs"></i>
                        </button>
                    </div>
                </td>
            </tr>
        <?php endwhile; ?> <!-- CHANGED FROM endforeach TO endwhile -->
        
        <tr class="bg-slate-800/40 font-bold border-t-2 border-slate-700">
            <td class="px-6 py-4 font-sans text-slate-400" colspan="2">Account Summary Totals</td>
            <td id="summary-deposits" class="px-6 py-4 text-right text-emerald-400">NRs. <?php echo number_format($cum_deposits, 2); ?></td>
            <td id="summary-withdrawals" class="px-6 py-4 text-right text-rose-400">NRs. <?php echo number_format($cum_withdrawals, 2); ?></td>
            <td id="summary-balance" class="px-6 py-4 text-right text-white bg-slate-900/40">NRs. <?php echo number_format($running_balance, 2); ?></td>
            <td></td> <!-- Balance alignment spacer column -->
        </tr>
    </tbody>
</table>
    </div>
</div>
<!-- Unified Ledger Action Modal Frame Overlay -->
<div id="ledgerActionModal" class="fixed inset-0 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center z-50 hidden opacity-0 transition-all duration-200">
    <div class="bg-[#161b22] border border-slate-800 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden transform scale-95 transition-transform duration-200" id="modalContainer">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-800 flex items-center justify-between">
            <h3 id="modalTitle" class="text-sm font-bold text-white uppercase tracking-wider">Transaction Management</h3>
            <button type="button" onclick="closeLedgerModal()" class="text-slate-500 hover:text-slate-300 transition-colors cursor-pointer">
                <i class="fas fa-times text-sm"></i>
            </button>
        </div>
        
        <!-- Form Context Container -->
        <form id="ledgerActionForm" onsubmit="handleLedgerFormSubmit(event)" class="p-6 space-y-4 m-0">
            <input type="hidden" id="action_id" name="id">
            <input type="hidden" id="action_type" name="action">

            <!-- DYNAMIC ALERT CONTAINER (ADD THIS LINE) -->
            <div id="modal-alert-container" class="hidden"></div>

            <!-- Body Frame Target Nodes -->
            <div id="modalBodyWrapper" class="space-y-4">
                <!-- Content will be dynamically inserted here via JS -->
            </div>

            <!-- Footer Operations Panel -->
            <div class="pt-4 border-t border-slate-800 flex items-center justify-end gap-3">
                <button type="button" onclick="closeLedgerModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700/60 text-slate-300 text-xs font-bold uppercase tracking-wider rounded-xl transition-all cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="modalSubmitBtn" class="px-5 py-2.5 text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all flex items-center gap-2 shadow-lg cursor-pointer">
                    Confirm Action
                </button>
            </div>
        </form>
    </div>
</div>
<?php include('footer.php'); ?>

<script>
$(document).ready(function() {
    function computeLiveBalance() {
        let baseline = parseFloat($("#current_running_balance").val()) || 0.00;
        let changeAmount = parseFloat($("#tx_amount").val()) || 0.00;
        let actionType = $("#flow_type").val();
        
        let definitiveOutput = (actionType === "DEPOSIT") ? (baseline + changeAmount) : (baseline - changeAmount);
        
        $("#calculated_balance_preview").text(definitiveOutput.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        $("#balance_submission_input").val(definitiveOutput.toFixed(2));
    }

    $("#tx_amount").on("input change", computeLiveBalance);
    $("#flow_type").on("change", computeLiveBalance);
});

// Modal Logic for Split Parameters
function openSplitParameterModal(m, c, a) {
    const modalHtml = `
        <div id="split-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#161b22] border border-blue-500/30 w-full max-w-sm rounded-2xl shadow-2xl p-6 border-t-4 border-t-blue-600">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-500">
                        <i class="fas fa-sliders-h text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold tracking-tight">Edit Distribution Splits</h3>
                        <p class="text-blue-500/70 text-[10px] uppercase tracking-widest font-black">JSON Parameter Sync</p>
                    </div>
                </div>
                
                <form id="updateSplitsForm" class="space-y-4">
                    <input type="hidden" name="dmat" value="<?php echo htmlspecialchars($target_dmat); ?>">
                    
                    <div class="space-y-1">
                        <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Manager Cut (%)</label>
                        <input type="number" step="0.1" name="manager_pct" value="${m}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm focus:ring-2 focus:ring-blue-600/50 outline-none"/>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Client Cut (%)</label>
                        <input type="number" step="0.1" name="client_pct" value="${c}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm focus:ring-2 focus:ring-blue-600/50 outline-none"/>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Agent Cut (%)</label>
                        <input type="number" step="0.1" name="agent_pct" value="${a}" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2 text-slate-200 font-mono text-sm focus:ring-2 focus:ring-blue-600/50 outline-none"/>
                    </div>

                    <div id="modal_error_log" class="text-xs text-rose-400 font-semibold hidden pt-2"></div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="closeSplitModal()" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all">
                            Cancel
                        </button>
                        <button type="submit" class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-blue-600/20">
                            Save Config
                        </button>
                    </div>
                </form>
            </div>
        </div>
    `;
    $('body').append(modalHtml);
}

function closeSplitModal() {
    $('#split-modal').remove();
}

$(document).on('submit', '#updateSplitsForm', function(e) {
    e.preventDefault();
    const form = $(this);
    const errLog = $('#modal_error_log');
    
    // Quick validate total sum = 100%
    const m = parseFloat(form.find('input[name="manager_pct"]').val()) || 0;
    const c = parseFloat(form.find('input[name="client_pct"]').val()) || 0;
    const a = parseFloat(form.find('input[name="agent_pct"]').val()) || 0;
    
    if ((m + c + a) !== 100) {
        errLog.text("Validation Error: Total allocations must sum up to exactly 100% (Current total: " + (m+c+a) + "%)").removeClass('hidden');
        return;
    }

    $.ajax({
        type: "GET",
        url: "json-api.php",
        data: form.serialize() + "&action=update_split_parameters",
        dataType: "json",
        success: function(response) {
            if (response.status === 'success') {
                // Update live HTML values instantly
                $('#lbl_manager_pct').text(m.toFixed(1));
                $('#lbl_client_pct').text(c.toFixed(1));
                $('#lbl_agent_pct').text(a.toFixed(1));
                
                // Dynamically update the open modal invocation handler values
                $('.px-4.py-1\.5').attr('onclick', `openSplitParameterModal(${m}, ${c}, ${a})`);
                closeSplitModal();
            } else {
                errLog.text("API Error: " + response.message).removeClass('hidden');
            }
        },
        error: function() {
            errLog.text("Network Protocol Error: Could not reach json-api.php").removeClass('hidden');
        }
    });
});
$(document).ready(function() {
    // 1. Helper function to scrape and clean numeric values out of currency strings
    function parseCurrencyValue(elementId) {
        let rawText = $(`#${elementId}`).text() || "0";
        // Remove "NRs.", commas, and whitespace to isolate a clean float string
        let cleanText = rawText.replace(/NRs\./g, '').replace(/,/g, '').trim();
        return parseFloat(cleanText) || 0.00;
    }

    // 2. Extract values from DOM
    let totalDeposits = parseCurrencyValue('summary-deposits');
    let totalWithdrawals = parseCurrencyValue('summary-withdrawals');
    let reportedBalance = parseCurrencyValue('summary-balance');
                    console.log("Extracted Totals - Deposits: " + totalDeposits + ", Withdrawals: " + totalWithdrawals + ", Reported Balance: " + reportedBalance);
    // 3. Mathematical reconciliation audit check
    let mathematicalBalance = totalDeposits - totalWithdrawals;
    console.log("Computed Mathematical Balance: " + mathematicalBalance);
    
    // Using a tiny epsilon check (0.01) to account for floating-point arithmetic precision errors
let warningItems = [];

// 1. Audit Check: Ledger Reconciliation Discrepancy
if (Math.abs(mathematicalBalance - reportedBalance) > 0.01) {
    let discrepancyAmt = Math.abs(mathematicalBalance - reportedBalance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    warningItems.push(`
        <div>
            <strong class="text-rose-400 uppercase tracking-wide text-[10px] block mb-0.5">Ledger Mismatch Detected</strong>
            The transaction history does not match the account balance. Discrepancy Margin: <span class="text-rose-400 font-mono font-bold">NRs. ${discrepancyAmt}</span>. Please verify inputs.
        </div>
    `);
    console.warn("Ledger reconciliation mismatch detected. Discrepancy: NRs. " + discrepancyAmt);
}

// 2. Audit Check: Overdrawn / Negative Balance Account Status
if (reportedBalance < 0) {
    let absoluteNegAmt = Math.abs(reportedBalance).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    warningItems.push(`
        <div>
            <strong class="text-rose-400 uppercase tracking-wide text-[10px] block mb-0.5">Critical Error: Overdrawn Account</strong>
            This vault balance is negative: <span class="text-rose-400 font-mono font-bold">-NRs. ${absoluteNegAmt}</span>. Financial features are restricted until settled.
        </div>
    `);
    console.error("Critical Account Error: Terminal account state is overdrawn (" + reportedBalance + ")");
} 
// 3. Audit Check: Low Balance Cap Threshold Check for IPO allocations
else if (reportedBalance < 1000) {
    let currentBalAmt = reportedBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    warningItems.push(`
        <div>
            <strong class="text-amber-400 uppercase tracking-wide text-[10px] block mb-0.5">Insufficient IPO Balance Notice</strong>
            The current balance (<span class="text-slate-200 font-mono font-bold">NRs. ${currentBalAmt}</span>) is lower than the minimum requirement of <span class="text-amber-400 font-mono font-bold">NRs. 1,000.00</span> needed to apply for IPO blocks.
        </div>
    `);
    console.warn("IPO Threshold Warning: Balance falls below minimum capital pool limits.");
}

// 4. Construct and Inject UI Notice Bounding Wrapper
if (warningItems.length > 0) {
    // Remove any old warning bars first to prevent duplicates if calculations run multiple times
    $('#ledger-audit-warning').remove();

    // Determine theme colors based on severity (rose background for critical errors/mismatches, amber for notice warnings)
    const isCritical = (reportedBalance < 0 || Math.abs(mathematicalBalance - reportedBalance) > 0.01);
    const alertBgClass = isCritical ? 'bg-rose-600/10 border-rose-500/30' : 'bg-amber-600/10 border-amber-500/30';
    const iconClass = isCritical ? 'fa-exclamation-triangle text-rose-500 bg-rose-500/20' : 'fa-info-circle text-amber-500 bg-amber-500/20';
    const listSpacingClass = warningItems.length > 1 ? 'space-y-3 divide-y divide-slate-800/60 [&>div]:pt-2.5 first:pt-0' : 'space-y-0.5';

    let alertHtml = `
        <div id="ledger-audit-warning" class="mb-8 p-4 ${alertBgClass} rounded-xl flex items-start gap-3.5 animate-fadeIn">
            <div class="w-8 h-8 rounded-lg ${iconClass} flex items-center justify-center flex-shrink-0 mt-0.5 text-xs">
                <i class="fas ${isCritical ? 'fa-exclamation-triangle' : 'fa-info-circle'}"></i>
            </div>
            <div class="text-xs text-slate-300 leading-normal w-full ${listSpacingClass}">
                ${warningItems.join('')}
            </div>
        </div>
    `;

    // INJECT ON TOP: Adds notice container underneath the breadcrumb header area layout wrapper elements
    $('.mb-8.flex.flex-col').first().after(alertHtml);
} else {
    // Clean up dashboard message nodes if parameters balance out perfectly during runtime tracking loops
    $('#ledger-audit-warning').remove();
}
});
// ========================================================
// REUSABLE OVERLAY SYSTEM CONTROLLER
// ========================================================
function openLedgerModal(mode, data) {
    const modal = document.getElementById('ledgerActionModal');
    const container = document.getElementById('modalContainer');
    const title = document.getElementById('modalTitle');
    const wrapper = document.getElementById('modalBodyWrapper');
    const submitBtn = document.getElementById('modalSubmitBtn');
    
    // Assign routing values
    document.getElementById('action_id').value = data.id;
    document.getElementById('action_type').value = mode === 'edit' ? 'edit_ledger_transaction' : 'delete_ledger_transaction';

    if (mode === 'edit') {
        title.innerText = "Modify Ledger Transaction";
        submitBtn.className = "px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-lg shadow-blue-600/20 cursor-pointer";
        submitBtn.innerHTML = `<i class="fas fa-save"></i> Save Changes`;
        
        wrapper.innerHTML = `
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Transaction Date</label>
                    <input id="nepali-date-pick" type="text" name="date" value="${data.date}" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs font-mono text-slate-300 focus:outline-none focus:border-slate-600 transition-all">
                </div>
                <div class="col-span-2">
                    <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-1.5">Particular Description</label>
                    <input type="text" name="particular" value="${escapeHtml(data.particular)}" required class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-slate-300 focus:outline-none focus:border-slate-600 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-1.5">Credit / Deposit Amount</label>
                    <input type="number" step="0.01" min="0" name="deposit_amt" value="${parseFloat(data.deposit) || 0}" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs font-mono text-emerald-400 focus:outline-none focus:border-emerald-600 transition-all">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-rose-500 uppercase tracking-widest mb-1.5">Debit / Withdrawal Amount</label>
                    <input type="number" step="0.01" min="0" name="withdraw_amt" value="${parseFloat(data.withdraw) || 0}" class="w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs font-mono text-rose-400 focus:outline-none focus:border-rose-600 transition-all">
                </div>
            </div>
        `;
    } else if (mode === 'delete') {
        title.innerText = "Purge Ledger Transaction";
        submitBtn.className = "px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold uppercase tracking-wider rounded-xl transition-all shadow-lg shadow-rose-600/20 cursor-pointer";
        submitBtn.innerHTML = `<i class="fas fa-trash-alt"></i> Delete Permanently`;
        
        wrapper.innerHTML = `
            <div class="p-3 bg-rose-500/5 border border-rose-500/20 rounded-xl flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-rose-500 mt-0.5 text-sm"></i>
                <div class="space-y-1">
                    <h4 class="text-xs font-bold text-slate-200">Are you absolutely sure?</h4>
                    <p class="text-[11px] text-slate-400 leading-relaxed font-sans">
                        You are about to delete the transaction log entry: <span class="text-rose-400 font-mono font-bold">"${escapeHtml(data.particular)}"</span>. This will alter the running balance chain calculation history and cannot be undone.
                    </p>
                </div>
            </div>
        `;
    }

    // Smooth entry presentation animations
    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        container.classList.remove('scale-95');
    }, 10);
}

function closeLedgerModal() {
    const modal = document.getElementById('ledgerActionModal');
    const container = document.getElementById('modalContainer');
    
    modal.classList.add('opacity-0');
    container.classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.getElementById('ledgerActionForm').reset();
    }, 200);
}

// ========================================================
// ASYNCHRONOUS FORM PROCESSING PIPELINE
// ========================================================
function handleLedgerFormSubmit(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const serializedData = {};
    formData.forEach((value, key) => { serializedData[key] = value; });

    const alertContainer = $('#modal-alert-container');
    
    // Disable submit button during processing to prevent double submissions
    const submitBtn = $('#modalSubmitBtn');
    submitBtn.prop('disabled', true).addClass('opacity-50');

    $.ajax({
        url: 'json-api.php',
        type: 'POST',
        data: serializedData,
        dataType: 'json',
        success: function(response) {
            if (response && response.status === 'success') {
                // Show inline success state
                alertContainer.html(`
                    <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs rounded-xl flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span>${response.message || "Operation executed successfully."}</span>
                    </div>
                `).removeClass('hidden');

                // Delay reload slightly so user sees success confirmation badge
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                // Show inline application error state
                alertContainer.html(`
                    <div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs rounded-xl flex items-center gap-2 animate-shake">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Operation Error: ${response.message || "Unknown backend refusal."}</span>
                    </div>
                `).removeClass('hidden');
                
                // Re-enable interactive elements on failure
                submitBtn.prop('disabled', false).removeClass('opacity-50');
            }
        },
        error: function(xhr, status, error) {
            console.error("Critical API Channel Disruption:", error);
            alertContainer.html(`
                <div class="p-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs rounded-xl flex items-center gap-2">
                    <i class="fas fa-server"></i>
                    <span>System error communicating with server endpoint.</span>
                </div>
            `).removeClass('hidden');
            
            submitBtn.prop('disabled', false).removeClass('opacity-50');
        }
    });
}

// Global String Escaper Helper to prevent DOM context injection breaking layout fields
function escapeHtml(string) {
    return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}




</script>