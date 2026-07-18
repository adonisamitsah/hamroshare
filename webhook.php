<?php
/**
 * webhook.php - Deep Nesting Telegram Bot Framework
 */
require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */

$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');
$webhookSecret = $_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? getenv('TELEGRAM_WEBHOOK_SECRET');
$allowedChats = explode(',', $_ENV['TELEGRAM_CHAT_IDS'] ?? getenv('TELEGRAM_CHAT_IDS'));

$incomingToken = getallheaders()['X-Telegram-Bot-Api-Secret-Token'] ?? '';
if (!empty($webhookSecret) && $incomingToken !== $webhookSecret) {
    http_response_code(403); die("Access Denied");
}

class TelegramFramework {
    private $db;
    private $botToken;
    private $allowedChats;

    /**
     * ==========================================
     * 1. THE ROUTE REGISTRY
     * ==========================================
     * Every single view in your app goes here.
     */
    private $routes = [
        // --- LEVEL 1: MAIN MENU ---
        'portfolio' => ['icon' => '💼', 'label' => 'Global Portfolio', 'method' => 'viewPortfolioMenu', 'in_menu' => true],
        'system'    => ['icon' => '⚙️', 'label' => 'System Status',    'method' => 'viewSystem',        'in_menu' => true],
        'users'     => ['icon' => '👥', 'label' => 'Manage Users',     'method' => 'viewUsers',         'in_menu' => true],
        'cache'     => ['icon' => '🧹', 'label' => 'Cache Manager',    'method' => 'viewCacheManager',  'in_menu' => true],
        
        // --- LEVEL 2: DRILL-DOWNS ---
        'scrips'    => ['method' => 'viewScripList',  'in_menu' => false],
        'clients'   => ['method' => 'viewClientList', 'in_menu' => false],
        
        // --- LEVEL 3: DEEP DETAILS ---
        'detail'    => ['method' => 'viewScripDetail',  'in_menu' => false],
        'client_dtl'=> ['method' => 'viewClientDetail', 'in_menu' => false],
        'user_dtl'  => ['method' => 'viewUserDetail',   'in_menu' => false],
        
        // --- ACTIONS ---
        'toggle_u'    => ['method' => 'actionToggleUser', 'in_menu' => false],
        'clear_cache' => ['method' => 'actionClearCache', 'in_menu' => false]
    ];

    public function __construct($db, $botToken, $allowedChats) {
        $this->db = $db;
        $this->botToken = $botToken;
        $this->allowedChats = $allowedChats;
    }

    /**
     * ==========================================
     * 2. THE CORE ROUTER
     * ==========================================
     */
    public function handleRequest($update) {
        if (!$update) return;

        $chatId = $update['message']['chat']['id'] ?? $update['callback_query']['message']['chat']['id'] ?? null;
        $userId = $update['message']['from']['id'] ?? $update['callback_query']['from']['id'] ?? null;

        if (!in_array((string)$chatId, $this->allowedChats) && !in_array((string)$userId, $this->allowedChats)) return;

        if (isset($update['message']['text'])) {
            $this->sendChatAction($chatId, 'typing');
            
            // Search Scrip Query Logic
            $text = trim($update['message']['text']);
            if (strpos(strtolower($text), '/start') === 0 || strpos(strtolower($text), '/menu') === 0) {
                 $this->renderMainMenu($chatId);
            } elseif (strpos(strtolower($text), '/ping') === 0) {
                 $this->sendMessage($chatId, "🟢 *System Online*\nDatabase and Webhook are actively communicating.");
            } else {
                 $this->searchScripHoldings($chatId, strtoupper($text));
            }
            
        } elseif (isset($update['callback_query'])) {
            $messageId = $update['callback_query']['message']['message_id'];
            $callbackId = $update['callback_query']['id'];
            
            // Format: "route:parameter:page"
            $data = explode(':', $update['callback_query']['data']);
            $route = $data[0] ?? 'menu';
            $param = $data[1] ?? null;
            $page  = (int)($data[2] ?? 0);

            $this->answerCallbackQuery($callbackId);

            if ($route === 'menu') {
                $this->renderMainMenu($chatId, $messageId);
            } elseif (isset($this->routes[$route])) {
                $methodName = $this->routes[$route]['method'];
                $this->$methodName($chatId, $messageId, $param, $page);
            }
        }
    }

