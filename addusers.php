<?php
require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

echo sudo_get_header("addusers");
?>

<div class="mb-8">
    <h1 class="text-2xl font-bold text-white tracking-tight">User Management</h1>
    <p class="text-slate-400 text-sm mt-1">Enroll new Meroshare credentials into the local database for bulk automation.</p>
</div>

<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-6 mb-8 shadow-sm">
    <h3 class="text-xs font-bold text-blue-500 uppercase tracking-[0.2em] mb-6">Create New Profile</h3>

    <form id="addUserForm" method="post" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Account Holder Name</label>
                <input type="text" placeholder="e.g. John Doe" name="name" required
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all placeholder:text-slate-600" />
            </div>

            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">Depository Participant (DP)</label>
                <div class="flex gap-2">
                    <div class="relative flex-1 custom-select-wrapper" id="dpSelect">
                        <div class="selected-display bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 cursor-pointer flex justify-between items-center transition-all hover:border-slate-500 hover:bg-slate-800/50">
                            <span class="current-value text-sm truncate">Choose DP</span>
                            <i class="fas fa-chevron-down text-[10px] text-slate-500"></i>
                        </div>

                        <input type="hidden" name="clientId" id="clientIdInput" required>

                        <div class="options-list hidden absolute z-50 w-full mt-2 bg-[#0d1117] border border-slate-800 rounded-xl shadow-2xl max-h-72 overflow-y-auto custom-scrollbar">
                            <?php
                            $rawOptions = sudo_get_capital_as_options($db);

                            // Safely extract options
                            if (preg_match_all('/<option value="(.*?)">(.*?)<\/option>/', $rawOptions, $matches)) {
                                for ($i = 0; $i < count($matches[0]); $i++) {
                                    $val = htmlspecialchars($matches[1][$i], ENT_QUOTES, 'UTF-8');
                                    $text = htmlspecialchars($matches[2][$i], ENT_QUOTES, 'UTF-8');

                                    if ($val == "") continue;

                                    echo '
                                        <div class="option-item px-4 py-3 border-b border-slate-800/40 hover:bg-blue-600/10 cursor-pointer transition-colors group" data-value="' . $val . '">
                                            <div class="text-[11px] font-bold text-slate-200 group-hover:text-blue-400 leading-snug break-words">
                                                ' . $text . '
                                            </div>
                                        </div>';
                                }
                            } else {
                                echo '<div class="px-4 py-3 text-xs text-rose-400">Failed to load DP List.</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <a href="json-api.php?type=updateCapitalAsOptions"
                        class="bg-slate-800 hover:bg-slate-700 text-blue-400 p-3 rounded-xl transition-all flex items-center justify-center border border-slate-700 shrink-0"
                        title="Sync DP List">
                        <i class="fas fa-sync"></i>
                    </a>
                </div>
            </div>

            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">16-Digit Demat Number</label>
                <input type="text" placeholder="0000000000000000" name="dmat_num" pattern="\d{16}" required
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all font-mono" />
            </div>

            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">MeroShare Password</label>
                <input type="text" placeholder="••••••••" name="password" required
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all" />
            </div>

            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">CRN Number</label>
                <input type="text" placeholder="C-0000000" name="crnNumber" required
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all font-mono" />
            </div>

            <div class="space-y-1">
                <label class="text-[11px] text-slate-500 font-semibold uppercase ml-1">4-Digit PIN</label>
                <input type="text" placeholder="0000" name="transactionPIN" pattern="\d{4}" required
                    class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-2.5 text-slate-200 focus:ring-2 focus:ring-blue-600/50 outline-none transition-all font-mono" />
            </div>
        </div>

        <div class="pt-4 border-t border-slate-800 flex justify-end">
            <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-900/20 transition-all active:scale-95">
                Register User
            </button>
        </div>
    </form>
</div>

<div class="mb-4">
    <div class="relative max-w-sm">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
            <i class="fas fa-search text-xs"></i>
        </span>
        <input type="text" id="table_search" placeholder="Quick search users..."
            class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-300 focus:outline-none focus:border-slate-600 transition-all" />
    </div>
</div>

<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse" id="usersTable">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">SN</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Name & DP</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Demat</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Secured Data (PWD/CRN/PIN)</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Manage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                <?php
                $sn = 1;
                // Added ORDER BY id DESC so the newest users appear at the top automatically
                $query1 = "SELECT * FROM users ORDER BY id DESC;";
                $result = $db->query($query1);

                while ($row = $result->fetchArray(SQLITE3_ASSOC)):
                    // Sanitize outputs to prevent XSS and broken Javascript string limits
                    $dmat = htmlspecialchars($row['dmat_num'], ENT_QUOTES, 'UTF-8');
                    $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');
                    $dpName = htmlspecialchars($row['dpName'] ?? 'Unknown DP', ENT_QUOTES, 'UTF-8');
                    $pwd = htmlspecialchars($row['password'], ENT_QUOTES, 'UTF-8');
                    $crn = htmlspecialchars($row['crnNumber'], ENT_QUOTES, 'UTF-8');
                    $pin = htmlspecialchars($row['transactionPIN'], ENT_QUOTES, 'UTF-8');
                    $id = htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8');
                    $isActive = (int)$row['is_active'];

                ?>
                    <tr class="hover:bg-slate-800/20 transition-colors group <?= $isActive ? '' : 'opacity-50' ?>">
                        <td class="px-6 py-4 text-xs text-slate-600 font-mono"><?= sprintf("%02d", $sn) ?></td>
                        <td class="px-6 py-4">
                            <a href="user.php?dmat=<?= $dmat ?>" class="group block">
                                <div class="flex flex-col">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full <?= $isActive ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]' : 'bg-rose-500 shadow-[0_0_8px_rgba(244,63,94,0.8)]' ?>"></span>
                                        <span class="text-sm font-semibold text-slate-200 group-hover:text-blue-400 transition-colors">
                                            <?= $name ?>
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-blue-500 font-mono uppercase tracking-tighter flex items-center gap-1 pl-3.5">
                                        <?= $dpName ?>
                                        <i class="fas fa-arrow-right opacity-0 -translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all text-[8px]"></i>
                                    </span>
                                </div>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-400 font-mono"><?= $dmat ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-4">
                                <div class="bg-slate-900 px-3 py-1.5 rounded-lg border border-slate-800 flex items-center gap-3">
                                    <span class="text-[11px] font-mono text-slate-400 hidetext_" id="password_<?= $dmat ?>"><?= $pwd ?></span>
                                    <span class="text-slate-600">|</span>
                                    <span class="text-[11px] font-mono text-slate-400 hidetext_" id="crnNumber_<?= $dmat ?>"><?= $crn ?></span>
                                    <span class="text-slate-600">|</span>
                                    <span class="text-[11px] font-mono text-slate-400 hidetext_" id="transactionPIN_<?= $dmat ?>"><?= $pin ?></span>
                                </div>
                                <button class="text-slate-500 hover:text-white transition-colors cursor-pointer" onclick="eye('<?= $dmat ?>')">
                                    <i id="eye_<?= $dmat ?>" class="fas fa-eye text-xs"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <button onclick="openEditModal('<?= $id ?>', '<?= addslashes($name) ?>', '<?= addslashes($pwd) ?>', '<?= addslashes($crn) ?>', '<?= addslashes($pin) ?>', '<?= addslashes($isActive) ?>')"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:bg-blue-500/10 hover:text-blue-500 transition-all border border-transparent hover:border-blue-500/20 cursor-pointer">
                                    <i class="fas fa-edit text-xs"></i>
                                </button>
                                <button onclick="confirmDelete('<?= $id ?>', '<?= addslashes($name) ?>')"
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:bg-red-500/10 hover:text-red-500 transition-all border border-transparent hover:border-red-500/20 cursor-pointer">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php
                    $sn++;
                endwhile;
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include('footer.php'); ?>

