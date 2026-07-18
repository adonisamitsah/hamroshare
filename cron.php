<?php
require_once __DIR__ . '/config.php'; /** *  @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// Handle Updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "update_cron") {
    $stmt = $db->prepare("UPDATE system_crons SET 
                          status = :status, 
                          frequency_minutes = :freq 
                          WHERE id = :id");
    $stmt->bindValue(":status", $_POST["status"], SQLITE3_TEXT);
    $stmt->bindValue(":freq", $_POST["frequency"], SQLITE3_INTEGER);
    $stmt->bindValue(":id", $_POST["cron_id"], SQLITE3_INTEGER);
    $stmt->execute();
    header("Location: cron.php?success=1");
    exit();
}

// Add this handler at the top with your other POST actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "clear_logs") {
    file_put_contents('cron_debug.log', '');
    header("Location: cron.php");
    exit();
}
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "clear_error_logs") {
    file_put_contents('error_log', '');
    header("Location: cron.php");
    exit();
}

echo sudo_get_header("cron");
$results = $db->query("SELECT * FROM system_crons ORDER BY id ASC");
?>

<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-white tracking-tight">Automation Dashboard</h1>
            <p class="text-slate-400 mt-1">Manage system heartbeats and task frequencies.</p>
        </div>
        <div class="bg-slate-900 px-4 py-2 rounded-lg border border-slate-800">
            <span class="text-slate-500 text-xs uppercase font-bold tracking-widest">Server Time:</span>
            <span class="text-blue-400 font-mono ml-2"><?php echo date("H:i:s"); ?></span>
        </div>
    </div>


<div class="bg-[#0d1117] border border-slate-800 rounded-2xl p-6 mb-8 shadow-2xl">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-white font-bold flex items-center gap-2">
            <i class="fas fa-terminal text-blue-500"></i> System Log: cron_debug.log
        </h3>
        <div class="flex items-center gap-4 text-[10px] text-slate-500 font-bold uppercase">
            <span>Size: <?= file_exists('cron_debug.log') ? round(filesize('cron_debug.log') / 1024, 2) . ' KB' : '0 KB'; ?></span>
            
            <a href="view_log.php?log=cron" target="_blank" class="text-blue-500 hover:text-blue-400 transition-colors flex items-center gap-1">
                <i class="fas fa-external-link-alt"></i> View Full
            </a>

            <form method="POST" onsubmit="return confirm('Clear cron logs?')">
                <input type="hidden" name="action" value="clear_logs">
                <button type="submit" class="text-rose-500 hover:text-rose-400 transition-colors">Clear Logs</button>
            </form>
        </div>
    </div>
    <div class="bg-black/40 p-4 rounded-xl font-mono text-[10px] text-emerald-400 h-48 overflow-y-auto border border-slate-800 whitespace-pre-wrap selection:bg-emerald-500/30"><?= getSafeLogOutput('cron_debug.log'); ?></div>
</div>

<div class="bg-[#0d1117] border border-slate-800 rounded-2xl p-6 mb-8 shadow-2xl">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-white font-bold flex items-center gap-2">
            <i class="fas fa-terminal text-rose-500"></i> System Log: error_log
        </h3>
        <div class="flex items-center gap-4 text-[10px] text-slate-500 font-bold uppercase">
            <span>Size: <?= file_exists('error_log') ? round(filesize('error_log') / 1024, 2) . ' KB' : '0 KB'; ?></span>
            
            <a href="view_log.php?log=error" target="_blank" class="text-blue-500 hover:text-blue-400 transition-colors flex items-center gap-1">
                <i class="fas fa-external-link-alt"></i> View Full
            </a>

            <form method="POST" onsubmit="return confirm('Clear error logs?')">
                <input type="hidden" name="action" value="clear_error_logs">
                <button type="submit" class="text-rose-500 hover:text-rose-400 transition-colors">Clear Logs</button>
            </form>
        </div>
    </div>
    <div class="bg-black/40 p-4 rounded-xl font-mono text-[10px] text-rose-400 h-48 overflow-y-auto border border-slate-800 whitespace-pre-wrap selection:bg-rose-500/30"><?= getSafeLogOutput('error_log'); ?></div>
</div>

    <div class="grid grid-cols-1 gap-4">
        <?php while ($row = $results->fetchArray(SQLITE3_ASSOC)): ?>
            <div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 transition-all hover:border-slate-700">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 <?php echo $row["status"] == "enabled" ? "bg-emerald-500/10 text-emerald-500" : "bg-slate-800 text-slate-500"; ?> rounded-xl flex items-center justify-center text-xl">
                            <i class="fas <?php echo $row["cron_key"] == "ipo_scanner" ? "fa-search-dollar" : "fa-key"; ?>"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-lg"><?php echo $row["display_name"]; ?></h3>
                            <p class="text-slate-500 text-sm"><?php echo $row["description"]; ?></p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="text-right mr-4">
                            <p class="text-slate-500 text-[10px] uppercase font-bold">Frequency</p>
                            <p class="text-white font-mono text-sm"><?php echo $row["frequency_minutes"]; ?>m</p>
                        </div>



<?php
$viewUrl = null;
// Select both key and value to allow extraction
$tokenStmt = $db->prepare("SELECT key FROM constant WHERE key LIKE :pattern LIMIT 1");

$patterns = [
    'check_ipo_results' => 'web_report_%',
    'ipo_scanner' => 'scanner_report_%',
    'account_renewal_reminder' => 'renewal_report_%',
    'edis_automation' => 'edis_report_%',
    'allshares' => 'allshares_report_%'
];

if (isset($patterns[$row['cron_key']])) {
    $tokenStmt->bindValue(':pattern', $patterns[$row['cron_key']]);
    $res = $tokenStmt->execute()->fetchArray(SQLITE3_ASSOC);
    
    if ($res) {
        $fullKey = $res['key'];
        // Split by '_' and take the last part as the token
        $parts = explode('_', $fullKey);
        $token = end($parts);
        
        // Define your routes mapping
        $routes = [
            'check_ipo_results' => 'cr_display_ipo_results.php',
            'ipo_scanner' => 'cr_display_apply_ipo_right.php',
            'account_renewal_reminder' => 'cr_display_account_renewal_reminder.php',
            'edis_automation' => 'cr_display_edis_results.php',
            'allshares' => 'cr_display_allshares.php'
        ];
        
        $viewUrl = $routes[$row['cron_key']] . "?token=" . $token;
    }
}
?>

<div class="flex items-center gap-2">
    <?php if($viewUrl): ?>
        <a href="<?php echo $viewUrl; ?>" target="_blank" class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-2 rounded-lg text-sm font-bold transition-all">
            View
        </a>
    <?php endif; ?>
    <button onclick="openCronEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" 
            class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-lg text-sm font-bold transition-all">
        Edit
    </button>
</div>




                        
                    </div>
                </div>

                <div class="mt-6 pt-6 border-t border-slate-800/50 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-slate-500 text-[10px] uppercase font-bold">Status</p>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $row["status"] == "enabled" ? "bg-emerald-500/10 text-emerald-500" : "bg-rose-500/10 text-rose-500"; ?>">
                            <?php echo strtoupper($row["status"]); ?>
                        </span>
                    </div>
                    <div>
                        <p class="text-slate-500 text-[10px] uppercase font-bold">Last Run</p>
                        <p class="text-slate-300 text-xs"><?php echo $row["last_run_at"] ?? "Never"; ?></p>
                    </div>
                    <div>
                        <p class="text-slate-500 text-[10px] uppercase font-bold">Last Health</p>
                        <p class="text-xs font-bold <?php echo $row["last_status"] == "SUCCESS" ? "text-emerald-500" : "text-rose-500"; ?>">
                            <?php echo $row["last_status"] ?? "N/A"; ?>
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-slate-500 text-[10px] uppercase font-bold">Internal Key</p>
                        <code class="text-blue-400 text-[10px]"><?php echo $row["cron_key"]; ?></code>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="croneditModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-[#0d1117] border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
        <form action="cron.php" method="POST">
            <input type="hidden" name="action" value="update_cron">
            <input type="hidden" name="cron_id" id="modal_cron_id">
            
            <div class="p-6">
                <h3 class="text-xl font-bold text-white mb-2" id="modal_title">Edit Task</h3>
                <p class="text-slate-500 text-sm mb-6" id="modal_desc"></p>

                <div class="space-y-4">
                    <div>
                        <label class="block text-slate-400 text-xs font-bold uppercase mb-2">Operation Status</label>
                        <select name="status" id="modal_status" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-all">
                            <option value="enabled">Enabled</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-slate-400 text-xs font-bold uppercase mb-2">Frequency (Minutes)</label>
                        <input type="number" name="frequency" id="modal_freq" class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-white focus:border-blue-500 outline-none transition-all">
                    </div>
                </div>
            </div>

            <div class="bg-slate-900/50 p-6 flex gap-3">
                <button type="button" onclick="closeModal()" class="flex-1 px-4 py-3 bg-slate-800 hover:bg-slate-700 text-white rounded-xl font-bold transition-all">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCronEditModal(cron) {
    document.getElementById("modal_cron_id").value = cron.id;
    document.getElementById("modal_title").innerText = cron.display_name;
    document.getElementById("modal_desc").innerText = cron.description;
    document.getElementById("modal_status").value = cron.status;
    document.getElementById("modal_freq").value = cron.frequency_minutes;
    document.getElementById("croneditModal").classList.remove("hidden");
}

function closeModal() {
    document.getElementById("croneditModal").classList.add("hidden");
}
</script>

<?php include "footer.php"; ?>