<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
// The header function now handles the sidebar (aside) and the start of the main area
echo sudo_get_header("login");

// --- INJECT THIS AT THE TOP OF YOUR MAIN INDEX.PHP ---
// Ensure session is started (if not already started by config.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$minorAlerts = [];
$minorWarnings = [];

// Only run the calculation if we haven't shown the modal in this session yet
if (!isset($_SESSION['minor_modal_shown'])) {
    
    $results = $db->query("SELECT name, dmat_num, mydetails FROM users WHERE mydetails IS NOT NULL AND mydetails != '' AND mydetails NOT LIKE 'ERROR_%'");
    
    $today = new DateTime();
    
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $details = json_decode($row['mydetails'], true);
        
        if (isset($details['dob'])) {
            $dobDate = new DateTime($details['dob']);
            
            // Calculate exact 18th birthday
            $eighteenthBday = clone $dobDate;
            $eighteenthBday->modify('+18 years');
            
            // Calculate difference in days (Returns positive if in future, negative if in past)
            $interval = $today->diff($eighteenthBday);
            $daysUntil18 = (int)$interval->format('%R%a'); 
            
            if ($daysUntil18 > 0 && $daysUntil18 <= 180) {
                // Turning 18 within 6 months
                $minorWarnings[] = [
                    'name' => $row['name'],
                    'dmat' => $row['dmat_num'],
                    'msg'  => "Turning 18 in {$daysUntil18} days (" . $eighteenthBday->format('Y-m-d') . ")"
                ];
            } elseif ($daysUntil18 <= 0 && $daysUntil18 >= -90) {
                // Turned 18 recently (within last 90 days)
                $daysAgo = abs($daysUntil18);
                $minorAlerts[] = [
                    'name' => $row['name'],
                    'dmat' => $row['dmat_num'],
                    'msg'  => "Turned 18 {$daysAgo} days ago (" . $eighteenthBday->format('Y-m-d') . ")"
                ];
            }
        }
    }
    
    // If we found anyone, flag the session so it doesn't pop up again on refresh
    if (count($minorAlerts) > 0 || count($minorWarnings) > 0) {
        $_SESSION['minor_modal_shown'] = true;
    }
}
// --- END PHP INJECTION ---


?>

<!-- No more <div id="main"> here, it is already opened in the header -->

<!-- Header Section -->
<div class="mb-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Login Page</h1>
            <p class="text-slate-400 text-sm mt-1 max-w-2xl">
                From this page you can login to meroshare. Logins are usually valid for **60 minutes**.
            </p>
        </div>
        <div>
            <button id="loginallbtn" 
                    class="w-full md:w-auto bg-blue-600 hover:bg-blue-500 text-white px-6 py-2.5 rounded-xl font-semibold shadow-lg shadow-blue-900/20 transition-all active:scale-95 flex items-center justify-center gap-2" 
                    onclick="loginall();">
                <i class="fas fa-plug text-xs"></i>
                Login All
            </button>
        </div>
    </div>
</div>

<!-- Search Bar -->
<div class="mb-6">
    <div class="relative max-w-md">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500 font-bold">
            <i class="fas fa-search text-sm"></i>
        </span>
        <input type="text" id="table_search" 
               class="block w-full pl-10 pr-3 py-2.5 bg-[#161b22] border border-slate-700 rounded-xl text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-600/50 focus:border-blue-600 transition-all" 
               placeholder="Search Table..." />
    </div>
</div>

