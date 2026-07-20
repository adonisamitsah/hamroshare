<?php
require_once __DIR__ . '/config.php';
/** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

// ==========================================
// 1. ELECTRON / PORTABLE .ENV INITIALIZATION
// ==========================================
// Determine if we are running in AppImage (writable OS path) or local XAMPP (__DIR__)
$dataDir = getenv('APP_DATA_DIR') ?: __DIR__;
$envPath = $dataDir . '/.env';
$envExamplePath = __DIR__ . '/.env.example';

// If .env is missing, copy .env.example to the writable path
if (!file_exists($envPath)) {
    if (file_exists($envExamplePath)) {
        copy($envExamplePath, $envPath);
    } else {
        die("Critical Error: .env.example is missing from the application core.");
    }
}

// ==========================================
// 2. HELPER FUNCTIONS FOR .ENV MANAGEMENT
// ==========================================

/**
 * Parses the structured .env file into sections, extracting @info and active status.
 */
function parse_env_for_editor($filepath)
{
    if (!file_exists($filepath)) return false;

    $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $structured_env = [];
    $current_section = 'General Settings';
    $pending_info = [];

    foreach ($lines as $line) {
        $line = trim($line);

        // 1. Ignore global documentation headers
        if (preg_match('/^#\s*@docs:/i', $line)) continue;

        // 2. Match Section Headers: ### [ TITLE ] ###
        if (preg_match('/^###\s+\[\s*(.+?)\s*\]\s*###$/', $line, $matches)) {
            $current_section = $matches[1];
            if (!isset($structured_env[$current_section])) {
                $structured_env[$current_section] = [];
            }
            continue;
        }

        // 3. Match Info Text: # @info: text
        if (preg_match('/^#\s*@info:\s*(.+)$/i', $line, $matches)) {
            $pending_info[] = $matches[1];
            continue;
        }

        // 4. Match Disabled/Commented Variables: # KEY=VALUE
        if (preg_match('/^#\s*([A-Z0-9_]+)=(.*)$/i', $line, $matches)) {
            $structured_env[$current_section][] = [
                'key' => $matches[1],
                'value' => trim($matches[2], '"\' '),
                'active' => false,
                'info' => implode(' ', $pending_info)
            ];
            $pending_info = []; // Clear info for next variable
            continue;
        }

        // 5. Match Active Variables: KEY=VALUE
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/i', $line, $matches)) {
            $structured_env[$current_section][] = [
                'key' => $matches[1],
                'value' => trim($matches[2], '"\' '),
                'active' => true,
                'info' => implode(' ', $pending_info)
            ];
            $pending_info = []; // Clear info for next variable
        }
    }

    return $structured_env;
}

/**
 * Safely updates a variable in the .env file without destroying formatting,
 * sections, @info docs, or altering the commented/disabled state.
 */
function update_env_variable($filepath, $key, $value)
{
    $lines = file($filepath, FILE_IGNORE_NEW_LINES);
    $found = false;
    $keyRegex = preg_quote($key, '/');

    foreach ($lines as &$line) {
        $trimmed = trim($line);
        // Matches: KEY=val OR # KEY=val (preserves the prefix)
        if (preg_match('/^(#\s*)?(' . $keyRegex . ')=(.*)$/i', $trimmed, $matches)) {
            $prefix = $matches[1]; // captures "# " if it was commented out, or "" if active
            $line = $prefix . $key . '="' . $value . '"';
            $found = true;
            break;
        }
    }

    // If the key wasn't in the .env file at all, append it safely to the bottom
    if (!$found) {
        $lines[] = $key . '="' . $value . '"';
    }

    return file_put_contents($filepath, implode("\n", $lines) . "\n");
}

