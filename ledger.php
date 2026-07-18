<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// Render the system header with your custom theme layout rules
echo sudo_get_header("ledger");

// Refactored Query: Dynamically extracts split allocations straight out of the users table JSON column
$query = "
    SELECT 
        u.id, 
        u.name, 
        u.username, 
        u.dmat_num,
        COALESCE(l.current_balance, 0.00) as current_balance,
        COALESCE(l.total_deposits, 0.00) as total_deposits,
        COALESCE(l.total_withdrawals, 0.00) as total_withdrawals,
        -- Fall back safely to standard baseline splits if the JSON target string contains structural anomalies
        CAST(COALESCE(json_extract(u.profit_dist_split_para, '$.manager_pct'), 80.00) AS REAL) as manager_pct,
        CAST(COALESCE(json_extract(u.profit_dist_split_para, '$.client_pct'), 20.00) AS REAL) as client_pct,
        CAST(COALESCE(json_extract(u.profit_dist_split_para, '$.agent_pct'), 0.00) AS REAL) as agent_pct
    FROM users u
    LEFT JOIN (
        SELECT 
            dmat_num,
            balance as current_balance,
            SUM(deposit_amt) as total_deposits,
            SUM(withdraw_amt) as total_withdrawals
        FROM ledgers 
        GROUP BY dmat_num 
        HAVING id = MAX(id)
    ) l ON u.dmat_num = l.dmat_num;
";

$result = $db->query($query);
?>

<!-- Header Section -->
<div class="mb-8 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Ledger & Profit Distribution</h1>
        <p class="text-slate-400 text-sm mt-1">Track financial summaries, capital pools, and percentage commission splits across profiles.</p>
    </div>
</div>

<!-- Global Metrics Grid Context Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <!-- Balance Vault -->
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 flex items-center gap-5 shadow-sm">
        <div class="w-12 h-12 bg-blue-500/10 text-blue-500 rounded-xl flex items-center justify-center text-xl">
            <i class="fas fa-wallet"></i>
        </div>
        <div>
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Global Vault Balances</p>
            <p class="text-xl font-bold text-white font-mono mt-1" id="global-balance">Calculating...</p>
        </div>
    </div>
    
    <!-- Capital Inflows -->
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 flex items-center gap-5 shadow-sm">
        <div class="w-12 h-12 bg-emerald-500/10 text-emerald-500 rounded-xl flex items-center justify-center text-xl">
            <i class="fas fa-arrow-down"></i>
        </div>
        <div>
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Aggregate Deposits</p>
            <p class="text-xl font-bold text-white font-mono mt-1" id="global-deposits">Calculating...</p>
        </div>
    </div>

    <!-- Capital Outflows -->
    <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 flex items-center gap-5 shadow-sm">
        <div class="w-12 h-12 bg-rose-500/10 text-rose-500 rounded-xl flex items-center justify-center text-xl">
            <i class="fas fa-arrow-up"></i>
        </div>
        <div>
            <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Aggregate Withdrawals</p>
            <p class="text-xl font-bold text-white font-mono mt-1" id="global-withdrawals">Calculating...</p>
        </div>
    </div>
</div>

<!-- Table Filter Search -->
<div class="mb-4">
    <div class="relative max-w-sm">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-search text-xs"></i>
        </span>
        <input type="text" id="table_search" placeholder="Quick search ledgers..." 
               class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-300 focus:outline-none focus:border-slate-600 transition-all"/>
    </div>
</div>

