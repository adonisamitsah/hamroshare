<?php

abstract class BaseReportViewer {
    
    protected $db;
    protected string $token;
    protected string $baseUrl;
    
    // Abstract properties that MUST be defined by the child class
    protected string $prefix = '';
    protected string $pageTitle = 'HamroShare Report';
    protected string $themeColor = 'emerald'; // e.g., emerald, indigo, amber, blue
    
    // Extracted Data
    protected array $payload = [];
    protected string $executionTime = 'Unknown';
    protected array $historyList = [];

    public function __construct($db, $token) {
        $this->db = $db;
        $this->token = trim($token);
        
        // Fetch Base URL from .env, fallback to relative root
        $this->baseUrl = $_ENV['BASE_URL'] ?? getenv('BASE_URL') ?: '/';
        
        $this->init();
    }

    /**
     * Bootstraps the entire data fetching and security process
     */
    private function init(): void {
        $this->validateTokenFormat();
        $this->fetchCurrentPayload();
        $this->fetchHistory();
    }

    private function validateTokenFormat(): void {
        if (empty($this->token) || !preg_match('/^[a-f0-9]{64}$/', $this->token)) {
            $this->renderErrorScreen("🚨 Access Denied", "Missing or Invalid Token Format.");
        }
    }

    private function fetchCurrentPayload(): void {
        $stmt = $this->db->prepare("SELECT value FROM constant WHERE key = :key LIMIT 1");
        $stmt->bindValue(':key', $this->prefix . $this->token, SQLITE3_TEXT);
        $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$res) {
            $this->renderErrorScreen("🔒 Token Expired", "This report link is invalid, expired, or has been revoked.");
        }

