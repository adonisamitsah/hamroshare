<?php 
// error_reporting(1);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("backup-restore");
?>

<!-- Header & Critical Warning Section -->
<div class="mb-8 flex flex-col lg:flex-row gap-6 items-start">
    <div class="flex-1">
        <div class="flex items-center gap-3 mb-2">
            <div class="p-2 bg-amber-500/10 border border-amber-500/20 rounded-lg text-amber-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">System Backup & Recovery</h1>
        </div>
        <p class="text-slate-400 text-sm max-w-2xl leading-relaxed">
            Manage your database snapshots. <span class="text-amber-400 font-semibold underline decoration-amber-500/30">Warning:</span> Restore and Delete actions are immediate and cannot be undone. Always verify your backup file before uploading.
        </p>
    </div>

    <button id="create_backup" onclick="create_backup();" 
            class="group bg-blue-600 hover:bg-blue-500 text-white px-8 py-3 rounded-xl font-bold text-sm tracking-widest uppercase transition-all shadow-lg shadow-blue-900/20 flex items-center gap-3 active:scale-95">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:rotate-180 transition-transform duration-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
        Generate New Backup
    </button>
</div>

<!-- Upload Zone -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-8 mb-8 relative overflow-hidden group">
    <!-- Background SVG decoration -->
    <svg class="absolute -right-12 -bottom-12 w-48 h-48 text-slate-800/20 pointer-events-none" fill="currentColor" viewBox="0 0 24 24">
        <path d="M12 15V3m0 12l-4-4m4 4l4-4M2 17l.621 2.485A2 2 0 004.561 21h14.878a2 2 0 001.94-1.515L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>

    <form id="msbak_upload_form" class="relative z-10">
        <div class="flex flex-col items-center justify-center border-2 border-dashed border-slate-700 rounded-xl p-8 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all">
            <div class="w-12 h-12 bg-slate-800 rounded-full flex items-center justify-center mb-4 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
            </div>
            <p class="text-sm text-slate-300 font-medium mb-1">Upload valid <span class="text-blue-400 font-mono">.msbak</span> file</p>
            <p class="text-[11px] text-slate-500 mb-6 uppercase tracking-widest">Maximum file size: 50MB</p>
            
            <div class="flex w-full max-w-md gap-2">
                <input id="msbak_file" name="msbak_file" type="file" accept=".msbak" 
                       class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-300 hover:file:bg-slate-700 cursor-pointer" />
                <button id="msbak_upload_btn" type="button" 
                        class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-2 rounded-lg text-xs font-bold transition-all shadow-md active:scale-95">
                    Upload
                </button>
            </div>
            
            <div id="progress_report" class="mt-4 w-full max-w-md text-center text-xs font-mono text-blue-400 animate-pulse"></div>
        </div>
    </form>
</div>

<!-- Search Bar -->
<div class="mb-4">
    <div class="relative max-w-sm">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-600">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </span>
        <input type="text" id="table_search" placeholder="Search backups..." 
               class="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-400 focus:outline-none focus:border-slate-600 transition-all"/>
    </div>
</div>

<!-- Backups Table -->
<div class="bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-800/30 border-b border-slate-800">
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">Backup Files</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Created Timestamp</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Action</th>
                    <th class="px-6 py-4 text-[10px] font-bold text-slate-500 uppercase tracking-widest">System Log</th>
                </tr>
            </thead>
            <tbody id="backup_table_body" class="divide-y divide-slate-800 text-[13px]">
                <!-- Injected via backup_table() JavaScript -->
            </tbody>
        </table>
    </div>
</div>

<?php include('footer.php'); ?>
<script type="text/javascript">
    backup_table();
</script>