<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// 1. Pagination Logic
$limit = 500; // Logs per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total count for pagination links
$totalLogs = $db->querySingle("SELECT COUNT(*) FROM system_logs");
$totalPages = ceil($totalLogs / $limit);

// Mark as read if requested
if(isset($_GET['action']) && $_GET['action'] == 'mark_all_read') {
    $db->exec("UPDATE system_logs SET is_notified = 1 WHERE is_notified = 0");
    header("Location: notifications.php");
    exit;
}

echo sudo_get_header("notifications", $db);
?>

<!-- ... [Keep your Stats Grid and Header Section here] ... -->
<!-- Header & Stats Section -->
<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">System Audit Logs</h1>
            <p class="text-slate-400 text-sm mt-1 max-w-2xl">
                Track all automated activities, including password rotations and profile syncs.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="notifications.php?action=mark_all_read" 
               class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-4 py-2.5 rounded-xl text-xs font-bold transition-all border border-slate-700 flex items-center gap-2">
                <i class="fas fa-check-double text-[10px]"></i>
                Mark All Read
            </a>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
    <?php
    $successCount = $db->querySingle("SELECT COUNT(*) FROM system_logs WHERE status = 'SUCCESS'");
    $errorCount = $db->querySingle("SELECT COUNT(*) FROM system_logs WHERE status = 'ERROR'");
    $pendingNotify = $db->querySingle("SELECT COUNT(*) FROM system_logs WHERE is_notified = 0");
    ?>
    <div class="bg-[#161b22] border border-slate-800 p-5 rounded-2xl">
        <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Total Success</p>
        <h3 class="text-2xl font-bold text-emerald-400 mt-1"><?php echo $successCount; ?></h3>
    </div>
    <div class="bg-[#161b22] border border-slate-800 p-5 rounded-2xl">
        <p class="text-slate-500 text-[10px] uppercase font-bold tracking-widest">Failures Detected</p>
        <h3 class="text-2xl font-bold text-rose-500 mt-1"><?php echo $errorCount; ?></h3>
    </div>
    <div class="bg-[#161b22] border border-slate-800 p-5 rounded-2xl border-blue-500/30 bg-blue-500/5">
        <p class="text-blue-400 text-[10px] uppercase font-bold tracking-widest">New Alerts</p>
        <h3 class="text-2xl font-bold text-white mt-1"><?php echo $pendingNotify; ?></h3>
    </div>
</div>

<!-- Filters & Search -->
<div class="mb-6 flex flex-col md:flex-row gap-4">
    <div class="relative flex-1 max-w-md">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-filter text-sm"></i>
        </span>
        <input type="text" id="log_search" 
               class="block w-full pl-10 pr-3 py-2.5 bg-[#161b22] border border-slate-700 rounded-xl text-sm text-slate-200 placeholder-slate-500 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all" 
               placeholder="Search logs by message or user..." />
    </div>
</div>
<!-- Table Container -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="logs-table">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Timestamp</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">User</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Type</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Status</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Message</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php 
// Fetch only the specific slice of logs
$logQuery = "SELECT l.*, u.name as user_name 
            FROM system_logs l 
            LEFT JOIN users u ON l.user_id = u.id 
            ORDER BY l.id DESC LIMIT $limit OFFSET $offset";
$logs = $db->query($logQuery);

// Define color mappings for different log types
$typeColors = [
    'INFO'     => 'bg-blue-500/10 text-blue-400 border border-blue-500/20',
    'WARNING'  => 'bg-amber-500/10 text-amber-400 border border-amber-500/20',
    'ERROR'    => 'bg-rose-500/10 text-rose-400 border border-rose-500/20',
    'SUCCESS'  => 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20',
    'CRITICAL' => 'bg-red-600/20 text-red-500 border border-red-500/30'
];

while($log = $logs->fetchArray()){
    // Status styles
    $statusClass = ($log['status'] == 'SUCCESS' || $log['log_type'] == 'SUCCESS') ? 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20' : 'text-rose-400 bg-rose-400/10 border-rose-400/20';
    $unreadClass = ($log['is_notified'] == 0) ? 'border-l-4 border-l-blue-600 bg-blue-600/5' : '';
    
    // Log Type styles (Fallback to slate if the type isn't in our array)
    $logType = strtoupper($log['log_type']);
    $typeClass = $typeColors[$logType] ?? 'bg-slate-800 text-slate-400 border border-slate-700';
    
    echo '
    <tr class="hover:bg-slate-800/20 transition-colors '.$unreadClass.'">
        <td class="px-6 py-4 text-[11px] text-slate-500 font-mono">' . 
     (!empty($log['created_at']) ? date("M d, H:i", strtotime($log['created_at'])) : '---') . 
     '</td>
        <td class="px-6 py-4 text-sm text-slate-300">'.($log['user_name'] ?? 'System').'</td>
        <td class="px-6 py-4 text-center">
            <span class="text-[9px] font-bold px-2 py-1 rounded uppercase tracking-tighter '.$typeClass.'">'.$log['log_type'].'</span>
        </td>
        <td class="px-6 py-4 text-center">
            <span class="px-2.5 py-1 text-[10px] font-normal ">'.$log['status'].'</span>
        </td>
        <td class="px-6 py-4">
            <div class="flex flex-col">
                <span class="text-xs text-slate-300">'.$log['message'].'</span>
                <span class="text-[9px] text-slate-600 font-mono mt-1">Step: '.$log['step'].'</span>
            </div>
        </td>
    </tr>';
}
?>
            </tbody>
        </table>
    </div>

    <!-- Pagination Footer -->
    <div class="px-6 py-4 bg-slate-800/20 border-t border-slate-800 flex items-center justify-between">
        <p class="text-xs text-slate-500">
            Showing <span class="text-slate-300"><?php echo $offset + 1; ?></span> to 
            <span class="text-slate-300"><?php echo min($offset + $limit, $totalLogs); ?></span> of 
            <span class="text-slate-300"><?php echo $totalLogs; ?></span> logs
        </p>
        
        <div class="flex gap-2">
            <!-- Previous Button -->
            <?php if($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="px-3 py-1.5 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg text-xs hover:bg-slate-700 transition-colors">Previous</a>
            <?php endif; ?>

            <!-- Next Button -->
            <?php if($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="px-3 py-1.5 bg-slate-800 border border-slate-700 text-slate-300 rounded-lg text-xs hover:bg-slate-700 transition-colors">Next</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
$(document).ready(function(){
    // Live Search Logic
    $("#log_search").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#logs-table tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });
});
</script>

<?php include('footer.php'); ?>