<?php
// view_log.php - Secure Terminal Viewer
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/php_function.php'; // Includes getSafeLogOutput()

// 1. Strict Whitelist of Allowed Files
$allowed_logs = [
    'cron'  => 'cron_debug.log',
    'error' => 'error_log'
];

// 2. Validate the request securely
$requested_log = $_GET['log'] ?? '';

if (!array_key_exists($requested_log, $allowed_logs)) {
    http_response_code(403);
    die("Error 403: Forbidden or invalid log requested.");
}

$file_name = $allowed_logs[$requested_log];
$file_path = __DIR__ . '/' . $file_name;

// 3. Fetch the reversed, safe log output (fetching 1000 lines for the full view)
$logContent = getSafeLogOutput($file_path, 1000);

// Determine dynamic accent colors based on log type
$accentColor = ($requested_log === 'error') ? 'text-rose-500' : 'text-emerald-400';
$iconColor   = ($requested_log === 'error') ? 'text-rose-500' : 'text-blue-500';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Log: <?= htmlspecialchars($file_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0d1117; color: #c9d1d9; }
        
        /* Custom scrollbar to match the terminal aesthetic */
        ::-webkit-scrollbar { width: 10px; height: 10px; }
        ::-webkit-scrollbar-track { background: #0d1117; }
        ::-webkit-scrollbar-thumb { background: #30363d; border-radius: 5px; }
        ::-webkit-scrollbar-thumb:hover { background: #484f58; }
    </style>
</head>
<body class="antialiased min-h-screen p-4 md:p-8 font-sans selection:bg-blue-500/30 flex justify-center items-center">

    <div class="w-full max-w-7xl flex flex-col h-[calc(100vh-4rem)]">
        
        <div class="bg-[#161b22] border border-slate-800 rounded-t-2xl p-4 flex justify-between items-center shadow-md z-10">
            <div class="flex items-center gap-3">
                <div class="w-3.5 h-3.5 rounded-full bg-rose-500 shadow-inner"></div>
                <div class="w-3.5 h-3.5 rounded-full bg-amber-500 shadow-inner"></div>
                <div class="w-3.5 h-3.5 rounded-full bg-emerald-500 shadow-inner"></div>
                
                <h1 class="ml-4 text-xs md:text-sm font-bold text-slate-300 font-mono tracking-wider flex items-center gap-2 select-none">
                    <i class="fas fa-terminal <?= $iconColor ?>"></i> ~/var/log/<?= htmlspecialchars($file_name) ?>
                </h1>
            </div>
            
            <div class="flex items-center gap-4">
                <span class="hidden sm:inline-block text-[10px] text-slate-500 font-mono font-bold uppercase tracking-widest border border-slate-700/50 px-2.5 py-1 rounded-md bg-slate-900 shadow-inner">
                    Top 1000 Lines (Newest First)
                </span>
                <button onclick="window.close()" class="text-slate-500 hover:text-rose-500 transition-colors" title="Close Window">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <div class="bg-[#050505] border-x border-b border-slate-800 rounded-b-2xl p-6 overflow-y-auto flex-1 shadow-[0_0_50px_rgba(0,0,0,0.5)]">
            <div class="font-mono text-[11px] md:text-sm <?= $accentColor ?> whitespace-pre-wrap leading-relaxed selection:bg-white/20">
                <?= $logContent ?>
            </div>
        </div>
        
    </div>

</body>
</html>