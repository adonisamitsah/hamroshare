<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

/**
 * Core Database Engine Class
 * Handles all backend logic to keep the UI clean
 */
class DatabaseEngine {
    private SQLite3 $db;

    public function __construct(SQLite3 $db) {
        $this->db = $db;
    }

    public function getTables(): array {
        $tables = [];
        $res = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC");
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) $tables[] = $row['name'];
        return $tables;
    }

    public function getSchema(string $table): array {
        $schema = ['columns' => [], 'pk' => 'rowid', 'hasPk' => false];
        $pragma = $this->db->query("PRAGMA table_info('$table')");
        while ($row = $pragma->fetchArray(SQLITE3_ASSOC)) {
            $schema['columns'][] = $row;
            if ($row['pk'] == 1) {
                $schema['pk'] = $row['name'];
                $schema['hasPk'] = true;
            }
        }
        return $schema;
    }

    public function processCrud(string $table, array $schema) {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return null;
        
        $action = $_POST['crud_action'] ?? '';
        $pkCol = $schema['pk'];
        $pkValue = $_POST['pk_value'] ?? null;

        try {
            if ($action === 'delete' && $pkValue) {
                $stmt = $this->db->prepare("DELETE FROM \"$table\" WHERE \"$pkCol\" = ?");
                $stmt->bindValue(1, $pkValue);
                $stmt->execute();
            } elseif (in_array($action, ['create', 'update'])) {
                $fields = []; $values = []; $placeholders = [];
                
                foreach ($schema['columns'] as $col) {
                    if ($col['name'] === $pkCol && ($action === 'update' || $col['type'] === 'INTEGER')) continue;
                    if (isset($_POST[$col['name']])) {
                        $fields[] = $col['name'];
                        $values[] = $_POST[$col['name']];
                        $placeholders[] = '?';
                    }
                }

                if ($action === 'create') {
                    $sql = "INSERT INTO \"$table\" (\"" . implode('", "', $fields) . "\") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $this->db->prepare($sql);
                    foreach ($values as $i => $val) $stmt->bindValue($i + 1, $val);
                } else {
                    $setSql = implode(' = ?, ', array_map(fn($f) => "\"$f\"", $fields)) . ' = ?';
                    $stmt = $this->db->prepare("UPDATE \"$table\" SET $setSql WHERE \"$pkCol\" = ?");
                    foreach ($values as $i => $val) $stmt->bindValue($i + 1, $val);
                    $stmt->bindValue(count($values) + 1, $pkValue);
                }
                $stmt->execute();
            }
            header("Location: ?table=" . urlencode($table) . "&msg=success");
            exit;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function fetchPaginatedData(string $table, array $schema, int $page, int $limit, string $search): array {
        $offset = ($page - 1) * $limit;
        $where = '';
        $searchConds = [];

        if ($search !== '') {
            foreach ($schema['columns'] as $col) {
                if (in_array(strtoupper($col['type']), ['TEXT', 'VARCHAR']) || empty($col['type'])) {
                    $searchConds[] = "\"" . $col['name'] . "\" LIKE :search";
                }
            }
            if ($searchConds) $where = " WHERE " . implode(" OR ", $searchConds);
        }

        // Count Total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM \"$table\" $where");
        if ($where) $countStmt->bindValue(':search', "%$search%", SQLITE3_TEXT);
        $total = $countStmt->execute()->fetchArray(SQLITE3_NUM)[0];

        // Fetch Data
        $select = $schema['hasPk'] ? "*" : "rowid, *";
        $stmt = $this->db->prepare("SELECT $select FROM \"$table\" $where ORDER BY \"{$schema['pk']}\" DESC LIMIT $limit OFFSET $offset");
        if ($where) $stmt->bindValue(':search', "%$search%", SQLITE3_TEXT);
        $res = $stmt->execute();
        
        $rows = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;

        return ['rows' => $rows, 'total' => $total, 'pages' => max(1, ceil($total / $limit)), 'offset' => $offset];
    }
}

// --- Controller Execution ---
$engine = new DatabaseEngine($db);
$tables = $engine->getTables();

$currentTable = in_array($_GET['table'] ?? '', $tables) ? $_GET['table'] : ($tables[0] ?? null);
$schema = $currentTable ? $engine->getSchema($currentTable) : null;
$errorMsg = $currentTable ? $engine->processCrud($currentTable, $schema) : null;

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$limit = 50;
$data = $currentTable ? $engine->fetchPaginatedData($currentTable, $schema, $page, $limit, $search) : ['rows'=>[], 'total'=>0, 'pages'=>1, 'offset'=>0];

// SVG Helper
function getIcon(string $name): string {
    $icons = [
        'db' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />',
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />',
        'alert' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />',
        'edit' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />',
        'trash' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />',
        'close' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />'
    ];
    return '<svg xmlns="http://www.w3.org/2000/svg" class="w-full h-full" fill="none" viewBox="0 0 24 24" stroke="currentColor">' . ($icons[$name] ?? '') . '</svg>';
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Explorer | HamroShare</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #0a0a0a; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #4b5563; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-[#050505] text-gray-300 min-h-screen p-4 md:p-8 font-sans antialiased selection:bg-indigo-500/30">

    <div class="max-w-[1600px] mx-auto space-y-6">
        
        <div class="flex flex-col xl:flex-row justify-between gap-6 bg-[#0f1115] p-5 rounded-2xl border border-gray-800 shadow-2xl">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 p-3 bg-indigo-500/10 border border-indigo-500/20 rounded-xl text-indigo-400"><?= getIcon('db') ?></div>
                <div>
                    <h1 class="text-xl md:text-2xl font-bold text-white tracking-tight">Database Explorer</h1>
                    <p class="text-xs text-gray-500 font-mono mt-0.5">SQLite Engine</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 w-full xl:w-auto">
                <form method="GET" class="relative flex items-center w-full sm:w-auto">
                    <input type="hidden" name="table" value="<?= htmlspecialchars($currentTable) ?>">
                    <div class="w-4 h-4 absolute left-3 text-gray-500"><?= getIcon('search') ?></div>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." 
                           class="w-full sm:w-64 bg-[#0a0a0a] border border-gray-800 text-white text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 pl-9 p-2.5 outline-none">
                </form>

                <select onchange="window.location.href='?table=' + this.value" class="w-full sm:w-auto bg-[#0a0a0a] border border-gray-800 text-gray-300 text-sm rounded-lg focus:ring-indigo-500 p-2.5 outline-none font-mono cursor-pointer">
                    <?php foreach ($tables as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>" <?= $t === $currentTable ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <button onclick="openModal('create')" class="bg-emerald-600/10 hover:bg-emerald-600 border border-emerald-600/30 text-emerald-500 hover:text-white font-bold py-2 px-5 rounded-lg transition-all flex items-center gap-2">
                    <div class="w-4 h-4"><?= getIcon('plus') ?></div> New Row
                </button>
            </div>
        </div>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div id="toast" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-xl flex items-center gap-3 transition-opacity duration-500">
                <div class="w-5 h-5"><?= getIcon('check') ?></div><span class="text-sm font-bold">Database synchronized successfully.</span>
            </div>
            <script>setTimeout(() => { document.getElementById('toast').style.opacity = '0'; setTimeout(() => document.getElementById('toast').remove(), 500); }, 3000);</script>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 px-4 py-3 rounded-xl flex items-center gap-3">
                <div class="w-5 h-5"><?= getIcon('alert') ?></div><span class="text-sm font-bold">Error: <?= htmlspecialchars($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($currentTable): ?>
        <div class="bg-[#0f1115] border border-gray-800 rounded-2xl overflow-hidden flex flex-col min-h-[500px]">
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead class="sticky top-0 z-10 bg-[#0f1115] shadow-sm shadow-black border-b border-gray-800">
                        <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                            <th class="px-5 py-4 w-20 text-center">Actions</th>
                            <?php foreach ($schema['columns'] as $col): ?>
                                <th class="px-5 py-4">
                                    <?= htmlspecialchars($col['name']) ?>
                                    <?php if ($col['name'] === $schema['pk']): ?><span class="ml-1 text-[9px] text-indigo-400 border border-indigo-500/30 px-1.5 py-0.5 rounded bg-indigo-500/10">PK</span><?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/50">
                        <?php if (empty($data['rows'])): ?>
                            <tr><td colspan="100%" class="px-5 py-12 text-center text-gray-500 text-sm font-mono bg-[#0a0a0a]">_No rows found_</td></tr>
                        <?php else: foreach ($data['rows'] as $row): 
                            // CRITICAL FIX: Encode JSON safely with HTML entities to prevent Control Character parsing bugs
                            $safeJson = htmlspecialchars(json_encode($row, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                        ?>
                            <tr class="hover:bg-gray-800/20 transition-colors group">
                                <td class="px-5 py-3 flex justify-center gap-2 opacity-50 group-hover:opacity-100 transition-opacity">
                                    <button data-row="<?= $safeJson ?>" onclick="openModal('update', this.dataset.row)" class="w-7 h-7 p-1.5 bg-blue-500/10 hover:bg-blue-500/30 text-blue-400 rounded"><?= getIcon('edit') ?></button>
                                    <form method="POST" onsubmit="return confirm('Delete this row forever?');" class="inline">
                                        <input type="hidden" name="crud_action" value="delete"><input type="hidden" name="pk_value" value="<?= htmlspecialchars($row[$schema['pk']]) ?>">
                                        <button type="submit" class="w-7 h-7 p-1.5 bg-rose-500/10 hover:bg-rose-500/30 text-rose-400 rounded"><?= getIcon('trash') ?></button>
                                    </form>
                                </td>
                                <?php foreach ($schema['columns'] as $col): $val = $row[$col['name']]; ?>
                                    <td class="px-5 py-3 text-xs <?= $col['name'] === $schema['pk'] ? 'font-mono text-gray-500' : 'text-gray-300' ?>">
                                        <?php if (is_null($val)): ?>
                                            <span class="text-gray-600 italic">NULL</span>
                                        <?php elseif (strlen((string)$val) > 50): ?>
                                            <div class="max-w-[250px] truncate hover:max-w-xl hover:overflow-x-auto hover:text-indigo-300 no-scrollbar" title="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($val) ?></div>
                                        <?php else: echo htmlspecialchars($val); endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($data['pages'] > 1): ?>
            <div class="p-4 bg-[#0a0a0a] flex items-center justify-between text-sm text-gray-400 border-t border-gray-800">
                <div>Showing <span class="font-mono text-white"><?= $data['offset'] + 1 ?></span> - <span class="font-mono text-white"><?= min($data['offset'] + $limit, $data['total']) ?></span> of <span class="font-mono text-white"><?= $data['total'] ?></span></div>
                <div class="flex gap-2">
                    <?php $q = "&search=" . urlencode($search); ?>
                    <?php if ($page > 1): ?><a href="?table=<?= urlencode($currentTable) ?>&page=<?= $page - 1 ?><?= $q ?>" class="px-3 py-1 bg-gray-800 rounded text-white font-mono">&larr;</a><?php endif; ?>
                    <span class="px-3 py-1">Page <?= $page ?> / <?= $data['pages'] ?></span>
                    <?php if ($page < $data['pages']): ?><a href="?table=<?= urlencode($currentTable) ?>&page=<?= $page + 1 ?><?= $q ?>" class="px-3 py-1 bg-gray-800 rounded text-white font-mono">&rarr;</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <div id="crudModal" class="fixed inset-0 z-50 hidden bg-black/90 backdrop-blur-md flex items-center justify-center p-4 transition-opacity">
        <div class="bg-[#0f1115] border border-gray-800 w-full max-w-3xl max-h-[90vh] flex flex-col rounded-2xl shadow-2xl">
            <div class="p-5 border-b border-gray-800 flex justify-between items-center bg-[#161b22] rounded-t-2xl">
                <h2 id="modalTitle" class="text-lg font-bold text-white tracking-tight">Record Editor</h2>
                <button onclick="closeModal()" class="w-8 h-8 p-1.5 text-gray-500 hover:text-white bg-gray-800 rounded-lg"><?= getIcon('close') ?></button>
            </div>
            
            <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
                <form id="crudForm" method="POST">
                    <input type="hidden" name="crud_action" id="crud_action"><input type="hidden" name="pk_value" id="pk_value">
                    <div class="grid gap-5">
                        <?php if ($currentTable) foreach ($schema['columns'] as $col): ?>
                            <div class="space-y-1.5 <?= $col['name'] === $schema['pk'] && $col['type'] === 'INTEGER' ? 'hidden' : '' ?>" id="field_group_<?= $col['name'] ?>">
                                <label class="text-[11px] font-bold text-indigo-400/80 uppercase tracking-widest block"><?= htmlspecialchars($col['name']) ?></label>
                                <?php if ($col['type'] === 'TEXT' || strpos($col['type'], 'VARCHAR') !== false): ?>
                                    <textarea name="<?= htmlspecialchars($col['name']) ?>" id="input_<?= htmlspecialchars($col['name']) ?>" rows="3" class="w-full bg-[#0a0a0a] border border-gray-800 rounded-xl p-3 text-sm font-mono text-gray-300 focus:border-indigo-500 outline-none"></textarea>
                                <?php else: ?>
                                    <input type="text" name="<?= htmlspecialchars($col['name']) ?>" id="input_<?= htmlspecialchars($col['name']) ?>" class="w-full bg-[#0a0a0a] border border-gray-800 rounded-xl p-3 text-sm font-mono text-gray-300 focus:border-indigo-500 outline-none" />
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
            
            <div class="p-5 border-t border-gray-800 bg-[#161b22] rounded-b-2xl flex justify-end gap-3">
                <button onclick="closeModal()" class="px-5 py-2.5 rounded-xl text-sm font-bold text-gray-400 hover:bg-gray-800 transition-colors">Cancel</button>
                <button type="submit" form="crudForm" id="modalSubmitBtn" class="px-6 py-2.5 rounded-xl text-sm font-bold text-white transition-all"></button>
            </div>
        </div>
    </div>

    <script>
        const pkCol = "<?= $currentTable ? $schema['pk'] : '' ?>";

        function openModal(action, rowDataJson = null) {
            document.getElementById('crudModal').classList.remove('hidden');
            document.getElementById('crud_action').value = action;
            const btn = document.getElementById('modalSubmitBtn');
            const form = document.getElementById('crudForm');

            if (action === 'create') {
                btn.innerText = 'Insert Record';
                btn.className = 'px-6 py-2.5 rounded-xl text-sm font-bold text-emerald-900 bg-emerald-500 hover:bg-emerald-400';
                form.reset(); document.getElementById('pk_value').value = '';
                let g = document.getElementById('field_group_' + pkCol); if(g) g.classList.add('hidden');
            } else {
                btn.innerText = 'Update Changes';
                btn.className = 'px-6 py-2.5 rounded-xl text-sm font-bold text-white bg-indigo-600 hover:bg-indigo-500';
                let g = document.getElementById('field_group_' + pkCol); if(g) g.classList.remove('hidden');

                // Parses safely because it was injected via data-row attribute
                const data = JSON.parse(rowDataJson);
                
                for (const [key, value] of Object.entries(data)) {
                    const input = document.getElementById('input_' + key);
                    if (input) {
                        let fVal = value !== null ? value : '';
                        
                        // Smart JSON format check
                        if (input.tagName === 'TEXTAREA' && typeof fVal === 'string' && (fVal.trim().startsWith('{') || fVal.trim().startsWith('['))) {
                            try {
                                fVal = JSON.stringify(JSON.parse(fVal), null, 4);
                                input.rows = Math.min(15, Math.max(5, fVal.split('\n').length));
                            } catch(e) {}
                        } else if (input.tagName === 'TEXTAREA') input.rows = 3;

                        input.value = fVal;
                        if (key === pkCol) { input.readOnly = true; input.classList.add('opacity-50', 'bg-gray-900'); }
                        else { input.readOnly = false; input.classList.remove('opacity-50', 'bg-gray-900'); }
                    }
                }
                document.getElementById('pk_value').value = data[pkCol];
            }
        }
        function closeModal() { document.getElementById('crudModal').classList.add('hidden'); }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
    </script>
</body>
</html>