<script>
    // 1. Implemented Live Table Search (The input was there, but no code backed it up!)
    $("#table_search").on("keyup", function() {
        let value = $(this).val().toLowerCase();
        $("#usersTable tbody tr").filter(function() {
            // Toggle the row based on whether the text matches the search input
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
        });
    });

    // 2. Optimized Form Submission
    $("#addUserForm").on("submit", function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');

        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Encrypting...');

        $.ajax({
            type: "GET",
            url: "json-api.php",
            data: form.serialize() + "&action=add_user",
            dataType: "json",
            success: function(response) {
                if (response.status === 'success') {
                    showSentinelModal("Successful", response.message, "success");
                    form[0].reset();
                    $('#dpSelect .current-value').text('Choose DP').addClass('text-slate-500').removeClass('text-slate-100');

                    // Optional: Reload the page after 1.5 seconds so the new user appears in the table
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showSentinelModal("Access Denied", response.message, "error");
                }
            },
            error: function(xhr) {
                showSentinelModal("Protocol Error", "The API did not respond correctly. Status: " + xhr.status, "error");
            },
            complete: function() {
                submitBtn.prop('disabled', false).text('Register User');
            }
        });
    });

    // 3. Delete Modal Logic
    let pendingDeleteId = null;

    function confirmDelete(id, name) {
        pendingDeleteId = id;

        const modalHtml = `
        <div id="sentinel-modal" class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div class="bg-[#161b22] border border-rose-500/30 w-full max-w-sm rounded-2xl shadow-2xl p-6 border-t-4 border-t-rose-600">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center">
                        <i class="fas fa-user-slash text-rose-500 text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-white font-bold tracking-tight">Confirm Deletion</h3>
                        <p class="text-rose-500/70 text-[10px] uppercase tracking-widest font-black">Security Protocol</p>
                    </div>
                </div>
                
                <div class="text-slate-300 text-sm leading-relaxed mb-6">
                    You are about to permanently remove <b>${name}</b> from the Meroshare Users. This action will purge all encrypted DMAT credentials and cannot be undone.
                </div>

                <div class="flex gap-3">
                    <button onclick="$('#sentinel-modal').remove(); pendingDeleteId = null;" 
                            class="flex-1 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold uppercase tracking-widest rounded-xl transition-all">
                        Cancel
                    </button>
                    <button onclick="executeDeletion()" 
                            class="flex-1 py-3 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-rose-600/20">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    `;
        $('body').append(modalHtml);
    }

    function executeDeletion() {
        if (pendingDeleteId) {
            window.location.href = `delete-user.php?id=${pendingDeleteId}`;
        }
    }

    // 4. Custom Select Logic
    $(document).ready(function() {
        const dpSelect = $('#dpSelect');
        const list = dpSelect.find('.options-list');
        const display = dpSelect.find('.selected-display');
        const input = $('#clientIdInput');
        const displayLabel = dpSelect.find('.current-value');

        // Open/Close
        display.on('click', function(e) {
            e.stopPropagation();
            list.toggleClass('hidden');
            dpSelect.toggleClass('z-[100]');
        });

        // Select Item
        dpSelect.on('click', '.option-item', function() {
            const val = $(this).data('value');
            const text = $(this).find('div').first().text().trim();

            input.val(val);
            displayLabel.text(text).removeClass('text-slate-500').addClass('text-slate-100');
            list.addClass('hidden');
        });

        // Close on click outside
        $(document).on('click', function() {
            list.addClass('hidden');
        });
    });
</script>