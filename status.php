<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("status");
?>

<!-- Header & Control Bar -->
<div class="mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Application Status Tracker</h1>
            <p class="text-slate-400 text-sm mt-1">Monitor the verification and allotment progress across all linked accounts.</p>
        </div>
        
        <!-- Action Group -->
        <div class="flex flex-wrap items-center gap-2">
            <button id="generateStatusButtonsall_btn" onclick="generateStatusButtonsall();" 
                    class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all active:scale-95 shadow-lg shadow-blue-900/20">
                Check Status All
            </button>
            <!-- Dynamic Latest Buttons -->
            <button id="checkstatusall0" onclick="checkstatusallforlatest(0);" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl text-[10px] font-bold uppercase border border-slate-700 transition-all"></button>
            <button id="checkstatusall1" onclick="checkstatusallforlatest(1);" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl text-[10px] font-bold uppercase border border-slate-700 transition-all"></button>
            <button id="checkstatusall2" onclick="checkstatusallforlatest(2);" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2.5 rounded-xl text-[10px] font-bold uppercase border border-slate-700 transition-all"></button>
        </div>
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
               placeholder="Filter by name or account..." />
    </div>
</div>

<!-- Data Table -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Investor</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Process</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Latest Log Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">

<?php 
$query1="SELECT * FROM users WHERE is_active=1;";
$result=$db->query($query1);
$sn = 1;
while($row= $result->fetchArray()){
    $log_content = "";
    $raw_log = $row['lastStatusLog'];
    
    if (!empty($raw_log)) {
        $json = json_decode($raw_log);
        $remark = ($json->meroshareRemark == $json->reasonOrRemark) 
                   ? $json->meroshareRemark 
                   : $json->meroshareRemark . " (Reason: " . $json->reasonOrRemark . ")";
        
        $log_parts = explode("_scrip:", $row['lastStatusLogTime']);
        $log_date = sudo_get_time_diff($log_parts[0]);
        $scrip = $log_parts[1] ?? 'N/A';
        
        $alloted_info = (isset($json->receivedKitta) && $json->receivedKitta != null) 
                        ? '<p class="text-blue-400 font-bold">Allotted: '.$json->receivedKitta.'</p>' 
                        : '';
        
        // Dynamic Status Badge
        if ($json->statusName == "Verified" || $json->statusName == "Alloted") {
            $status_badge = '<span class="bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter">'.$json->statusName.'</span>';
        } else {
            $status_badge = '<span class="bg-red-500/10 text-red-400 border border-red-500/20 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-tighter">'.$json->statusName.'</span>';
        }

        $log_content = '
            <div class="space-y-1 py-2">
                <div class="flex items-center gap-2">
                    <span class="text-white font-semibold text-xs">'.$scrip.'</span>
                    '.$status_badge.'
                </div>
                <div class="grid grid-cols-2 gap-x-4 text-[11px]">
                    <p class="text-slate-500">Applied: <span class="text-slate-300">'.$json->appliedKitta.'</span></p>
                    '.$alloted_info.'
                </div>
                <p class="text-[10px] text-slate-500 leading-tight">Remark: '.$remark.'</p>
                <p class="text-[9px] text-slate-600 font-mono mt-1">Checked: '.$log_date.'</p>
            </div>';
    }

    echo '
    <tr class="hover:bg-slate-800/20 transition-colors">
        <td class="px-6 py-4 text-xs text-slate-600 font-mono">'.sprintf("%02d", $sn).'</td>
        <td class="px-6 py-4">
            <div class="flex flex-col">
                <span class="text-sm font-semibold text-slate-200">'.$row['name'].'</span>
                <span class="text-[10px] text-slate-500 font-mono">'.$row['dmat_num'].'</span>
            </div>
        </td>
        <td class="px-6 py-4 text-center" id="btn_'.$row['dmat_num'].'">
            <button class="bg-slate-700 hover:bg-blue-600 text-white text-[10px] font-bold py-1.5 px-4 rounded-lg transition-all active:scale-95 border border-slate-600" 
                    id="checkStatus_'.$row['dmat_num'].'" 
                    onclick="generateStatusButtons(\''.$row['dmat_num'].'\');">
                Fetch Status
            </button>
        </td>
        <td class="px-6 py-4" id="log_'.$row['dmat_num'].'">
            '.$log_content.'
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