    private function renderMainMenu($chatId, $messageId = null) {
        $buttons = [];
        foreach ($this->routes as $key => $route) {
            if ($route['in_menu']) {
                $buttons[$route['icon'] . ' ' . $route['label']] = "{$key}:null:0";
            }
        }
        
        $keyboard = $this->buildGrid($buttons, 2);
        $text = "🎛 *HamroShare Command Center*\nSelect a module below. You can also type a scrip symbol (e.g. `TPKHL`) to search holdings instantly.";

        if ($messageId) $this->editMessage($chatId, $messageId, $text, $keyboard);
        else $this->sendMessage($chatId, $text, $keyboard);
    }

    /**
     * ==========================================
     * 3. APP MODULES (THE DEEP NESTING LOGIC)
     * ==========================================
     */

    // --- PORTFOLIO & SCRIP LOGIC ---
    private function viewPortfolioMenu($chatId, $messageId, $param, $page) {
        $accountQuery = $this->db->querySingle("SELECT COUNT(*) FROM users WHERE is_active=1");
        
        $stmt = $this->db->prepare("SELECT value FROM constant WHERE key LIKE 'allshares_report_%' ORDER BY id DESC LIMIT 1");
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        if ($result && $data = json_decode($result['value'], true)) {
            $valuation = number_format($data['metrics']['total_value_ltp'] ?? 0, 2);
            $text = "📈 *Global Portfolio Valuation*\n\n" .
                    "👥 *Active Accounts:* `{$accountQuery}`\n" .
                    "💵 *Aggregate Valuation:* `Rs. {$valuation}`\n" .
                    "⏱ *Last Synced:* `" . ($data['generated_at'] ?? 'Unknown') . "`";
        } else {
            $text = "📈 *Global Portfolio Valuation*\n\n👥 *Active Accounts:* `{$accountQuery}`\n💵 *Valuation:* `See Web Dashboard`";
        }
        
        $buttons = [
            '🔍 View by Scrip'  => 'scrips:null:0',
            '👤 View by Client' => 'clients:null:0'
        ];
        
        $keyboard = $this->buildGrid($buttons, 1, 'menu');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function viewScripList($chatId, $messageId, $param, $page) {
        $limit = 10;
        
        $query = "SELECT myshare FROM users WHERE is_active=1 AND myshare IS NOT NULL AND myshare != ''";
        $results = $this->db->query($query);
        
        $scripData = []; 
        $totalSystemValue = 0;

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $rawShare = html_entity_decode($row['myshare'], ENT_QUOTES, 'UTF-8');
            $jsonStart = strpos($rawShare, '{');
            
            if ($jsonStart !== false) {
                $rawShare = substr($rawShare, $jsonStart);
                $shareData = json_decode($rawShare, true);
                
                if (is_array($shareData) && isset($shareData['meroShareMyPortfolio'])) {
                    foreach ($shareData['meroShareMyPortfolio'] as $item) {
                        $scrip = $item['script'];
                        if (empty($scrip)) continue;

                        $val = (float)($item['valueOfLastTransPrice'] ?? 0);
                        $units = (float)($item['currentBalance'] ?? 0);

                        if (!isset($scripData[$scrip])) {
                            $scripData[$scrip] = ['value' => 0, 'units' => 0];
                        }
                        
                        $scripData[$scrip]['value'] += $val;
                        $scripData[$scrip]['units'] += $units;
                        $totalSystemValue += $val;
                    }
                }
            }
        }

        if (empty($scripData)) {
            $text = "⚠️ *No Active Holdings*\nThere are currently no shares tracked in the local system cache.";
            $keyboard = $this->buildGrid([], 1, 'portfolio:null:0');
            $this->editMessage($chatId, $messageId, $text, $keyboard);
            return;
        }

        uasort($scripData, function($a, $b) { return $b['value'] <=> $a['value']; });

        $uniqueScrips = array_keys($scripData);
        $totalScrips = count($uniqueScrips);
        $totalPages = ceil($totalScrips / $limit);
        
        if ($page < 0) $page = 0;
        if ($page >= $totalPages) $page = $totalPages - 1;
        $offset = $page * $limit;

        $pageScrips = array_slice($uniqueScrips, $offset, $limit);

        $text = "📂 *System Portfolio Breakdown*\n";
        $text .= "Page " . ($page + 1) . " of {$totalPages} | Total Assets: `{$totalScrips}`\n";
        $text .= "Global Valuation: `Rs. " . number_format($totalSystemValue, 2) . "`\n\n";
        $text .= "🎯 *Ranked Assets (This Page):*\n";

        $buttons = [];
        foreach ($pageScrips as $index => $scrip) {
            $data = $scripData[$scrip];
            $rank = $offset + $index + 1;
            
            $valFormatted = number_format($data['value'], 2);
            $text .= "`{$rank}.` *{$scrip}* - `{$data['units']} Units` (Rs. {$valFormatted})\n";
            $buttons["🏷 {$scrip}"] = "detail:{$scrip}:0";
        }
        
        $text .= "\n_Select an asset below to view top holders and deep metrics._";

        $keyboard = $this->buildGrid($buttons, 2, 'portfolio:null:0', $page, $totalPages, 'scrips');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function viewScripDetail($chatId, $messageId, $targetScrip, $page) {
        $query = "SELECT name, username, myshare FROM users WHERE is_active=1 AND myshare IS NOT NULL";
        $results = $this->db->query($query);

        $totalUnits = 0;
        $totalValue = 0;
        $ltp = 0;
        $holders = [];

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $rawShare = html_entity_decode($row['myshare'] ?? '', ENT_QUOTES, 'UTF-8');
            $jsonStart = strpos($rawShare, '{');
            
            if ($jsonStart !== false) {
                $rawShare = substr($rawShare, $jsonStart);
                $shareData = json_decode($rawShare, true);
                
                if (isset($shareData['meroShareMyPortfolio'])) {
                    foreach ($shareData['meroShareMyPortfolio'] as $item) {
                        if ($item['script'] === $targetScrip) {
                            $displayName = !empty($row['name']) ? $row['name'] : $row['username'];
                            $bal = (float)$item['currentBalance'];
                            $val = (float)($item['valueOfLastTransPrice'] ?? 0);
                            $ltp = (float)($item['lastTransactionPrice'] ?? $ltp);

                            $totalUnits += $bal;
                            $totalValue += $val;

                            $holders[] = [
                                'name' => $displayName,
                                'units' => $bal
                            ];
                        }
                    }
                }
            }
        }

        usort($holders, function($a, $b) { return $b['units'] <=> $a['units']; });

        $text = "📊 *Scrip Intelligence: {$targetScrip}*\n\n";
        $text .= "💰 *Current LTP:* `Rs. " . number_format($ltp, 2) . "`\n";
        $text .= "📦 *Total System Units:* `{$totalUnits}`\n";
        $text .= "💎 *Total Valuation:* `Rs. " . number_format($totalValue, 2) . "`\n\n";
        $text .= "👥 *Top Account Holders:*\n";

        $count = 0;
        foreach ($holders as $h) {
            if ($count >= 5) {
                $text .= "  _...and " . (count($holders) - 5) . " more accounts_\n";
                break;
            }
            $text .= "• `{$h['name']}`: {$h['units']} units\n";
            $count++;
        }

        $keyboard = $this->buildGrid([], 1, 'scrips:null:0');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function searchScripHoldings($chatId, $scripQuery) {
        if (!preg_match('/^[A-Z0-9]{2,10}$/', $scripQuery)) {
            $this->sendMessage($chatId, "❌ Invalid scrip format. Please send a valid NEPSE symbol (e.g., TPKHL).");
            return;
        }

        $query = "SELECT name, username, myshare FROM users WHERE is_active=1";
        $results = $this->db->query($query);
        
        $holders = [];
        $totalUnits = 0;

        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $rawShare = html_entity_decode($row['myshare'] ?? '', ENT_QUOTES, 'UTF-8');
            $jsonStart = strpos($rawShare, '{');
            if ($jsonStart !== false) $rawShare = substr($rawShare, $jsonStart);
            
            $shareData = json_decode($rawShare, true);
            if (isset($shareData['meroShareMyPortfolio'])) {
                foreach ($shareData['meroShareMyPortfolio'] as $item) {
                    if ($item['script'] === $scripQuery) {
                        $displayName = !empty($row['name']) ? $row['name'] : $row['username'];
                        $balance = (float)$item['currentBalance'];
                        
                        $holders[] = "👤 `{$displayName}`: {$balance} units";
                        $totalUnits += $balance;
                    }
                }
            }
        }

        if (empty($holders)) {
            $this->sendMessage($chatId, "📉 *Search Result: {$scripQuery}*\n\nNo active holdings found in your monitored portfolios.");
        } else {
            $text = "📈 *Search Result: {$scripQuery}*\n\n";
            $text .= implode("\n", $holders);
            $text .= "\n\n💰 *Total Monitored Units:* `{$totalUnits}`";
            $this->sendMessage($chatId, $text);
        }
    }

