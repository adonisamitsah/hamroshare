<?php
// cr_display_apply_ipo_right.php

require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
require_once __DIR__ . '/class_report_viewer.php';

class ScannerReport extends BaseReportViewer {
    
    // 1. Define the report's identity
    protected string $prefix = 'scanner_report_';
    protected string $themeColor = 'indigo';
    protected string $pageTitle = 'Share Auto-Apply';

    // 2. Define the Main Content
    protected function renderMainContent(): void {
        $processing_report = $this->payload['processing_report'] ?? [];
        
        // --- UI/UX Stats Analytics Parsing ---
        $statSuccess = 0;
        $statSkipped = 0;
        $statFailed = 0;

        foreach ($processing_report as $line) {
            $clean = strip_tags($line);
            if (strpos($clean, '✅') !== false || strpos($clean, '🟣') !== false) {
                $statSuccess++;
            } elseif (strpos($clean, '⏭️') !== false) {
                $statSkipped++;
            } elseif (strpos($clean, '❌') !== false || strpos($clean, '⚠️') !== false) {
                $statFailed++;
            }
        }
        ?>
        
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5 lg:p-8 shadow-xl relative overflow-hidden">
            <div class="absolute -bottom-24 -right-10 w-64 h-64 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 relative z-10">
                <div>
                    <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight text-white mb-2">
                        Auto-Apply Sequence
                    </h1>
                    <p class="text-xs lg:text-sm text-gray-400 font-mono flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                        Engine Run Time: <span class="text-gray-300 font-bold"><?= htmlspecialchars($this->executionTime) ?></span>
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 lg:gap-4 w-full md:w-auto">
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[80px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-emerald-500 mb-1">Applied</span>
                        <span class="text-xl font-black text-emerald-400 font-mono"><?= $statSuccess ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[80px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-amber-500 mb-1">Skipped</span>
                        <span class="text-xl font-black text-amber-400 font-mono"><?= $statSkipped ?></span>
                    </div>
                    <div class="bg-gray-950 border border-gray-800 p-3 rounded-xl text-center min-w-[80px]">
                        <span class="block text-[9px] uppercase font-bold tracking-widest text-red-500 mb-1">Failed</span>
                        <span class="text-xl font-black text-red-400 font-mono"><?= $statFailed ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-[#0f1219] border border-gray-800 rounded-2xl shadow-xl overflow-hidden flex flex-col">
            
            <div class="bg-gray-900/80 px-4 py-2 border-b border-gray-800 flex items-center gap-2">
                <div class="flex gap-1.5">
                    <div class="w-3 h-3 rounded-full bg-red-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                    <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                </div>
                <span class="ml-2 text-[10px] font-mono text-gray-500 uppercase tracking-widest">stdout / processor_log</span>
            </div>

            <?php if (empty($processing_report)): ?>
                <div class="p-12 text-center flex flex-col items-center justify-center h-48">
                    <p class="text-gray-500 font-mono text-sm">~ $ No execution directives logged in this sequence.</p>
                </div>
            <?php else: ?>
                <div class="p-2 sm:p-4 space-y-1 sm:space-y-2 overflow-x-auto">
                    <?php foreach ($processing_report as $index => $line): 
                        $cleanLine = strip_tags($line);
                        
                        $badgeStyle = "bg-gray-800 text-gray-400 border-gray-700";
                        $statusLabel = "LOGGED";
                        $iconColor = "text-gray-500";
                        
                        // 1. Determine Status Attributes
                        if (strpos($cleanLine, '✅') !== false) {
                            $badgeStyle = "bg-emerald-500/10 text-emerald-400 border-emerald-500/20";
                            $statusLabel = "SUCCESS";
                            $iconColor = "text-emerald-500";
                        } elseif (strpos($cleanLine, '🟣') !== false) {
                            $badgeStyle = "bg-purple-500/10 text-purple-400 border-purple-500/20";
                            $statusLabel = "APPLIED";
                            $iconColor = "text-purple-500";
                        } elseif (strpos($cleanLine, '❌') !== false) {
                            $badgeStyle = "bg-red-500/10 text-red-400 border-red-500/20";
                            $statusLabel = "FAILED";
                            $iconColor = "text-red-500";
                        } elseif (strpos($cleanLine, '⏭️') !== false) {
                            $badgeStyle = "bg-amber-500/10 text-amber-400 border-amber-500/20";
                            $statusLabel = "SKIPPED";
                            $iconColor = "text-amber-500";
                        } elseif (strpos($cleanLine, '⚠️') !== false) {
                            $badgeStyle = "bg-blue-500/10 text-blue-400 border-blue-500/20";
                            $statusLabel = "RE-APPLY";
                            $iconColor = "text-blue-500";
                        }
                        
                        // 2. Deep Clean: Remove Emojis AND invisible Unicode variation selectors (U+FE0F)
                        $sanitized = preg_replace('/^[^a-zA-Z0-9\*]+/', '', $cleanLine);
                        
                        // 3. Parse string into Name, Action, and Details
                        $parsedName = '';
                        $parsedAction = $sanitized;
                        $parsedDetails = '';

                        // Matches format: "**Name**: Action Text (Details)"
                        if (preg_match('/\*\*(.*?)\*\*:?\s*([^\(]+)(?:\((.*?)\))?$/', $sanitized, $matches)) {
                            $parsedName = trim($matches[1]);
                            $parsedAction = trim($matches[2]);
                            $parsedDetails = isset($matches[3]) ? trim($matches[3]) : '';
                        }
                    ?>
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-2 sm:gap-4 p-2.5 sm:p-3 rounded-lg hover:bg-gray-800/30 transition-colors border border-transparent hover:border-gray-800/50 group">
                            
                            <div class="flex items-start gap-3 w-full min-w-0">
                                <span class="font-mono text-[10px] text-gray-600 pt-0.5 select-none w-5 text-right shrink-0">
                                    <?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?>
                                </span>
                                
                                <div class="flex-1 min-w-0">
                                    <div class="font-mono text-xs sm:text-[13px] leading-relaxed text-gray-300 break-words group-hover:text-gray-100 transition-colors flex flex-wrap items-center gap-x-2">
                                        <span class="<?= $iconColor ?> font-bold shrink-0">❯</span> 
                                        
                                        <?php if ($parsedName): ?>
                                            <span class="text-gray-100 font-bold tracking-tight"><?= htmlspecialchars($parsedName) ?></span>
                                            <span class="text-gray-600">—</span>
                                        <?php endif; ?>
                                        
                                        <span class="text-gray-400"><?= htmlspecialchars($parsedAction) ?></span>
                                    </div>

                                    <?php if ($parsedDetails): ?>
                                        <div class="mt-2 pl-3">
                                            <span class="inline-flex items-center gap-1.5 text-[10px] sm:text-xs text-gray-400 bg-gray-900/60 px-2.5 py-1 rounded-md border border-gray-800/80 font-mono shadow-inner">
                                                <i class="fas fa-info-circle text-gray-500"></i>
                                                <?= htmlspecialchars($parsedDetails) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="pl-11 sm:pl-0 shrink-0 self-start mt-1 sm:mt-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-mono font-bold tracking-widest border <?= $badgeStyle ?>">
                                    <?= $statusLabel ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// 3. Instantiate and execute the class
$report = new ScannerReport($db, $_GET['token'] ?? '');
$report->render();