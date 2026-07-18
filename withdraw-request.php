<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

$stmt = $db->prepare("SELECT key, value FROM constant WHERE key LIKE 'withdrawal_history_%' ORDER BY key DESC");
$result = $stmt->execute();

$history = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $history[] = [
        'key' => $row['key'],
        'data' => json_decode($row['value'], true)
    ];
}

echo sudo_get_header("profit_distribution");
?>
<div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 uppercase tracking-widest mb-1 font-bold">
            <a href="profit_distribute.php" class="hover:text-blue-500 transition-colors">Profit Distribution Matrix</a>
            <i class="fas fa-chevron-right text-[9px]"></i>
            <span class="text-slate-400">Withdrawal Requests</span>
        </div>
         </div>
    <div>
        <a href="profit_distribute.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all border border-slate-700">
            <i class="fas fa-arrow-left"></i> Back to Summary
        </a>
    </div>
</div>

<div class="max-w-5xl mx-auto p-8">
    <h1 class="text-2xl font-bold text-white mb-8 flex items-center gap-3">
        <i class="fas fa-history text-blue-500"></i> Withdrawal Requests History
    </h1>
    
<div class="space-y-4">
    <?php foreach($history as $entry): 
        $ts = str_replace('withdrawal_history_', '', $entry['key']);
        $formattedDate = DateTime::createFromFormat('YmdHis', $ts)->format('d M Y, H:i');
        $totalAmount = array_sum(array_column($entry['data'], 'withdrawAmount'));
    ?>
    <div class="bg-[#0d1117] border border-slate-800 rounded-2xl p-5 shadow-lg flex items-center justify-between hover:border-amber-500/50 transition-all cursor-pointer group"
         onclick="openBatchModal('<?php echo htmlspecialchars(json_encode($entry['data']), ENT_QUOTES); ?>')">
        
        <div class="flex items-center gap-6">
            <div class="w-12 h-12 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center text-amber-500 group-hover:bg-amber-500/10 transition-colors">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div>
                <h4 class="text-white font-bold text-sm"><?php echo $formattedDate; ?></h4>
                <p class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Settlement Batch ID: <span class="font-mono"><?php echo substr($entry['key'], -8); ?></span></p>
            </div>
        </div>

        <div class="flex items-center gap-8">
            <div class="text-right">
                <p class="text-[9px] text-slate-600 uppercase font-bold tracking-widest">Total Liquidity</p>
                <p class="text-sm font-mono font-bold text-amber-400">NRs. <?php echo number_format($totalAmount, 2); ?></p>
            </div>
            <button class="w-10 h-10 rounded-full bg-slate-900 text-slate-500 hover:bg-rose-900/20 hover:text-rose-400 transition-all" 
                    onclick="deleteBatch(event, '<?php echo $entry['key']; ?>')">
                <i class="fas fa-trash-alt text-xs"></i>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>