        $this->payload = json_decode($res['value'], true) ?: [];
        $this->executionTime = $this->payload['generated_at'] ?? 'Unknown Date';
    }

    private function fetchHistory(): void {
        $historyStmt = $this->db->prepare("SELECT key, value FROM constant WHERE key LIKE :prefix");
        $historyStmt->bindValue(':prefix', $this->prefix . '%', SQLITE3_TEXT);
        $results = $historyStmt->execute();
        
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $histPayload = json_decode($row['value'], true);
            $this->historyList[] = [
                'token' => str_replace($this->prefix, '', $row['key']),
                'time'  => $histPayload['generated_at'] ?? 'Unknown Date'
            ];
        }

        // Sort newest first
        usort($this->historyList, function($a, $b) {
            return strcmp($b['time'], $a['time']);
        });
    }

    /**
     * Forces the child class to implement its own specific main content
     */
    abstract protected function renderMainContent(): void;

    /**
     * Master Render Engine - Assembles the full HTML page
     */
    public function render(): void {
        $theme = $this->themeColor; // Syntactic sugar for HTML string interpolation
        ?>
        <!DOCTYPE html>
        <html lang="en" class="dark">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title><?= htmlspecialchars($this->pageTitle) ?></title>
            
            <link rel="icon" type="image/png" href="favicon.png">
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700;800&display=swap" rel="stylesheet">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    theme: {
                        extend: {
                            fontFamily: {
                                sans: ['Inter', 'sans-serif'],
                                mono: ['JetBrains Mono', 'monospace'],
                            },
                            colors: {
                                gray: { 850: '#1f2937', 900: '#111827', 950: '#030712' }
                            }
                        }
                    }
                }
            </script>
            <style>
                ::-webkit-scrollbar { width: 6px; height: 6px; }
                ::-webkit-scrollbar-track { background: transparent; }
                ::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
                ::-webkit-scrollbar-thumb:hover { background: #4b5563; }
                .glass-panel { background: rgba(17, 24, 39, 0.7); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
            </style>
        </head>
        <body class="bg-[#0a0a0a] text-gray-200 min-h-screen antialiased selection:bg-<?= $theme ?>-500 selection:text-black flex flex-col">

            <div class="lg:hidden sticky top-0 z-40 glass-panel border-b border-gray-800 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button onclick="toggleDrawer()" class="text-gray-300 hover:text-white focus:outline-none p-1 rounded-md hover:bg-gray-800 transition">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-lg font-bold text-white tracking-tight">HamroShare</h1>
                </div>
                <div class="flex items-center gap-2">
                    <a href="<?= htmlspecialchars($this->baseUrl) ?>" class="text-[10px] uppercase font-bold text-gray-400 bg-gray-800 hover:bg-gray-700 px-2 py-1 rounded transition">App</a>
                    <div class="text-[10px] font-mono text-<?= $theme ?>-400 font-bold bg-<?= $theme ?>-500/10 px-2 py-1 rounded border border-<?= $theme ?>-500/20">
                        <?= htmlspecialchars(date('H:i', strtotime($this->executionTime))) ?>
                    </div>
                </div>
            </div>

            <div class="flex-1 max-w-[1400px] w-full mx-auto px-4 py-6 lg:py-8 grid grid-cols-1 lg:grid-cols-12 gap-8 relative">
                
                <div id="drawer-overlay" onclick="toggleDrawer()" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-40 lg:hidden opacity-0 pointer-events-none transition-opacity duration-300"></div>

                <aside id="mobile-drawer" class="fixed inset-y-0 left-0 z-50 w-[280px] bg-gray-950 border-r border-gray-800 shadow-2xl transform -translate-x-full transition-transform duration-300 ease-out flex flex-col lg:relative lg:translate-x-0 lg:col-span-3 lg:bg-transparent lg:border-none lg:shadow-none lg:z-0 lg:w-full">
                    
                    <div class="p-5 border-b border-gray-800 lg:px-0 lg:pt-0 lg:pb-4 flex flex-col gap-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-xs font-mono font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-database"></i> History Vault
                            </h3>
                            <button onclick="toggleDrawer()" class="lg:hidden text-gray-400 hover:text-white p-1">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                        <a href="<?= htmlspecialchars($this->baseUrl) ?>" class="hidden lg:flex items-center justify-center gap-2 w-full py-2 bg-gray-900 hover:bg-gray-800 border border-gray-800 rounded-lg text-xs font-bold text-gray-300 transition-colors shadow-sm">
                            <i class="fas fa-arrow-left text-[10px]"></i> Back to Dashboard
                        </a>
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 lg:p-0 space-y-2 lg:pr-2 pb-20 lg:pb-0">
                        <?php if (empty($this->historyList)): ?>
                            <p class="text-xs text-gray-600 italic p-4 text-center bg-gray-900/50 rounded-xl border border-gray-800/50">No historical records found.</p>
                        <?php else: ?>
                            <?php foreach ($this->historyList as $item): ?>
                                <?php 
                                    $isActive = ($item['token'] === $this->token);
                                    $cardStyle = $isActive 
                                        ? "bg-gradient-to-br from-{$theme}-900/40 to-gray-900 border-{$theme}-500/50 ring-1 ring-{$theme}-500/20 shadow-[0_0_15px_var(--tw-shadow-color)] shadow-{$theme}-500/10" 
                                        : 'bg-gray-900/30 border-gray-800 hover:bg-gray-800/60 hover:border-gray-700 text-gray-400';
                                ?>
                                <a href="?token=<?= htmlspecialchars($item['token']) ?>" class="block w-full text-left p-3 rounded-xl border transition-all duration-200 <?= $cardStyle ?> group">
                                    <div class="flex justify-between items-start mb-1.5">
                                        <span class="text-xs font-mono <?= $isActive ? "text-{$theme}-400 font-bold" : 'text-gray-300 group-hover:text-gray-100' ?>">
                                            <?= htmlspecialchars($item['time']) ?>
                                        </span>
                                        <?php if ($isActive): ?>
                                            <span class="bg-<?= $theme ?>-500 text-black text-[9px] px-1.5 py-0.5 rounded font-black uppercase tracking-wider shadow-sm">Active</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="block text-[10px] font-mono text-gray-500 truncate opacity-70 group-hover:opacity-100 transition-opacity">
                                        id_<?= substr($item['token'], -12) ?>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </aside>

                <main class="lg:col-span-9 space-y-6 lg:space-y-8">
                    <?php $this->renderMainContent(); ?>
                </main>

            </div>

            <script>
                const drawer = document.getElementById('mobile-drawer');
                const overlay = document.getElementById('drawer-overlay');
                
                function toggleDrawer() {
                    drawer.classList.toggle('-translate-x-full');
                    overlay.classList.toggle('opacity-0');
                    overlay.classList.toggle('pointer-events-none');
                    document.body.style.overflow = drawer.classList.contains('-translate-x-full') ? '' : 'hidden';
                }

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !drawer.classList.contains('-translate-x-full')) {
                        toggleDrawer();
                    }
                });
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Renders a clean error screen and halts execution
     */
    private function renderErrorScreen(string $title, string $message): void {
        echo "<div style='background:#0a0a0a; color:#ef4444; height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; font-family:sans-serif;'>";
        echo "<h1>$title</h1>";
        echo "<p style='color:#888; margin-top:10px;'>$message</p>";
        echo "<a href='{$this->baseUrl}' style='margin-top:20px; padding:10px 20px; background:#1f2937; color:#fff; text-decoration:none; border-radius:5px;'>Return Home</a>";
        echo "</div>";
        exit;
    }
}