<!-- Master Ledgers Core Matrix Table -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="ledgerTable">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">User Profile</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Financial Vault Balance</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Split Allocations</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Manage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php 
                $sn = 1;
                $totalBalance = 0;
                $totalDeposits = 0;
                $totalWithdrawals = 0;
                $hasRows = false;

                while($row = $result->fetchArray(SQLITE3_ASSOC)){
                    $hasRows = true;
                    $totalBalance += $row['current_balance'];
                    $totalDeposits += $row['total_deposits'];
                    $totalWithdrawals += $row['total_withdrawals'];
                    echo '
                    <tr class="hover:bg-slate-800/20 transition-colors group">
                        <!-- Serial Number -->
                        <td class="px-6 py-4 text-xs text-slate-600 font-mono">'.sprintf("%02d", $sn).'</td>
                        
                        <!-- Account Details -->
                        <td class="px-6 py-4">
                            <a href="ledger_view.php?dmat='.urlencode($row['dmat_num']).'" class="flex flex-col group/item max-w-max cursor-pointer">
    <span class="text-sm font-semibold text-slate-200 group-hover:text-blue-400 transition-colors">
        '.htmlspecialchars($row['name'] ?? $row['username']).'
    </span>
    <span class="text-[10px] text-slate-500 font-mono uppercase tracking-tighter group-hover/item:text-slate-400 transition-colors">
        DMAT: '.htmlspecialchars($row['dmat_num']).'
    </span>
</a>
                        </td>
                        
                        <!-- Vault Balance Statistics -->
                        <td class="px-6 py-4">
                            <div class="flex flex-col">
                                <span class="text-xs font-mono font-bold '.($row['current_balance'] >= 0 ? 'text-emerald-400' : 'text-rose-400').'">
                                    NRs. '.number_format($row['current_balance'], 2).'
                                </span>
                                <div class="flex gap-1 text-[9px] text-slate-600 font-mono mt-0.5">
                                    <span>In: <span class="text-slate-400">'.number_format($row['total_deposits'], 0).'</span></span>
                                    <span>•</span>
                                    <span>Out: <span class="text-slate-400">'.number_format($row['total_withdrawals'], 0).'</span></span>
                                </div>
                            </div>
                        </td>
                        
                        <!-- Commission Schemes Split Layout badges -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded bg-blue-500/10 border border-blue-500/20 text-blue-400 text-[10px] font-mono font-bold" title="Manager Cut">
                                    M: '.number_format($row['manager_pct'], 1).'%
                                </span>
                                <span class="px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-[10px] font-mono font-bold" title="Client Cut">
                                    C: '.number_format($row['client_pct'], 1).'%
                                </span>';
                                if ($row['agent_pct'] > 0) {
                                    echo '
                                    <span class="px-2 py-0.5 rounded bg-amber-500/10 border border-amber-500/20 text-amber-400 text-[10px] font-mono font-bold" title="Agent Cut">
                                        A: '.number_format($row['agent_pct'], 1).'%
                                    </span>';
                                }
                            echo '
                            </div>
                        </td>
                        
                        <!-- Actions Routing Options inside Dashboard Wrapper -->
<td class="px-6 py-4 text-center">
    <div class="flex items-center justify-center gap-2 opacity-80 group-hover:opacity-100 transition-opacity">
        <!-- View Ledger Details Link -->
        <a href="ledger_view.php?dmat='.urlencode($row['dmat_num']).'" 
           class="inline-flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-xs bg-slate-900 text-slate-400 hover:bg-blue-500/10 hover:text-blue-400 border border-slate-800 hover:border-blue-500/20 transition-all font-semibold cursor-pointer">
            <i class="fas fa-eye text-[10px]"></i> View
        </a>

        <!-- Distribute Capital Link -->
        <a href="profit_distribute.php?dmat='.urlencode($row['dmat_num']).'" 
           class="inline-flex items-center justify-center gap-2 px-3 py-1.5 rounded-lg text-xs bg-slate-900 text-slate-400 hover:bg-emerald-500/10 hover:text-emerald-400 border border-slate-800 hover:border-emerald-500/20 transition-all font-semibold cursor-pointer">
            <i class="fas fa-coins text-[10px]"></i> Distribute
        </a>
    </div>
</td>
                    </tr>';
                    $sn++;
                }

                if (!$hasRows) {
    echo '<tr id="no-data-row"><td colspan="5" class="px-6 py-8 text-center text-xs text-slate-500 italic"><i class="fas fa-folder-open mb-2 text-lg block"></i>No user tracking metrics logged in database yet.</td></tr>';
}
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
$(document).ready(function() {
    // Safely assign variables from PHP
    const globalBalance = <?= $totalBalance ?>;
    const globalDeposits = <?= $totalDeposits ?>;
    const globalWithdrawals = <?= $totalWithdrawals ?>;

    // Dynamically update text layouts
    $('#global-balance')
        .text('NRs. ' + globalBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
        .addClass(globalBalance >= 0 ? 'text-emerald-400' : 'text-rose-400')
        .removeClass('text-white'); // Remove the default text-white color

    $('#global-deposits').text('NRs. ' + globalDeposits.toLocaleString('en-US', { minimumFractionDigits: 0 }));
    $('#global-withdrawals').text('NRs. ' + globalWithdrawals.toLocaleString('en-US', { minimumFractionDigits: 0 }));

    // Realtime search filter
    $("#table_search").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#ledgerTable tbody tr").filter(function() {
            // Prevent matching the empty fallback row if visible
            if ($(this).attr('id') === 'no-data-row') return;
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>