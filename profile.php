<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

if (isset($_POST['update_constant'])) {
    $key = $_POST['key'];
    $value = $_POST['value'];

    // --- PASSWORD HASHING BYPASS ---
    // If updating the master password, hash it before saving
    if ($key === 'master_password') {
        $value = password_hash($value, PASSWORD_DEFAULT);
    }
    
    $stmt = $db->prepare("UPDATE constant SET value = :val WHERE key = :key");
    $stmt->bindValue(':val', $value, SQLITE3_TEXT);
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

echo sudo_get_header('profile');

$visibleKeys = ['admin_name', 'admin_email', 'master_password'];
?>

<div class="max-w-4xl mx-auto pb-20">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">Master Profile</h2>
            <p class="text-slate-500 text-sm mt-1">Manage core application identity and credentials.</p>
        </div>
        <div class="w-16 h-16 bg-blue-600/20 rounded-2xl border border-blue-500/30 flex items-center justify-center">
            <i class="fas fa-user-shield text-blue-500 text-2xl"></i>
        </div>
    </div>
<div class="mb-8">
        <a href="dbBrowser.php" 
           target="_blank" 
           rel="noopener noreferrer"
           class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-600 rounded-lg text-sm font-medium transition-colors">
            <i class="fas fa-database text-amber-500"></i>
            Launch Database Browser
        </a>
        <p class="text-xs text-slate-600 mt-2 italic">
            * Warning: Use with caution. Direct database access enabled.
        </p>
    </div>
    <div class="bg-slate-900/50 border border-slate-800 rounded-3xl overflow-hidden backdrop-blur-sm shadow-2xl">
        <div class="p-6 border-b border-slate-800 bg-slate-800/20">
            <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Account Credentials</h3>
        </div>
        <div class="p-0">
            <?php
            $query = $db->query("SELECT * FROM constant ORDER BY key ASC");
            $advancedRows = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                if (!in_array($row['key'], $visibleKeys)) { $advancedRows[] = $row; continue; }
                renderConstantRow($row);
            }
            ?>
        </div>
    </div>

    <div class="mt-10">
        <button onclick="$('#advanced-section').toggle(); $(this).find('i').toggleClass('rotate-180')" 
                class="flex items-center gap-2 text-slate-500 hover:text-rose-400 transition-colors text-[10px] font-black uppercase tracking-[0.2em] px-2 group">
            <i class="fas fa-chevron-down transition-transform duration-300"></i>
            Advanced System Functions
        </button>

        <div id="advanced-section" class="hidden mt-6">
            <div class="p-4 bg-rose-500/5 border border-rose-500/20 rounded-2xl mb-6 flex items-start gap-4">
                <div class="p-2 bg-rose-500/10 rounded-lg"><i class="fas fa-biohazard text-rose-500 text-sm"></i></div>
                <div>
                    <h4 class="text-xs font-bold text-rose-400 uppercase tracking-wider">Danger Zone</h4>
                    <p class="text-[10px] text-slate-500 mt-1 leading-relaxed">Incorrect values here can lead to application downtime.</p>
                </div>
            </div>
            <div class="bg-slate-950/40 border border-rose-900/20 rounded-3xl overflow-hidden">
                <?php foreach ($advancedRows as $row) { renderConstantRow($row, true); } ?>
            </div>
        </div>
    </div>
</div>

<?php
function renderConstantRow($row, $isAdvanced = false) {
    $displayName = ucwords(str_replace('_', ' ', $row['key']));
    $borderClass = $isAdvanced ? 'border-rose-900/10' : 'border-slate-800';
    $isPass = ($row['key'] == 'master_password');
    ?>
    <div class="group flex flex-col md:flex-row md:items-center justify-between p-6 border-b <?php echo $borderClass; ?> hover:bg-slate-800/30 transition-colors gap-4">
        <div class="md:w-1/3">
            <label class="text-sm font-medium text-slate-300 block"><?php echo $displayName; ?></label>
            <code class="text-[9px] text-slate-600 font-mono ml-0 uppercase tracking-tighter"><?php echo $row['key']; ?></code>
        </div>
        
        <div class="md:w-2/3 flex items-center gap-3">
            <div class="relative w-full">
                <input 
                    type="<?php echo $isPass ? 'password' : 'text'; ?>" 
                    id="input-<?php echo $row['key']; ?>"
                    class="bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-transparent block w-full p-2.5 <?php echo $isPass ? 'pr-10' : ''; ?> transition-all" 
                    value="<?php echo htmlspecialchars($row['value']); ?>"
                >
                <?php if($isPass): ?>
                <button type="button" onclick="toggleLocalPass('<?php echo $row['key']; ?>')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                    <i class="fas fa-eye text-xs" id="eye-<?php echo $row['key']; ?>"></i>
                </button>
                <?php endif; ?>
            </div>
            <button onclick="saveConstant('<?php echo $row['key']; ?>')" id="btn-<?php echo $row['key']; ?>" class="bg-blue-600/90 hover:bg-blue-500 text-white p-2.5 rounded-xl transition-all active:scale-95 flex-shrink-0 shadow-lg">
                <i class="fas fa-save w-4"></i>
            </button>
        </div>
    </div>
    <?php
}
?>
<?php include('footer.php'); ?>
<script>
// Eye Toggle Logic
function toggleLocalPass(key) {
    const input = $(`#input-${key}`);
    const eye = $(`#eye-${key}`);
    const type = input.attr('type') === 'password' ? 'text' : 'password';
    input.attr('type', type);
    eye.toggleClass('fa-eye fa-eye-slash');
}

function saveConstant(key) {
    const value = $(`#input-${key}`).val();
    const btn = $(`#btn-${key}`);
    const originalIcon = btn.html();

    btn.html('<i class="fas fa-circle-notch animate-spin w-4"></i>').prop('disabled', true);

    $.ajax({
        url: 'profile.php',
        method: 'POST',
        data: { update_constant: true, key: key, value: value },
        success: function(response) {
            const res = JSON.parse(response);
            if (res.status === 'success') {
                btn.removeClass('bg-blue-600/90').addClass('bg-emerald-600').html('<i class="fas fa-check w-4"></i>');
                showSentinelModal("Success", `Configuration for <b>${key}</b> has been updated successfully.`, "success");
                setTimeout(() => {
                    btn.removeClass('bg-emerald-600').addClass('bg-blue-600/90').html(originalIcon).prop('disabled', false);
                }, 2000);
            } else {
                showSentinelModal("Error", "Failed to update constant. Check database permissions.", "error");
                btn.html(originalIcon).prop('disabled', false);
            }
        },
        error: function() {
            showSentinelModal("Critical Error", "Could not connect to the server.", "error");
            btn.html(originalIcon).prop('disabled', false);
        }
    });
}
</script>