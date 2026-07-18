<?php
// view-all-ipo-results.php
require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// 1. DATA PREPARATION (Fetch the live ledger)
$query = "
    SELECT 
        ir.scrip, 
        ir.companyName, 
        ir.statusName, 
        ir.receivedKitta, 
        ir.last_updated,
        u.name, 
        u.dmat_num
    FROM ipo_results ir
    JOIN users u ON ir.dmat_num = u.dmat_num
    WHERE u.is_active = 1
    ORDER BY ir.last_updated DESC, ir.companyName ASC, u.name ASC
";
$result = $db->query($query);

// Restructure data for the UI: Group by Scrip
$ledger = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $scrip = $row['scrip'];
    if (!isset($ledger[$scrip])) {
        $ledger[$scrip] = [
            'companyName' => $row['companyName'],
            'last_updated' => $row['last_updated'],
            'stats' => ['Verified' => 0, 'Alloted' => 0, 'Rejected' => 0, 'Not Alloted' => 0, 'Unverified' => 0],
            'accounts' => []
        ];
    }
    
    // Increment Stats
    $status = trim($row['statusName']);
    if (isset($ledger[$scrip]['stats'][$status])) {
        $ledger[$scrip]['stats'][$status]++;
    }
    
    // Add Account
    $ledger[$scrip]['accounts'][] = $row;
}

// Ensure the newest updated companies are at the top
uasort($ledger, function($a, $b) {
    return strtotime($b['last_updated']) <=> strtotime($a['last_updated']);
});

// 2. RENDER HEADER
echo sudo_get_header("ipo-result");
?>

<div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Corporate Ledger</h1>
        <p class="text-slate-400 text-sm mt-1">Real-time synchronized database state of all IPO applications.</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="dashboard.php" class="bg-slate-800 hover:bg-slate-700 text-slate-200 px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider transition-all border border-slate-700 flex items-center gap-2">
            <i class="fas fa-arrow-left opacity-50"></i> Dashboard
        </a>
    </div>
</div>

<div class="space-y-4 mb-10">
    <?php foreach ($ledger as $scrip => $data): ?>
        <div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden group shadow-sm">
            
            <button class="w-full text-left bg-slate-800/30 hover:bg-slate-800/60 px-6 py-4 flex items-center justify-between transition-colors toggle-btn" data-target="content-<?= $scrip ?>">
                <div class="flex items-center gap-4">
                    <span class="bg-slate-900 border border-slate-700 text-slate-300 text-xs font-mono font-black px-3 py-1.5 rounded-lg shadow-inner">
                        <?= htmlspecialchars($scrip) ?>
                    </span>
                    <div>
                        <h2 class="text-sm font-bold text-slate-200 truncate max-w-[200px] sm:max-w-md">
                            <?= htmlspecialchars($data['companyName']) ?>
                        </h2>
                        <p class="text-[10px] text-slate-500 font-mono mt-0.5">Last Sync: <?= date('M d, H:i', strtotime($data['last_updated'])) ?></p>
                    </div>
                </div>

                <div class="hidden md:flex items-center gap-2 font-mono text-[10px] font-bold">
                    <?php if($data['stats']['Alloted'] > 0): ?><span class="text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">ALLOT: <?= $data['stats']['Alloted'] ?></span><?php endif; ?>
                    <?php if($data['stats']['Rejected'] > 0): ?><span class="text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20">REJ: <?= $data['stats']['Rejected'] ?></span><?php endif; ?>
                    <?php if($data['stats']['Verified'] > 0): ?><span class="text-blue-400 bg-blue-500/10 px-2 py-0.5 rounded border border-blue-500/20">VER: <?= $data['stats']['Verified'] ?></span><?php endif; ?>
                    <i class="fas fa-chevron-down text-slate-600 ml-3 transform transition-transform duration-200 arrow-icon"></i>
                </div>
            </button>

            <div id="content-<?= $scrip ?>" class="hidden border-t border-slate-800">
                <div class="p-5 bg-slate-900/20">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($data['accounts'] as $ac): 
                            $status = trim($ac['statusName']);
                            $kitta = $ac['receivedKitta'] ?? 0;
                            
                            $badgeColor = 'text-slate-500 border-slate-800 bg-slate-900/50';
                            if ($status === 'Alloted') $badgeColor = 'text-emerald-400 border-emerald-500/30 bg-emerald-500/10 font-black shadow-[0_0_10px_rgba(16,185,129,0.1)]';
                            elseif ($status === 'Verified') $badgeColor = 'text-blue-400 border-blue-500/30 bg-blue-500/5';
                            elseif ($status === 'Rejected') $badgeColor = 'text-rose-400 border-rose-500/30 bg-rose-500/5';
                            elseif ($status === 'Unverified') $badgeColor = 'text-amber-400 border-amber-500/30 bg-amber-500/5 animate-pulse';
                        ?>
                            <div class="bg-[#161b22] border border-slate-800 rounded-xl p-3.5 flex justify-between items-center hover:border-slate-700 transition-colors shadow-sm">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-200"><?= htmlspecialchars($ac['name']) ?></span>
                                    <span class="text-[9px] font-mono text-slate-500 mt-0.5">ID: <?= substr($ac['dmat_num'], -8) ?></span>
                                </div>
                                <div class="text-[10px] font-mono px-2.5 py-1 rounded border <?= $badgeColor ?>">
                                    <?= htmlspecialchars($status) ?>
                                    <?= $status === 'Alloted' ? " ({$kitta})" : "" ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-btn');
        
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Get target ID
                const targetId = this.getAttribute('data-target');
                const targetContent = document.getElementById(targetId);
                const icon = this.querySelector('.arrow-icon');
                
                // Toggle hidden class (using jQuery slideToggle equivalent with Tailwind)
                if (targetContent.classList.contains('hidden')) {
                    targetContent.classList.remove('hidden');
                    icon.classList.add('rotate-180');
                } else {
                    targetContent.classList.add('hidden');
                    icon.classList.remove('rotate-180');
                }
            });
        });
    });
</script>

<?php include('footer.php'); ?>