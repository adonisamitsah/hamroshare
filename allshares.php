<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("allshares");
?>

<!-- Header & Global Actions -->
<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Portfolio Overview</h1>
        <p class="text-slate-400 text-sm mt-1">Snapshot of all scripts held across your linked accounts.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="myshare_report_table.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-slate-700">
            <i class="fas fa-file-invoice mr-2 opacity-50"></i> Detailed Report
        </a>
        <button id="mysharesall" onclick="mysharesall();" 
                class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all shadow-lg shadow-blue-900/20 flex items-center gap-2">
            <i class="fas fa-sync-alt"></i> Fetch All Shares
        </button>
    </div>
</div>

<!-- Search area -->
<div class="mb-6">
    <div class="relative max-w-md">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-search text-xs"></i>
        </span>
        <input type="text" id="table_search" 
               class="block w-full pl-10 pr-3 py-2.5 bg-[#161b22] border border-slate-700 rounded-xl text-sm text-slate-200 placeholder-slate-500 focus:ring-1 focus:ring-blue-600 outline-none transition-all" 
               placeholder="Search by name or symbol..." />
    </div>
</div>

<!-- Main Table Container -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Investor</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Action</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Current Holdings Portfolio</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">

<?php 
$query1="SELECT * FROM users Where is_active=1;";
$result=$db->query($query1);
$sn=1;
while($row= $result->fetchArray()){
    $json_data = $row['myshare'];
    $inner_table = "";
    $json = json_decode($json_data);

    if (!empty($json) && $json->totalItems > 0) {
        $update_time = sudo_get_time_diff($row['myshare_time']);
        
        $inner_table = '<div class="mt-2 mb-4 space-y-2">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-[9px] font-mono text-slate-500 uppercase tracking-widest bg-slate-900 px-2 py-0.5 rounded border border-slate-800">Last Sync: '.$update_time.'</span>
            </div>
            <table class="w-full text-left rounded-lg overflow-hidden border border-slate-800/50">
                <thead class="bg-slate-900/50 text-[9px] uppercase tracking-tighter text-slate-500 border-b border-slate-800">
                    <tr class="nosearch">
                        <th class="px-3 py-2 font-bold">Symbol</th>
                        <th class="px-3 py-2 font-bold text-center">Kitta</th>
                        <th class="px-3 py-2 font-bold text-center">LTP</th>
                        <th class="px-3 py-2 font-bold text-center">Value</th>
                        <th class="px-3 py-2 font-bold text-right">Day Chg %</th>
                    </tr>
                </thead>
                <tbody class="text-[11px] divide-y divide-slate-800/50">';

        for ($i = 0; $i < $json->totalItems; $i++) {
            $item = $json->meroShareMyPortfolio[$i];
            $ltpval = $item->valueAsOfLastTransactionPrice;
            $pcpval = $item->valueAsOfPreviousClosingPrice;
            
            $percent_html = "";
            if ($ltpval > $pcpval) {
                $change = round((($ltpval - $pcpval) / $ltpval) * 100, 2);
                $percent_html = '<span class="text-green-400 font-bold">'.$change.'% <i class="fas fa-caret-up ml-1"></i></span>'; 
            } elseif ($ltpval < $pcpval) {
                $change = round((($ltpval - $pcpval) / $ltpval) * 100, 2);
                $percent_html = '<span class="text-red-400 font-bold">'.$change.'% <i class="fas fa-caret-down ml-1"></i></span>'; 
            } else {
                $percent_html = '<span class="text-slate-500">—</span>';
            }

            $inner_table .= '
                <tr class="nosearch bg-slate-900/20 hover:bg-slate-800/40 transition-colors">
                    <td class="px-3 py-2 font-semibold text-slate-300" data-tippy-content="'.$item->scriptDesc.'">'.$item->script.'</td>
                    <td class="px-3 py-2 text-center text-slate-400">'.$item->currentBalance.'</td>
                    <td class="px-3 py-2 text-center text-slate-400 font-mono">'.number_format($item->lastTransactionPrice, 2).'</td>
                    <td class="px-3 py-2 text-center text-slate-200 font-semibold">'.number_format($item->valueAsOfLastTransactionPrice).'</td>
                    <td class="px-3 py-2 text-right">'.$percent_html.'</td>
                </tr>';
        }
        $inner_table .= '</tbody></table></div>';
    }

    echo '
    <tr class="hover:bg-slate-800/20 transition-colors align-top">
        <td class="px-6 py-6 text-xs text-slate-600 font-mono">'.sprintf("%02d", $sn).'</td>
        <td class="px-6 py-6">
            <span class="text-sm font-semibold text-slate-200 block">'.$row['name'].'</span>
            <span class="text-[10px] text-slate-500 font-mono">'.$row['dmat_num'].'</span>
        </td>
        <td class="px-6 py-6">
            <button class="bg-slate-800 hover:bg-blue-600 text-white text-[10px] font-bold py-1.5 px-4 rounded-lg transition-all border border-slate-700" 
                    id="allshares_btn_'.$row['dmat_num'].'" 
                    onclick="myshares(\''.$row['dmat_num'].'\');">
                Sync Details
            </button>
        </td>
        <td class="px-6 py-6" id="sharetable_'.$row['dmat_num'].'">
            '.$inner_table.'
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