<!-- Table Container -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">SN</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Name</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Demat</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Last Login</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Action</th>
                    <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Log</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php 
                $query1="SELECT * FROM users WHERE is_active=1;";
                $result=$db->query($query1);
                $sn=1;
                while($row= $result->fetchArray()){
                    $min = ceil((time()-$row['lastLogin'])/60);
                    
                    if ($min > 60) {
                        $lastLogin = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/10 text-red-400 border border-red-500/20">Login Expired</span>';
                    } else {
                        $lastLogin = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-500/10 text-green-400 border border-green-500/20">'.$min.' min ago</span>';
                    }    
                    
                    echo '
                    <tr class="hover:bg-slate-800/20 transition-colors">
                        <td class="px-6 py-4 text-sm text-slate-500 font-mono">'.$sn.'</td>
                        <td class="px-6 py-4">
    <a href="user.php?dmat='.$row['dmat_num'].'" class="group block">
        <div class="flex flex-col">
            <!-- Name: Gets brighter on hover -->
            <span class="text-sm font-semibold text-slate-200 group-hover:text-blue-400 transition-colors">
                '.$row['name'].'
            </span>
            
            <!-- DP Name: Subtle underline or arrow hint on hover -->
            <span class="text-[10px] text-blue-500 font-mono uppercase tracking-tighter flex items-center gap-1">
                '.$row['dpName'].'
                <i class="fas fa-arrow-right opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-[8px]"></i>
            </span>
        </div>
    </a>
</td>
                        <td class="px-6 py-4 text-sm text-slate-400 text-center">'.$row['dmat_num'].'</td>
                        <td class="px-6 py-4 text-center" id="lastLogin_'.$row['dmat_num'].'">'.$lastLogin.'</td>
                        <td class="px-6 py-4 text-center">
                            <button class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-2 px-4 rounded-lg transition-all active:scale-95 shadow-lg shadow-blue-900/20" 
                                    id="login_'.$row['dmat_num'].'" 
                                    onclick="login(\''.$row['clientId'].'\',\''.$row['username'].'\',\''.$row['password'].'\',\''.$row['dmat_num'].'\');">
                                Login
                            </button>
                        </td>
                        <td class="px-6 py-4">
                            <div id="log_'.$row['dmat_num'].'" class="text-[11px] font-mono text-slate-500 italic"></div>
                        </td>
                    </tr>';
                    $sn++;
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (count($minorAlerts) > 0 || count($minorWarnings) > 0): ?>
    
    <div id="systemMinorModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-[#0b0e14]/90 backdrop-blur-sm transition-opacity duration-300 opacity-0 pointer-events-none">
        
        <div id="systemMinorModalContent" class="bg-[#161b22] border border-slate-800 rounded-3xl p-0 max-w-lg w-full shadow-2xl transform scale-95 transition-all duration-300 overflow-hidden mx-4">
            
            <div class="bg-slate-900/80 border-b border-slate-800 p-6 flex justify-between items-center relative overflow-hidden">
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="relative z-10 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-500/10 flex items-center justify-center text-amber-500 border border-amber-500/20">
                        <i class="fas fa-user-clock text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold text-lg tracking-tight">KYC Action Required</h3>
                        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Minor Status Expiry Report</p>
                    </div>
                </div>
                <button onclick="closeMinorAlertModal()" class="relative z-10 text-slate-500 hover:text-white transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="p-6 max-h-[60vh] overflow-y-auto space-y-4 custom-scrollbar">
                
                <?php if (count($minorAlerts) > 0): ?>
                    <h4 class="text-[10px] text-rose-500 font-black uppercase tracking-widest border-b border-slate-800 pb-2 mb-3">🔴 Urgent: Minor Status Expired</h4>
                    <div class="space-y-3 mb-6">
                        <?php foreach ($minorAlerts as $alert): ?>
                            <div class="bg-rose-500/5 border border-rose-500/20 rounded-xl p-3 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-bold text-white"><?= htmlspecialchars($alert['name']) ?></p>
                                    <p class="text-[10px] text-rose-400 font-mono"><?= $alert['msg'] ?></p>
                                </div>
                                <a href="user.php?dmat=<?= $alert['dmat'] ?>" class="text-[10px] bg-rose-500/10 text-rose-400 px-3 py-1.5 rounded-lg hover:bg-rose-500/20 transition-colors uppercase font-bold tracking-wider">View</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($minorWarnings) > 0): ?>
                    <h4 class="text-[10px] text-amber-500 font-black uppercase tracking-widest border-b border-slate-800 pb-2 mb-3">🟡 Upcoming: Approaching 16</h4>
                    <div class="space-y-3">
                        <?php foreach ($minorWarnings as $warn): ?>
                            <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-3 flex justify-between items-center">
                                <div>
                                    <p class="text-sm font-bold text-white"><?= htmlspecialchars($warn['name']) ?></p>
                                    <p class="text-[10px] text-amber-400 font-mono"><?= $warn['msg'] ?></p>
                                </div>
                                <a href="user.php?dmat=<?= $warn['dmat'] ?>" class="text-[10px] bg-amber-500/10 text-amber-400 px-3 py-1.5 rounded-lg hover:bg-amber-500/20 transition-colors uppercase font-bold tracking-wider">View</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-blue-500/5 border border-blue-500/10 rounded-xl p-4 mt-6 text-center">
                    <p class="text-[11px] text-slate-400 leading-relaxed">
                        Accounts must submit an Adult KYC and Citizenship Certificate to their Depository Participant to maintain active Demat and TMS access.
                    </p>
                </div>
            </div>

            <div class="p-6 border-t border-slate-800 bg-slate-900/30 text-right">
                <button onclick="closeMinorAlertModal()" class="bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold uppercase tracking-widest px-6 py-2.5 rounded-xl transition-colors w-full md:w-auto">
                    Acknowledge
                </button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Trigger animation slightly after page load
            setTimeout(() => {
                const modal = document.getElementById('systemMinorModal');
                const content = document.getElementById('systemMinorModalContent');
                
                modal.classList.remove('opacity-0', 'pointer-events-none');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 500);
        });

        function closeMinorAlertModal() {
            const modal = document.getElementById('systemMinorModal');
            const content = document.getElementById('systemMinorModalContent');
            
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            modal.classList.add('opacity-0');
            
            // Remove from DOM after animation
            setTimeout(() => {
                modal.remove();
            }, 300);
        }
    </script>
<?php endif; ?>


<?php include('footer.php'); ?>