<div id="batchModal" class="fixed inset-0 bg-black/80 hidden z-[9999] flex items-center justify-center p-4 backdrop-blur-sm">
    <div class="bg-[#161b22] w-full max-w-5xl rounded-2xl p-8 border border-slate-700 shadow-2xl max-h-[90vh] flex flex-col">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-white font-bold text-lg">Transaction Explorer</h2>
            <div class="flex gap-2">
                <button id="downloadBtn" class="bg-blue-600 hover:bg-blue-500 text-white px-5 py-2.5 rounded-lg text-[10px] uppercase font-bold tracking-wider transition-all">
                    <i class="fas fa-download mr-1"></i> Export Snapshot
                </button>
                <button onclick="closeModal()" class="bg-slate-800 text-slate-300 px-5 py-2.5 rounded-lg text-[10px] uppercase font-bold tracking-wider hover:bg-slate-700">Close</button>
            </div>
        </div>
        <div class="overflow-y-auto custom-scrollbar flex-grow">
            <table class="w-full text-left text-xs text-slate-300 border-collapse">
                <thead class="text-slate-500 uppercase text-[9px] border-b border-slate-800">
                    <tr><th class="p-4">Account</th><th class="p-4">DMAT</th><th class="p-4 text-right">Amount</th><th class="p-4 text-right">Breakdown (C/A/M)</th><th class="p-4 text-center"></th></tr>
                </thead>
                <tbody id="modalTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<div id="actionNotification" class="fixed inset-0 z-[10000] hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-[#161b22] border border-slate-700 p-6 rounded-2xl w-full max-w-sm shadow-2xl text-center">
        <div id="notificationIcon" class="w-12 h-12 mx-auto rounded-full flex items-center justify-center mb-4"></div>
        <h3 id="notificationTitle" class="text-white font-bold mb-2"></h3>
        <p id="notificationMsg" class="text-slate-400 text-xs mb-6"></p>
        <button onclick="closeNotification()" class="w-full bg-slate-800 text-white py-2 rounded-lg text-xs font-bold hover:bg-slate-700">Dismiss</button>
    </div>
</div>
<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 z-[10001] flex items-center justify-center bg-black/80 backdrop-blur-sm hidden">
    <div class="bg-[#161b22] border border-slate-700 p-8 rounded-2xl w-full max-w-sm shadow-2xl text-center">
        <div class="text-rose-500 mb-4 text-4xl">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h2 class="text-white text-xl font-bold mb-2">Permanently remove batch?</h2>
        <p class="text-slate-400 text-sm mb-8">This will revert all ledger entries and reset profit distribution statuses. This action cannot be undone.</p>
        
        <div class="flex gap-3">
            <button onclick="closeDeleteModal()" class="w-full py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold transition">Cancel</button>
            <button id="confirmDeleteBtn" class="w-full py-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-bold transition">Delete Batch</button>
        </div>
    </div>
</div>
<?php include("footer.php"); ?>
<script>
let currentModalData = null;

function openBatchModal(dataJson) {
    currentModalData = JSON.parse(dataJson); // Store for later use (e.g., export)
    const data = JSON.parse(dataJson);
    let tableRows = '';
    
    // Aggregation variables
    let gNet = 0, gM = 0, gC = 0, gA = 0;

    data.forEach(req => {
        gNet += Number(req.withdrawAmount);
        gM += Number(req.totalM);
        gC += Number(req.totalC);
        gA += Number(req.totalA);

        tableRows += `
            <tr class="text-[11px] text-slate-300 hover:bg-slate-900/50 transition-colors">
                <td class="p-4 font-mono text-slate-500">${req.dmat.toString().slice(-4)}</td>
                <td class="p-4 font-bold text-white">${req.name}</td>
                <td class="p-4 font-mono text-blue-400">${req.dmat}</td>
                <td class="p-4">
                    <div class="flex flex-wrap gap-1">
                        ${req.scrips.map(s => `<span class="px-1.5 py-0.5 bg-slate-800 rounded text-[9px] font-bold text-slate-400">${s}</span>`).join('')}
                    </div>
                </td>
                <td class="p-4 text-right font-mono text-amber-400 font-bold">NRs. ${Number(req.withdrawAmount).toFixed(2)}</td>
                <td class="p-4 text-right font-mono text-blue-400">M: ${Number(req.totalM).toFixed(2)}</td>
                <td class="p-4 text-right font-mono text-emerald-400">C: ${Number(req.totalC).toFixed(2)}</td>
                <td class="p-4 text-right font-mono text-amber-500">A: ${Number(req.totalA).toFixed(2)}</td>
            </tr>`;
    });

    const modalHtml = `
    <div id="dynamicBatchModal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
        <div class="bg-[#161b22] border border-amber-500/30 w-full max-w-5xl rounded-2xl shadow-2xl p-6 border-t-4 border-t-amber-500">
            <div class="flex justify-between items-center mb-6 border-b border-slate-800 pb-4">
                <div>
                    <h3 class="text-white font-bold text-sm">Withdrawal Request: Settlement Sandbox</h3>
                    <p class="text-amber-500/70 text-[9px] uppercase tracking-widest font-black">Verified Ledger Outturn</p>
                </div>
                <button onclick="document.getElementById('dynamicBatchModal').remove()" class="text-slate-500 hover:text-white"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="border border-slate-800 rounded-xl overflow-hidden max-h-96 overflow-y-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-slate-900 text-slate-500 text-[10px] uppercase tracking-wider">
                        <tr><th class="p-4">ID</th><th class="p-4">Account</th><th class="p-4">DMAT</th><th class="p-4">Scrips</th><th class="p-4 text-right">Net</th><th class="p-4 text-right">M</th><th class="p-4 text-right">C</th><th class="p-4 text-right">A</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">${tableRows}</tbody>
                    <tfoot class="bg-slate-900 font-bold text-[11px] text-slate-300">
                        <tr><td class="p-4" colspan="4">Total</td>
                        <td class="p-4 text-right text-amber-400">NRs. ${gNet.toFixed(2)}</td>
                        <td class="p-4 text-right text-blue-400">NRs. ${gM.toFixed(2)}</td>
                        <td class="p-4 text-right text-emerald-400">NRs. ${gC.toFixed(2)}</td>
                        <td class="p-4 text-right text-amber-500">NRs. ${gA.toFixed(2)}</td></tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="document.getElementById('dynamicBatchModal').remove()" class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-white text-[10px] font-bold uppercase rounded-xl">Close</button>
                <button onclick="triggerCleanExcelImageDownload(currentModalData);"
            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-bold uppercase rounded-xl shadow-lg shadow-blue-600/20 flex items-center gap-2">
        <i class="fas fa-download"></i> Download Report
    </button>
                </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
   // document.getElementById('downloadBtn').onclick = () => triggerCleanExcelImageDownload(currentModalData);

}

function showNotification(title, message, type = 'success') {
    const modal = document.getElementById('actionNotification');
    const iconDiv = document.getElementById('notificationIcon');
    document.getElementById('notificationTitle').innerText = title;
    document.getElementById('notificationMsg').innerText = message;
    iconDiv.className = `w-12 h-12 mx-auto rounded-full flex items-center justify-center mb-4 ${type === 'success' ? 'bg-emerald-500/20 text-emerald-500' : 'bg-rose-500/20 text-rose-500'}`;
    iconDiv.innerHTML = type === 'success' ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation-triangle"></i>';
    modal.classList.replace('hidden', 'flex');
}

function closeNotification() { document.getElementById('actionNotification').classList.replace('flex', 'hidden'); }
function toggleDetails(index) { document.getElementById(`details-${index}`).classList.toggle('hidden'); }
function closeModal() { document.getElementById('batchModal').style.display = 'none'; }


let batchKeyToDelete = null;

function deleteBatch(e, key) {
    e.stopPropagation();
    batchKeyToDelete = key; // Store the key
    document.getElementById('deleteModal').classList.remove('hidden'); // Show modal
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Handle the actual API call
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    closeDeleteModal();
    
    // Optional: Add a simple loading UI here
    $.post('json-api.php', { action: 'delete_batch', key: batchKeyToDelete }, (resp) => {
        // Assuming your backend sends JSON
        if(resp.status === 'success') {
            showNotification('Success', 'Batch removed successfully.');
        setTimeout(() => location.reload(), 1200);
        } else {
            showNotification('Error', 'Failed to remove batch.');
        }
    }, 'json');
});
</script>