// ==========================================
// 3. AJAX HANDLERS
// ==========================================
if (isset($_POST['update_constant'])) {
    $key = $_POST['key'];
    $value = $_POST['value'];

    // --- PASSWORD HASHING BYPASS ---
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

if (isset($_POST['update_env'])) {
    $key = trim($_POST['key']);
    $value = trim($_POST['value']);

    if (update_env_variable($envPath, $key, $value)) {
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
    exit;
}

echo sudo_get_header('profile');

$visibleKeys = ['admin_name', 'admin_email', 'master_password'];

// Parse current .env for rendering in UI
$envData = parse_env_for_editor($envPath);
$envError = ($envData === false);
?>

<div class="max-w-4xl mx-auto pb-20">
    <div class="mb-8 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-white">System Configuration</h2>
            <p class="text-slate-500 text-sm mt-1">Manage core application identity, database, and environment variables.</p>
        </div>
        <div class="w-16 h-16 bg-blue-600/20 rounded-2xl border border-blue-500/30 flex items-center justify-center">
            <i class="fas fa-sliders-h text-blue-500 text-2xl"></i>
        </div>
    </div>

    <!-- DATABASE CREDENTIALS SECTION -->
    <div class="bg-slate-900/50 border border-slate-800 rounded-3xl overflow-hidden backdrop-blur-sm shadow-2xl mb-10">
        <div class="p-6 border-b border-slate-800 bg-slate-800/20 flex justify-between items-center">
            <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wider">Account Credentials</h3>
            <a href="dbBrowser.php" target="_blank" class="text-xs flex items-center gap-2 text-amber-500 hover:text-amber-400 transition-colors">
                <i class="fas fa-database"></i> Launch DB Browser
            </a>
        </div>
        <div class="p-0">
            <?php
            $query = $db->query("SELECT * FROM constant ORDER BY key ASC");
            $advancedRows = [];
            while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
                if (!in_array($row['key'], $visibleKeys)) {
                    $advancedRows[] = $row;
                    continue;
                }
                renderRow($row['key'], $row['value'], 'constant');
            }
            ?>
        </div>
    </div>

    <!-- DYNAMIC ENVIRONMENT VARIABLES SECTIONS -->
    <?php
    if ($envError) {
        echo '<div class="p-6 text-sm font-medium text-rose-400 bg-rose-500/10 border border-rose-900/20 rounded-2xl mb-8">';
        echo '<i class="fas fa-exclamation-triangle mr-2"></i> Could not parse the .env file. Please check for syntax errors or file permission issues on the server.';
        echo '</div>';
    } else {
        // Exclude structural variables you don't want users editing via UI
        $hiddenEnvKeys = ['ENVIRONMENT', 'DB_FILENAME'];

        foreach ($envData as $sectionName => $variables) {
            // Check if section only contains hidden keys, skip if true
            $visibleCount = 0;
            foreach ($variables as $var) {
                if (!in_array($var['key'], $hiddenEnvKeys)) $visibleCount++;
            }
            if ($visibleCount === 0) continue;
    ?>

            <div class="bg-slate-900/50 border border-slate-800 rounded-3xl overflow-hidden backdrop-blur-sm shadow-2xl mb-10">
                <div class="p-6 border-b border-slate-800 bg-slate-800/20 flex justify-between items-center">
                    <h3 class="text-sm font-semibold text-slate-300 uppercase tracking-wider"><?php echo htmlspecialchars($sectionName); ?></h3>
                    <span class="text-[10px] text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded border border-emerald-500/20 uppercase font-black tracking-widest">.ENV</span>
                </div>
                <div class="p-0">
                    <?php
                    foreach ($variables as $var) {
                        if (in_array($var['key'], $hiddenEnvKeys)) continue;
                        renderRow($var['key'], $var['value'], 'env', false, $var['info'], $var['active']);
                    }
                    ?>
                </div>
            </div>

    <?php
        }
    }
    ?>

    <!-- ADVANCED SQLITE CONSTANTS -->
    <div class="mt-10">
        <button onclick="$('#advanced-section').toggle(); $(this).find('i').toggleClass('rotate-180')"
            class="flex items-center gap-2 text-slate-500 hover:text-rose-400 transition-colors text-[10px] font-black uppercase tracking-[0.2em] px-2 group">
            <i class="fas fa-chevron-down transition-transform duration-300"></i>
            Advanced System Functions (Database)
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
                <?php foreach ($advancedRows as $row) {
                    renderRow($row['key'], $row['value'], 'constant', true);
                } ?>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Unified render function for rows.
 * Added $info and $isActive parameters for the .env parsing logic.
 */
function renderRow($key, $value, $type, $isAdvanced = false, $info = '', $isActive = true)
{
    $displayName = ucwords(strtolower(str_replace('_', ' ', $key)));
    $borderClass = $isAdvanced ? 'border-rose-900/10' : 'border-slate-800';
    // Hide inputs that contain tokens, secrets, or passwords
    $isSecret = (strpos(strtolower($key), 'password') !== false || strpos(strtolower($key), 'token') !== false || strpos(strtolower($key), 'secret') !== false);
?>
    <div class="group flex flex-col md:flex-row md:items-center justify-between p-6 border-b <?php echo $borderClass; ?> hover:bg-slate-800/30 transition-colors gap-4 <?php echo !$isActive ? 'opacity-60 grayscale-[50%]' : ''; ?>">
        <div class="md:w-5/12">
            <div class="flex items-center gap-2">
                <label class="text-sm font-medium text-slate-300 block"><?php echo $displayName; ?></label>
                <?php if (!$isActive): ?>
                    <span class="text-[8px] bg-slate-800 text-slate-400 px-1.5 py-0.5 rounded uppercase font-bold tracking-widest">Commented</span>
                <?php endif; ?>
            </div>
            <code class="text-[9px] text-slate-600 font-mono ml-0 uppercase tracking-tighter block mt-1"><?php echo $key; ?></code>

            <?php if (!empty($info)): ?>
                <p class="text-[11px] text-slate-400 mt-2 leading-relaxed border-l-2 border-blue-500/30 pl-2"><?php echo htmlspecialchars($info); ?></p>
            <?php endif; ?>
        </div>

        <div class="md:w-7/12 flex items-center gap-3">
            <div class="relative w-full">
                <input
                    type="<?php echo $isSecret ? 'password' : 'text'; ?>"
                    id="input-<?php echo $type; ?>-<?php echo $key; ?>"
                    class="bg-slate-950 border border-slate-800 text-slate-200 text-sm rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-transparent block w-full p-2.5 <?php echo $isSecret ? 'pr-10' : ''; ?> transition-all"
                    value="<?php echo htmlspecialchars($value); ?>">
                <?php if ($isSecret): ?>
                    <button type="button" onclick="toggleLocalPass('<?php echo $type; ?>-<?php echo $key; ?>')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300">
                        <i class="fas fa-eye text-xs" id="eye-<?php echo $type; ?>-<?php echo $key; ?>"></i>
                    </button>
                <?php endif; ?>
            </div>
            <button onclick="saveData('<?php echo $key; ?>', '<?php echo $type; ?>')" id="btn-<?php echo $type; ?>-<?php echo $key; ?>" class="bg-blue-600/90 hover:bg-blue-500 text-white p-2.5 rounded-xl transition-all active:scale-95 flex-shrink-0 shadow-lg">
                <i class="fas fa-save w-4"></i>
            </button>
        </div>
    </div>
<?php
}
?>
<?php include('footer.php'); ?>
<script>
    function toggleLocalPass(inputId) {
        const input = $(`#input-${inputId}`);
        const eye = $(`#eye-${inputId}`);
        const type = input.attr('type') === 'password' ? 'text' : 'password';
        input.attr('type', type);
        eye.toggleClass('fa-eye fa-eye-slash');
    }

    function saveData(key, type) {
        const value = $(`#input-${type}-${key}`).val();
        const btn = $(`#btn-${type}-${key}`);
        const originalIcon = btn.html();

        btn.html('<i class="fas fa-circle-notch animate-spin w-4"></i>').prop('disabled', true);

        // Determine the POST payload based on whether we are saving an ENV variable or a SQLite Constant
        const payload = {
            key: key,
            value: value
        };
        if (type === 'env') {
            payload.update_env = true;
        } else {
            payload.update_constant = true;
        }

        $.ajax({
            url: 'profile.php',
            method: 'POST',
            data: payload,
            success: function(response) {
                const res = JSON.parse(response);
                if (res.status === 'success') {
                    btn.removeClass('bg-blue-600/90').addClass('bg-emerald-600').html('<i class="fas fa-check w-4"></i>');
                    showSentinelModal("Success", `Configuration for <b>${key}</b> has been updated successfully.`, "success");
                    setTimeout(() => {
                        btn.removeClass('bg-emerald-600').addClass('bg-blue-600/90').html(originalIcon).prop('disabled', false);
                    }, 2000);
                } else {
                    showSentinelModal("Error", "Failed to update configuration. Check permissions.", "error");
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