    // --- CLIENT VIEW LOGIC ---
    private function viewClientList($chatId, $messageId, $param, $page) {
        $limit = 10;
        $offset = $page * $limit;

        $totalClients = $this->db->querySingle("SELECT COUNT(*) FROM users WHERE is_active=1");
        $totalPages = ceil($totalClients / $limit);

        $stmt = $this->db->prepare("SELECT dmat_num, name, username FROM users WHERE is_active=1 ORDER BY name ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $results = $stmt->execute();

        $buttons = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $displayName = !empty($row['name']) ? $row['name'] : $row['username'];
            $buttons["👤 {$displayName}"] = "client_dtl:{$row['dmat_num']}:0"; 
        }

        $text = "📂 *Client Portfolios* (Page " . ($page + 1) . "/{$totalPages})\nSelect a client to view their holdings:";
        
        $keyboard = $this->buildGrid($buttons, 2, 'portfolio', $page, $totalPages, 'clients');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function viewClientDetail($chatId, $messageId, $dmatNum, $page) {
        $stmt = $this->db->prepare("SELECT name, username, password, crn, pin, myshare FROM users WHERE dmat_num = :dmat");
        $stmt->bindValue(':dmat', $dmatNum, SQLITE3_TEXT);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            $this->answerCallbackQuery($messageId, "Error: User not found.");
            return;
        }

        $displayName = !empty($user['name']) ? $user['name'] : $user['username'];
        $mUsername = $user['username'] ?: 'N/A';
        $mPassword = $user['password'] ?: 'N/A';
        $mCrn      = $user['crn'] ?: 'N/A';
        $mPin      = $user['pin'] ?: 'N/A';

        $rawShare = html_entity_decode($user['myshare'] ?? '', ENT_QUOTES, 'UTF-8');
        $jsonStart = strpos($rawShare, '{');
        
        $totalValue = 0;
        $holdingsList = "";

        if ($jsonStart !== false) {
            $rawShare = substr($rawShare, $jsonStart);
            $shareData = json_decode($rawShare, true);
            
            if (isset($shareData['meroShareMyPortfolio'])) {
                foreach ($shareData['meroShareMyPortfolio'] as $item) {
                    $val = (float)($item['valueOfLastTransPrice'] ?? 0);
                    $totalValue += $val;
                    $holdingsList .= "• `{$item['script']}`: {$item['currentBalance']} units\n";
                }
            }
        }

        $text = "👤 *Client Intelligence: {$displayName}*\n";
        $text .= "💵 *Total Value:* `Rs. " . number_format($totalValue, 2) . "`\n\n";
        
        $text .= "🔐 *CONFIDENTIAL CREDENTIALS*\n";
        $text .= "🆔 *DMAT:* `{$dmatNum}`\n";
        $text .= "👤 *User:* `{$mUsername}`\n";
        $text .= "🔑 *Pass:* ||{$mPassword}||\n";
        $text .= "🏦 *CRN:* ||{$mCrn}||\n";
        $text .= "🔢 *PIN:* ||{$mPin}||\n\n";
        
        $text .= "📦 *Active Holdings:*\n" . ($holdingsList ?: "_No active holdings found._");

        $keyboard = $this->buildGrid([], 1, 'clients:null:0');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    // --- USER MANAGEMENT LOGIC ---
    private function viewUsers($chatId, $messageId, $param, $page) {
        $limit = 5;
        $totalUsers = $this->db->querySingle("SELECT COUNT(*) FROM users");
        $totalPages = ceil($totalUsers / $limit);
        
        if ($page < 0) $page = 0;
        if ($page >= $totalPages && $totalPages > 0) $page = $totalPages - 1;
        $offset = $page * $limit;

        $stmt = $this->db->prepare("SELECT id, name, username, is_active FROM users ORDER BY name ASC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
        $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
        $results = $stmt->execute();

        $buttons = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $displayName = !empty($row['name']) ? $row['name'] : $row['username'];
            $statusIcon = $row['is_active'] ? '🟢' : '🔴';
            $buttons["{$statusIcon} {$displayName}"] = "user_dtl:{$row['id']}:{$page}";
        }

        $text = "👥 *User Management System*\n";
        $text .= "Page " . ($page + 1) . " of {$totalPages} | Total Accounts: `{$totalUsers}`\n\n";
        $text .= "_Select an account below to view credentials and manage status._";

        $keyboard = $this->buildGrid($buttons, 1, 'menu:null:0', $page, $totalPages, 'users');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function viewUserDetail($chatId, $messageId, $userId, $page) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if (!$user) {
            $this->answerCallbackQuery($messageId, "Error: User not found.");
            return;
        }

        $name     = htmlspecialchars(!empty($user['name']) ? $user['name'] : 'N/A');
        $username = htmlspecialchars($user['username'] ?: 'N/A');
        $password = htmlspecialchars($user['password'] ?: 'N/A');
        $crn      = htmlspecialchars($user['crn'] ?: 'N/A');
        $pin      = htmlspecialchars($user['pin'] ?: 'N/A');
        $dmat     = htmlspecialchars($user['dmat_num'] ?: 'N/A');
        $phone    = htmlspecialchars($user['phone'] ?: 'N/A');
        $status   = $user['is_active'] ? '🟢 Active' : '🔴 Disabled';
        $statusAction = $user['is_active'] ? '🔴 Disable User' : '🟢 Enable User';

        $totalValue = 0;
        $rawShare = html_entity_decode($user['myshare'] ?? '', ENT_QUOTES, 'UTF-8');
        $jsonStart = strpos($rawShare, '{');
        if ($jsonStart !== false) {
            $shareData = json_decode(substr($rawShare, $jsonStart), true);
            if (isset($shareData['meroShareMyPortfolio'])) {
                foreach ($shareData['meroShareMyPortfolio'] as $item) {
                    $totalValue += (float)($item['valueOfLastTransPrice'] ?? 0);
                }
            }
        }
        $valFormat = number_format($totalValue, 2);

        $text = "👤 <b>User Intelligence: {$name}</b>\n";
        $text .= "📊 <b>Status:</b> {$status}\n";
        $text .= "💵 <b>Portfolio Value:</b> <code>Rs. {$valFormat}</code>\n\n";
        
        $text .= "🔐 <b>CONFIDENTIAL CREDENTIALS</b>\n";
        $text .= "🆔 <b>DMAT:</b> <code>{$dmat}</code>\n";
        $text .= "📞 <b>Phone:</b> <code>{$phone}</code>\n";
        $text .= "👤 <b>Username:</b> <code>{$username}</code>\n";
        $text .= "🔑 <b>Password:</b> <span class=\"tg-spoiler\">{$password}</span>\n";
        $text .= "🏦 <b>CRN:</b> <span class=\"tg-spoiler\">{$crn}</span>\n";
        $text .= "🔢 <b>PIN:</b> <span class=\"tg-spoiler\">{$pin}</span>\n\n";
        
        $text .= "<i>Tap the blurred blocks to safely reveal credentials.</i>";

        $buttons = [
            $statusAction => "toggle_u:{$user['id']}:{$page}"
        ];
        
        $keyboard = $this->buildGrid($buttons, 1, "users:null:{$page}");
        $this->editMessageHtml($chatId, $messageId, $text, $keyboard);
    }

    private function actionToggleUser($chatId, $messageId, $userId, $page) {
        if (!empty($userId)) {
            $stmt = $this->db->prepare("UPDATE users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id");
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            $stmt->execute();
        }
        $this->viewUserDetail($chatId, $messageId, $userId, $page);
    }

    // --- SYSTEM & CACHE LOGIC ---
    private function viewSystem($chatId, $messageId, $param, $page) {
        $dbPath = realpath(__DIR__ . '/data.sqlite') ?: realpath(__DIR__ . '/hamroshare.db');
        $dbSize = ($dbPath && file_exists($dbPath)) ? number_format(filesize($dbPath) / 1024 / 1024, 2) . ' MB' : 'Unknown';
        
        $memory = number_format(memory_get_usage(true) / 1024 / 1024, 2) . ' MB';
        $time = date('Y-m-d H:i:s T');
        $botStatus = filter_var($_ENV['PARSER_BOT'] ?? getenv('PARSER_BOT'), FILTER_VALIDATE_BOOLEAN) ? '🟢 ONLINE' : '🔴 OFFLINE';

        $activeUsers = $this->db->querySingle("SELECT COUNT(*) FROM users WHERE is_active=1");
        $totalUsers = $this->db->querySingle("SELECT COUNT(*) FROM users");

        $text = "⚙️ *System Health & Telemetry*\n\n";
        $text .= "🗄 *Database Size:* `{$dbSize}`\n";
        $text .= "🧠 *PHP Memory Usage:* `{$memory}`\n";
        $text .= "🕒 *Server Time:* `{$time}`\n";
        $text .= "🤖 *Parser Engine:* `{$botStatus}`\n";
        $text .= "👥 *Account Status:* `{$activeUsers} Active / {$totalUsers} Total`\n";

        $keyboard = $this->buildGrid([], 1, 'menu:null:0');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function viewCacheManager($chatId, $messageId, $param, $page) {
        $cacheDir = __DIR__ . '/market_cache';
        $fileCount = 0;
        $sizeBytes = 0;

        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.json');
            $fileCount = count($files);
            foreach ($files as $file) {
                $sizeBytes += filesize($file);
            }
        }
        
        $sizeMb = number_format($sizeBytes / 1024 / 1024, 3);

        $text = "🧹 *System Cache Manager*\n\n";
        $text .= "📁 *Tracked Assets:* `{$fileCount} JSON files`\n";
        $text .= "💾 *Storage Payload:* `{$sizeMb} MB`\n\n";
        $text .= "_Clearing the cache will wipe historical data and force the cron engine to fetch fresh data from the Parse API on the next loop._";

        $buttons = [
            '⚠️ Force Clear All Cache' => 'clear_cache:null:0'
        ];
        $keyboard = $this->buildGrid($buttons, 1, 'menu:null:0');
        
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    private function actionClearCache($chatId, $messageId, $param, $page) {
        $cacheDir = __DIR__ . '/market_cache';
        $deletedCount = 0;

        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*.json');
            foreach ($files as $file) {
                if (unlink($file)) {
                    $deletedCount++;
                }
            }
        }

        $text = "✅ *Cache Purged Successfully*\n\nRemoved `{$deletedCount}` orphaned/stale market cache files from the server. Matrix will rebuild automatically.";
        
        $keyboard = $this->buildGrid([], 1, 'menu:null:0');
        $this->editMessage($chatId, $messageId, $text, $keyboard);
    }

    /**
     * ==========================================
     * 4. THE UI AUTOMATION ENGINE
     * ==========================================
     */
    private function buildGrid($buttonsFlatArray, $columns = 2, $backRoute = null, $currentPage = null, $totalPages = null, $paginationRoute = null) {
        $keyboard = ['inline_keyboard' => []];
        $currentRow = [];

        foreach ($buttonsFlatArray as $text => $callback) {
            $currentRow[] = ['text' => $text, 'callback_data' => $callback];
            if (count($currentRow) === $columns) {
                $keyboard['inline_keyboard'][] = $currentRow;
                $currentRow = [];
            }
        }
        if (!empty($currentRow)) $keyboard['inline_keyboard'][] = $currentRow;

        if ($currentPage !== null && $totalPages > 1) {
            $navRow = [];
            if ($currentPage > 0) $navRow[] = ['text' => '⬅️ Prev', 'callback_data' => "{$paginationRoute}:null:" . ($currentPage - 1)];
            if ($currentPage < $totalPages - 1) $navRow[] = ['text' => 'Next ➡️', 'callback_data' => "{$paginationRoute}:null:" . ($currentPage + 1)];
            if (!empty($navRow)) $keyboard['inline_keyboard'][] = $navRow;
        }

        if ($backRoute) {
            $keyboard['inline_keyboard'][] = [['text' => '🔙 Back', 'callback_data' => $backRoute]];
        }

        return $keyboard;
    }

    /**
     * ==========================================
     * 5. API CURL EXECUTORS
     * ==========================================
     */
    private function sendMessage($cId, $txt, $kb = null) { $this->req('sendMessage', ['chat_id'=>$cId, 'text'=>$txt, 'parse_mode'=>'Markdown', 'reply_markup'=>json_encode($kb)]); }
    private function editMessage($cId, $mId, $txt, $kb = null) { $this->req('editMessageText', ['chat_id'=>$cId, 'message_id'=>$mId, 'text'=>$txt, 'parse_mode'=>'Markdown', 'reply_markup'=>json_encode($kb)]); }
    private function editMessageHtml($cId, $mId, $txt, $kb = null) { $this->req('editMessageText', ['chat_id'=>$cId, 'message_id'=>$mId, 'text'=>$txt, 'parse_mode'=>'HTML', 'reply_markup'=>json_encode($kb)]); }
    private function answerCallbackQuery($cbId, $txt = "") { $this->req('answerCallbackQuery', ['callback_query_id'=>$cbId, 'text'=>$txt]); }
    private function sendChatAction($cId, $act) { $this->req('sendChatAction', ['chat_id'=>$cId, 'action'=>$act]); }
    private function req($method, $data) {
        $ch = curl_init("https://api.telegram.org/bot{$this->botToken}/{$method}");
        curl_setopt_array($ch, [CURLOPT_POST=>1, CURLOPT_RETURNTRANSFER=>1, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POSTFIELDS=>json_encode($data), CURLOPT_TIMEOUT=>5]);
        curl_exec($ch); curl_close($ch);
    }
}

$update = json_decode(file_get_contents('php://input'), true);
$bot = new TelegramFramework($db, $botToken, $allowedChats);
$bot->handleRequest($update);

http_response_code(200); echo "OK";
?>