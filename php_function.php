<?php

function sudo_get_header($active)
{
    global $db;

    if (!defined('ENVIRONMENT')) {
        define('ENVIRONMENT', 'production');
    }


    $baseMenuItems = [
        'dashboard'           => ['Dashboard', 'dashboard.php', 'fa-home'],
        'login'               => ['Login', 'login.php', 'fa-shield-alt'],
        'applyipo'            => ['Apply IPO', 'applyipo.php', 'fa-plus-circle'],
        'ipo-result'          => ['IPO Result', 'ipo-result.php', 'fa-chart-bar'],
        'addusers'            => ['Add Users', 'addusers.php', 'fa-user-plus'],
        'allshares'           => ['All Shares', 'allshares.php', 'fa-folder'],
        'edis'                => ['EDIS', 'edis.php', 'fa-exchange-alt'],
        'ledger'              => ['Ledger', 'ledger.php', 'fa-wallet'],
        'profit_distribution' => ['Profit Distribution', 'profit_distribute.php', 'fa-coins'],
        'cron'                => ['Cron Management', 'cron.php', 'fa-clock'],
        'backup-restore'      => ['Backup', 'backup-restore.php', 'fa-history'],
    ];

    // 2. Check the .env toggle
    $isParserBotEnabled = filter_var($_ENV['PARSER_BOT'] ?? getenv('PARSER_BOT'), FILTER_VALIDATE_BOOLEAN);

    // 3. Dynamically rebuild the array to inject the item exactly after 'ipo-result'
    $menuItems = [];
    foreach ($baseMenuItems as $key => $item) {
        // Add the standard menu item
        $menuItems[$key] = $item;

        // If we just added 'ipo-result' and the bot is enabled, inject 'ipo-signal' right after it
        if ($key === 'ipo-result' && $isParserBotEnabled) {
            $menuItems['ipo-signal'] = ['IPO Signals', 'ipo-signal.php', 'fa-chart-line'];
        }
    }


    // Safely cast to integer to prevent null errors if DB is locked/empty
    $logCount = (int)$db->querySingle("SELECT COUNT(*) FROM system_logs WHERE is_notified = 0");

    $badge = ($logCount > 0)
        ? '<span class="absolute -top-1.5 -right-1.5 bg-rose-600 text-[10px] text-white w-5 h-5 flex items-center justify-center rounded-full font-bold border-2 border-slate-900 shadow-lg animate-pulse">' . $logCount . '</span>'
        : '';

    $activeTitle = strtoupper($active);

    // Using Output Buffering for massive performance and readability gains
    ob_start();
?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hamroshare Admin</title>

        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
        <meta name="googlebot" content="noindex, nofollow">
        <meta name="bingbot" content="noindex, nofollow">

        <meta name="referrer" content="no-referrer">
        <meta http-equiv="X-Content-Type-Options" content="nosniff">
        <meta name="theme-color" content="#0d1117">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

        <link rel="icon" type="image/png" href="favicon.png">

        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            body {
                background-color: #0f1113;
                color: #e2e8f0;
                font-family: "Inter", sans-serif;
                margin: 0;
                overflow-x: hidden;
            }

            #layout {
                display: flex;
                min-height: 100vh;
                transition: all 0.3s ease;
            }

            /* Sidebar Base Styles */
            #menu {
                width: 280px;
                background: #161b22;
                border-right: 1px solid #30363d;
                flex-shrink: 0;
                display: flex;
                flex-direction: column;
                transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s ease;
                position: relative;
                z-index: 50;
            }

            /* Desktop "Collapsed" State */
            #menu.collapsed {
                width: 80px;
            }

            #menu.collapsed .menu-text,
            #menu.collapsed .pure-menu-heading span,
            #menu.collapsed .status-card {
                display: none;
            }

            #menu.collapsed .pure-menu-heading {
                justify-content: center;
                padding: 24px 0 !important;
            }

            #main {
                flex-grow: 1;
                background: #0d1117;
                width: 100%;
                transition: all 0.3s ease;
            }

            .pure-menu-heading {
                padding: 24px 28px !important;
                color: #f0f6fc !important;
                font-weight: 600 !important;
                font-size: 18px !important;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            /* Mobile Adjustments */
            @media (max-width: 1024px) {
                #menu {
                    position: fixed;
                    height: 100vh;
                    transform: translateX(-100%);
                }

                #menu.mobile-open {
                    transform: translateX(0);
                }

                #layout.mobile-overlay::after {
                    content: "";
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.5);
                    backdrop-filter: blur(4px);
                    z-index: 45;
                }
            }

            ::-webkit-scrollbar {
                width: 5px;
            }

            ::-webkit-scrollbar-track {
                background: transparent;
            }

            ::-webkit-scrollbar-thumb {
                background: #30363d;
                border-radius: 10px;
            }
        </style>
    </head>

    <body class="antialiased">

        <div id="layout">
            <aside id="menu" class="fixed inset-y-0 left-0 z-50 w-64 bg-[#0d1117] border-r border-slate-800 overflow-y-auto">
                <div class="pure-menu w-full overflow-hidden">
                    <div class="pure-menu-heading">
                        <div class="w-8 h-8 flex items-center justify-center shadow-lg shadow-blue-900/20 flex-shrink-0">
                            <img src="favicon.png" alt="Logo" class="w-5 h-5 object-contain">
                        </div>
                        <span class="transition-opacity duration-200">Hamroshare</span>
                    </div>

                    <ul class="mt-4 list-none p-0">
                        <?php foreach ($menuItems as $key => $details):
                            $isActive = ($active == $key);
                            $itemClass = $isActive ? 'bg-blue-600/10 text-blue-400 font-medium' : 'text-slate-400 hover:bg-slate-800 hover:text-slate-200';
                        ?>
                            <li class="px-3 mb-1">
                                <a href="<?= htmlspecialchars($details[1]) ?>" class="menu-link flex items-center py-2.5 px-4 rounded-xl transition-all duration-200 <?= $itemClass ?>" title="<?= htmlspecialchars($details[0]) ?>">
                                    <i class="fas <?= htmlspecialchars($details[2]) ?> w-5 text-center text-sm flex-shrink-0"></i>
                                    <span class="menu-text ml-3 text-[14px] whitespace-nowrap overflow-hidden transition-all duration-300"><?= htmlspecialchars($details[0]) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </aside>

            <main id="main">
                <?php
                // Call the automated checker
                $update_status = check_for_updates();

                if ($update_status['update_available']):
                ?>
                    <!-- Elegant Update Announcement Bar -->
                    <div class="w-full bg-gradient-to-r from-blue-600/20 via-indigo-600/20 to-blue-600/20 border-b border-blue-500/30 px-4 py-2 flex items-center justify-center gap-2 text-center text-xs backdrop-blur-md z-50 relative">
                        <span class="flex h-2 w-2 relative">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
                        </span>
                        <span class="text-slate-300 font-medium tracking-wide">
                            A new update <span class="text-blue-400 font-bold">v<?= htmlspecialchars($update_status['latest_version']) ?></span> is available! (Current: v<?= htmlspecialchars($update_status['local_version']) ?>)
                        </span>
                        <a href="https://github.com/adonisamitsah/hamroshare" target="_blank" class="ml-2 px-2.5 py-0.5 rounded-md bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/30 transition-all duration-200 text-[11px] font-semibold tracking-wider uppercase active:scale-95">
                            Changelog <i class="fas fa-external-link-alt ml-1 text-[9px]"></i>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Your Clean Header Component -->
                <header class="h-16 border-b border-slate-800 flex items-center px-4 md:px-8 justify-between sticky top-0 bg-[#0d1117]/80 backdrop-blur-xl z-40">
                    <div class="flex items-center gap-4">
                        <button id="toggleMenu" class="w-10 h-10 flex items-center justify-center rounded-xl hover:bg-slate-800 transition-colors text-slate-400">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="text-[11px] md:text-sm font-semibold text-slate-300 uppercase tracking-widest truncate"><?= $activeTitle ?></h1>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden md:flex items-center gap-2 bg-green-500/10 text-green-400 px-3 py-1 rounded-full border border-green-500/20">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                            <span class="text-[10px] font-bold uppercase tracking-tighter"><?= ENVIRONMENT; ?></span>
                        </div>

                        <div class="relative inline-block ml-4">
                            <a href="notifications.php" class="group relative flex items-center justify-center w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 text-slate-400 hover:text-blue-400 hover:border-blue-500/50 transition-all duration-300 shadow-inner" title="View System Logs">
                                <i class="fas fa-bell text-[14px] group-hover:rotate-[15deg] transition-transform"></i>
                                <?= $badge ?>
                            </a>
                        </div>

                        <a href="profile.php" title="Master Profile">
                            <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:bg-blue-600/20 hover:text-blue-400 transition-all active:scale-95">
                                <i class="fas fa-user-shield text-xs"></i>
                            </button>
                        </a>

                        <button id="master-logout-btn" title="Secure Logout" class="w-10 h-10 flex items-center justify-center rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-500 hover:bg-rose-500 hover:text-white transition-all active:scale-95">
                            <i class="fas fa-power-off text-xs"></i>
                        </button>
                    </div>
                </header>

                <div id="notification-container" class="fixed bottom-5 right-5 z-[1000] flex flex-col gap-3 w-80"></div>

                <div class="p-4 md:p-8">

                    <script>
                        $(document).ready(function() {
                            $("#toggleMenu").on("click", function(e) {
                                e.stopPropagation();
                                if ($(window).width() > 1024) {
                                    $("#menu").toggleClass("collapsed");
                                } else {
                                    $("#menu").toggleClass("mobile-open");
                                    $("#layout").toggleClass("mobile-overlay");
                                }
                            });

                            $(document).on("click", function(e) {
                                if ($(window).width() <= 1024 && !$(e.target).closest("#menu").length) {
                                    $("#menu").removeClass("mobile-open");
                                    $("#layout").removeClass("mobile-overlay");
                                }
                            });
                        });
                    </script>
                <?php
                // Return the cleanly buffered HTML output
                return ob_get_clean();
            }


            function sudo_get_Authorization($dmat_num, $db = null)
            {
                // This pulls the Singleton DB from config.php. 
                // Even if legacy code passes an old $db connection, this safely overrides it.
                global $db;

                $stmt = $db->prepare("SELECT Authorization FROM users WHERE dmat_num = :dmat LIMIT 1");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);

                $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                return $result ? $result['Authorization'] : '';
            }

            function sudo_put_Authorization($dmat_num, $db, $Authorization)
            {
                // This safely pulls your global database instance.
                // We leave $db in the middle of the function parameters so old files 
                // that pass 3 arguments won't break the application.
                global $db;

                $stmt = $db->prepare("UPDATE users SET Authorization = :auth, lastLogin = :time WHERE dmat_num = :dmat");
                $stmt->bindValue(':auth', $Authorization, SQLITE3_TEXT);
                $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);

                $stmt->execute();
            }


            function sudo_update_bankDetails($dmat_num, $db, $companyShareId)
            {
                $Authorization = sudo_get_Authorization($dmat_num, $db);

                // Safety Catch: Do not spam the API if we don't have a token
                if (empty($Authorization)) {
                    return "Failed: No Authorization token";
                }

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Fetch DB info early so we don't interrupt the API flow
                // -------------------------------------------------------------------------
                $bankPosQuery = "SELECT bankPosition FROM users WHERE dmat_num = :dmat LIMIT 1;";
                $stmtPos = $db->prepare($bankPosQuery);
                $stmtPos->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $posResult = $stmtPos->execute()->fetchArray(SQLITE3_ASSOC);
                $bankPosition = isset($posResult['bankPosition']) ? (int)$posResult['bankPosition'] : 0;

                $boid = $dmat_num;

                $headers = array(
                    "Accept: application/json, text/plain, */*",
                    "Authorization: " . $Authorization,
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36",
                    "Origin: https://meroshare.cdsc.com.np",
                    "Referer: https://meroshare.cdsc.com.np/",
                    "sec-ch-ua: \"Not-A.Brand\";v=\"24\", \"Chromium\";v=\"146\"",
                    "sec-ch-ua-mobile: ?1",
                    "sec-ch-ua-platform: \"Android\""
                );

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: cURL Keep-Alive (Massive Speed Boost)
                // Instead of opening and closing 3 separate TCP/SSL connections, we initialize 
                // cURL once and reuse the secure tunnel for all 3 requests.
                // -------------------------------------------------------------------------
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT => 15
                ]);

                // --- STEP 1: The "Can Apply" Handshake ---
                curl_setopt($ch, CURLOPT_URL, "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/customerType/{$companyShareId}/{$boid}");
                curl_exec($ch);

                usleep(300000); // 0.3s pause

                // --- STEP 2: Get Bank List ---
                curl_setopt($ch, CURLOPT_URL, "https://webbackend.cdsc.com.np/api/meroShare/bank/");
                $resp1 = curl_exec($ch);
                $bankList = json_decode($resp1, true);

                if (!isset($bankList[0]['id'])) {
                    curl_close($ch);
                    return "Failed to fetch bank list";
                }

                $bankIdForUrl = $bankList[$bankPosition]['id'] ?? $bankList[0]['id'];

                usleep(300000);

                // --- STEP 3: Get Account Details ---
                curl_setopt($ch, CURLOPT_URL, "https://webbackend.cdsc.com.np/api/meroShare/bank/" . $bankIdForUrl);
                $resp2 = curl_exec($ch);

                // Close the single connection only after all data is fetched
                curl_close($ch);

                $detailsArray = json_decode($resp2, true);

                if (isset($detailsArray[0]['accountNumber'])) {
                    $data = $detailsArray[0];

                    $query = "UPDATE users SET 
                    accountNumber = :acc, 
                    customerId = :cust, 
                    accountBranchId = :branch, 
                    bankId = :bank,
                    accountTypeId = :typeId 
                  WHERE dmat_num = :dmat";

                    // ---------------------------------------------------------------------
                    // OPTIMIZATION 3: Strict SQLite Data Types
                    // Casting to INT ensures faster DB indexing and prevents type mismatches.
                    // ---------------------------------------------------------------------
                    $stmt = $db->prepare($query);
                    $stmt->bindValue(':acc', $data['accountNumber'], SQLITE3_TEXT);
                    $stmt->bindValue(':cust', (int)$data['id'], SQLITE3_INTEGER);
                    $stmt->bindValue(':branch', (int)$data['accountBranchId'], SQLITE3_INTEGER);
                    $stmt->bindValue(':bank', (int)$bankIdForUrl, SQLITE3_INTEGER);
                    $stmt->bindValue(':typeId', (int)$data['accountTypeId'], SQLITE3_INTEGER);
                    $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);

                    return $stmt->execute() ? "Success" : "DB Update Failed";
                }

                return "Failed at Step 3: " . $resp2;
            }


            function sudo_apply_ipo($dmat_num, $companyShareId, $db = null)
            {
                global $db; // Safely pull your global Singleton if an old script passes a dead connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Secure Prepared Statement (Anti-SQL Injection)
                // -------------------------------------------------------------------------
                $stmt = $db->prepare("SELECT * FROM users WHERE dmat_num = :dmat LIMIT 1");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                if (!$user) {
                    return json_encode(["message" => "User not found in database."]);
                }
                if (empty($user['Authorization'])) {
                    return json_encode(["message" => "Missing Authorization token. Login required."]);
                }

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: querySingle() - 3x Faster than full query loops
                // -------------------------------------------------------------------------
                $kittaFetch = $db->querySingle("SELECT value FROM constant WHERE key = 'appliedKitta'");
                $appliedKitta = $kittaFetch ? (string)$kittaFetch : "10";

                $url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/share/apply";

                $headers = array(
                    "Content-Type: application/json",
                    "Authorization: " . $user['Authorization'],
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36",
                    "Origin: https://meroshare.cdsc.com.np",
                    "Referer: https://meroshare.cdsc.com.np/",
                    "Accept: application/json, text/plain, */*",
                    "sec-ch-ua: \"Not-A.Brand\";v=\"24\", \"Chromium\";v=\"146\"",
                    "sec-ch-ua-mobile: ?1",
                    "sec-ch-ua-platform: \"Android\""
                );

                // -------------------------------------------------------------------------
                // OPTIMIZATION 3: Null Coalescing Fallbacks (Prevents JSON Encoding Crashes)
                // -------------------------------------------------------------------------
                $postData = [
                    "demat"           => $dmat_num,
                    "boid"            => substr($dmat_num, -8),
                    "accountNumber"   => (string)($user['accountNumber'] ?? ''),
                    "customerId"      => (int)($user['customerId'] ?? 0),
                    "accountBranchId" => (int)($user['accountBranchId'] ?? 0),
                    "accountTypeId"   => (int)($user['accountTypeId'] ?? 1),
                    "appliedKitta"    => $appliedKitta,
                    "crnNumber"       => (string)($user['crnNumber'] ?? ''),
                    "transactionPIN"  => (string)($user['transactionPIN'] ?? ''),
                    "companyShareId"  => (string)$companyShareId,
                    "bankId"          => (string)($user['bankId'] ?? '')
                ];

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => json_encode($postData),
                    CURLOPT_SSL_VERIFYPEER => false,
                    // ---------------------------------------------------------------------
                    // OPTIMIZATION 4: Timeouts to prevent Cron gridlock
                    // ---------------------------------------------------------------------
                    CURLOPT_TIMEOUT        => 15, // Abort if MeroShare hangs for 15 seconds
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $resp = curl_exec($curl);
                $error = curl_error($curl);
                curl_close($curl);

                // -------------------------------------------------------------------------
                // OPTIMIZATION 5: Handle Dead Connections Gracefully
                // -------------------------------------------------------------------------
                if ($resp === false) {
                    return json_encode(["message" => "Connection to MeroShare failed: " . $error]);
                }

                return $resp;
            }

            function sudo_reapply_ipo($dmat_num, $applicationId, $companyShareId, $appliedKitta, $db = null)
            {
                global $db; // Safely pull your global Singleton if legacy code passes a dead connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Secure Prepared Statement (Anti-SQL Injection)
                // -------------------------------------------------------------------------
                $stmt = $db->prepare("SELECT * FROM users WHERE dmat_num = :dmat LIMIT 1");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                if (!$user) {
                    return json_encode(["message" => "User not found in database."]);
                }
                if (empty($user['Authorization'])) {
                    return json_encode(["message" => "Missing Authorization token. Login required."]);
                }

                $url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/share/reapply/" . $applicationId;

                $headers = [
                    "Content-Type: application/json",
                    "Authorization: " . $user['Authorization'],
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36"
                ];

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: Null Coalescing Fallbacks (Prevents JSON Encoding Crashes)
                // Ensures strictly typed outputs even if the database field is empty.
                // -------------------------------------------------------------------------
                $postData = [
                    "appliedKitta"    => (int)$appliedKitta,
                    "companyShareId"  => (string)$companyShareId,
                    "customerId"      => (int)($user['customerId'] ?? 0),
                    "boid"            => substr($dmat_num, -8),
                    "crnNumber"       => (string)($user['crnNumber'] ?? ''),
                    "bankId"          => (int)($user['bankId'] ?? 0),
                    "accountNumber"   => (string)($user['accountNumber'] ?? ''),
                    "demat"           => $dmat_num,
                    "accountBranchId" => (int)($user['accountBranchId'] ?? 0),
                    "transactionPIN"  => (string)($user['transactionPIN'] ?? ''),
                    "accountTypeId"   => (int)($user['accountTypeId'] ?? 1)
                ];

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => json_encode($postData),
                    CURLOPT_SSL_VERIFYPEER => false,
                    // ---------------------------------------------------------------------
                    // OPTIMIZATION 3: Timeouts to prevent Cron gridlock
                    // ---------------------------------------------------------------------
                    CURLOPT_TIMEOUT        => 15, // Abort if MeroShare hangs for 15 seconds
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $resp = curl_exec($curl);
                $error = curl_error($curl);
                curl_close($curl);

                // -------------------------------------------------------------------------
                // OPTIMIZATION 4: Handle Dead Connections Gracefully
                // -------------------------------------------------------------------------
                if ($resp === false) {
                    return json_encode(["message" => "Connection to MeroShare failed: " . $error]);
                }

                return $resp;
            }
            function sudo_get_reapply_details($dmat_num, $companyShareId, $db = null)
            {
                global $db; // Safely pull global Singleton if legacy code passes a dead connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Secure Prepared Statement (Anti-SQL Injection)
                // -------------------------------------------------------------------------
                $stmt = $db->prepare("SELECT Authorization FROM users WHERE dmat_num = :dmat LIMIT 1");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                // If the user isn't found, OR if their authorization token is missing/empty
                if (!$user || empty($user['Authorization'])) {
                    return json_encode([]);
                }

                $url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/reapply/" . $companyShareId;

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: " . $user['Authorization'],
                        "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36",
                        // OPTIMIZATION 2: Added standard headers to prevent firewall blocks
                        "Accept: application/json, text/plain, */*",
                        "Origin: https://meroshare.cdsc.com.np",
                        "Referer: https://meroshare.cdsc.com.np/"
                    ],
                    // OPTIMIZATION 3: Split timeouts for connection vs execution
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $resp = curl_exec($curl);
                curl_close($curl);

                // -------------------------------------------------------------------------
                // OPTIMIZATION 4: Graceful Network Failure Fallback
                // -------------------------------------------------------------------------
                if ($resp === false) {
                    // If MeroShare is completely down and cURL fails, return an empty JSON array
                    // just like the user-not-found case. This prevents PHP warnings and 
                    // downstream JSON decoding crashes.
                    return json_encode([]);
                }

                return $resp;
            }
            /**
             * Fetches the active IPO applications for a user to find the ApplicationFormID
             */
            function sudo_search_active_apps($dmat_num, $db = null)
            {
                global $db; // Safely pull global Singleton if legacy code passes a dead connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Secure Prepared Statement (Anti-SQL Injection)
                // -------------------------------------------------------------------------
                $stmt = $db->prepare("SELECT Authorization FROM users WHERE dmat_num = :dmat LIMIT 1");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                // If the user isn't found, OR if their authorization token is missing/empty
                if (!$user || empty($user['Authorization'])) {
                    return json_encode(["object" => []]);
                }

                $url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/active/search/";

                // Exact payload required by MeroShare to fetch the list of applications
                $payload = json_encode([
                    "filterFieldParams" => [
                        ["key" => "companyShare.companyIssue.companyISIN.script", "alias" => "Scrip"],
                        ["key" => "companyShare.companyIssue.companyISIN.company.name", "alias" => "Company Name"]
                    ],
                    "page" => 1,
                    "size" => 200,
                    "searchRoleViewConstants" => "VIEW_APPLICANT_FORM_COMPLETE",
                    "filterDateParams" => [
                        ["key" => "appliedDate", "condition" => "", "alias" => "", "value" => ""],
                        ["key" => "appliedDate", "condition" => "", "alias" => "", "value" => ""]
                    ]
                ]);

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: Hardened Headers & Timeout Splits
                // -------------------------------------------------------------------------
                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: " . $user['Authorization'],
                        "Content-Type: application/json",
                        "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36",
                        // Added standard WAF bypass headers
                        "Accept: application/json, text/plain, */*",
                        "Origin: https://meroshare.cdsc.com.np",
                        "Referer: https://meroshare.cdsc.com.np/"
                    ],
                    CURLOPT_POSTFIELDS     => $payload,
                    CURLOPT_TIMEOUT        => 15, // Total execution timeout
                    CURLOPT_CONNECTTIMEOUT => 10  // Initial connection timeout
                ]);

                $resp = curl_exec($curl);
                curl_close($curl);

                // -------------------------------------------------------------------------
                // OPTIMIZATION 3: Graceful Network Failure Fallback
                // -------------------------------------------------------------------------
                if ($resp === false) {
                    // If MeroShare is completely down or the connection drops, curl_exec 
                    // returns false. This intercepts it and returns a valid, empty JSON array
                    // to prevent PHP warnings and downstream JSON decoding crashes.
                    return json_encode(["object" => []]);
                }

                return $resp;
            }

            function sudo_apply_reserved_ipo($dmat_num, $companyShareId, $db = null)
            {
                global $db; // Safely pull your global Singleton connection

                // 1. Fetch User Data
                $stmt = $db->prepare("SELECT * FROM users WHERE dmat_num = :dmat LIMIT 1");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                if (!$user) {
                    return json_encode(["message" => "User record missing from local database."]);
                }

                // OPTIMIZATION 1: Early Exit for Missing Token
                if (empty($user['Authorization'])) {
                    return json_encode(["message" => "Missing Authorization token. Login required."]);
                }

                $token = $user['Authorization'];
                $boid = substr($dmat_num, -8);

                $headers = [
                    "Content-Type: application/json",
                    "Authorization: $token",
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36",
                    "Origin: https://meroshare.cdsc.com.np",
                    "Referer: https://meroshare.cdsc.com.np/",
                    "Accept: application/json, text/plain, */*"
                ];

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: cURL Keep-Alive (Reusing the TLS Tunnel)
                // -------------------------------------------------------------------------
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 15, // Cron Gridlock Protection
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                // 2. Fetch Share Eligibility Criteria
                curl_setopt($ch, CURLOPT_URL, "https://webbackend.cdsc.com.np/api/shareCriteria/boid/{$dmat_num}/{$companyShareId}");
                $criteriaResp = curl_exec($ch);

                if ($criteriaResp === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    return json_encode(["message" => "Connection failed during eligibility check: " . $error]);
                }

                $criteriaJson = json_decode($criteriaResp, true);

                $shareCriteriaId = $criteriaJson['id'] ?? null;
                $appliedKitta = $criteriaJson['reservedQuantity'] ?? 0;
                $_SESSION['reserved_ipo_applied_kitta'] = $appliedKitta;

                // 3. Eligibility Gate
                if (!$shareCriteriaId || $appliedKitta <= 0) {
                    curl_close($ch);
                    return json_encode([
                        "message" => "Skipped: User has 0 units eligibility allocation for this issue.",
                        "statusCode" => 403
                    ]);
                }

                // -------------------------------------------------------------------------
                // OPTIMIZATION 3: Null Coalescing Fallbacks for DB values
                // -------------------------------------------------------------------------
                $postData = [
                    "demat"           => $dmat_num,
                    "boid"            => $boid,
                    "shareCriteriaId" => (int)$shareCriteriaId,
                    "appliedKitta"    => (int)$appliedKitta,
                    "accountNumber"   => (string)($user['accountNumber'] ?? ''),
                    "customerId"      => (int)($user['customerId'] ?? 0),
                    "accountBranchId" => (int)($user['accountBranchId'] ?? 0),
                    "accountTypeId"   => (int)($user['accountTypeId'] ?? 1),
                    "crnNumber"       => (string)($user['crnNumber'] ?? ''),
                    "transactionPIN"  => (string)($user['transactionPIN'] ?? ''),
                    "companyShareId"  => (string)$companyShareId,
                    "bankId"          => (string)($user['bankId'] ?? '')
                ];

                // 4. Fire Final Application Request using the SAME cURL handle
                curl_setopt($ch, CURLOPT_URL, "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/share/apply");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

                $resp = curl_exec($ch);

                if ($resp === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    return json_encode(["message" => "Connection failed during final application: " . $error]);
                }

                curl_close($ch);

                return $resp;
            }

            function sudo_get_capital_as_options($db = null)
            {
                global $db; // Safely pull your global Singleton if an old script passes a dead connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: querySingle() Efficiency
                // This is C-level optimized in SQLite. It grabs the exact column value 
                // instantly without needing to build arrays or loop through result sets.
                // -------------------------------------------------------------------------
                $options = $db->querySingle("SELECT value FROM constant WHERE key = 'capital_as_options'");

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: Safe Fallbacks
                // If the database is completely empty or the constant hasn't been synced 
                // yet, this prevents returning a null value that would break the frontend.
                // -------------------------------------------------------------------------
                return $options ? (string)$options : '<option value="">Choose DP</option>';
            }
            function sudo_get_dmat_as_options($db = null)
            {
                global $db; // Safely pull your global Singleton if an old script passes a dead connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Memory & Speed (Targeted Selection)
                // Using SELECT * pulls passwords, tokens, and PINs into RAM just to build 
                // a dropdown. We now ONLY fetch the exact two columns we need.
                // OPTIMIZATION 2: UX Improvement (Alphabetical Sorting)
                // -------------------------------------------------------------------------
                $query = "SELECT dmat_num, name FROM users ORDER BY name ASC;";
                $result = $db->query($query);

                $options = "";

                // Safety catch if the table is empty or the query fails
                if (!$result) return $options;

                // Use SQLITE3_ASSOC to prevent fetching duplicated numbered array indexes
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {

                    // ---------------------------------------------------------------------
                    // OPTIMIZATION 3: XSS and HTML Integrity Protection
                    // ---------------------------------------------------------------------
                    $val  = htmlspecialchars($row['dmat_num'], ENT_QUOTES, 'UTF-8');
                    $name = htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8');

                    $options .= '<option value="' . $val . '">' . $name . '</option>';
                }

                return $options;
            }

            function sudo_update_capital_as_options($db = null)
            {
                global $db; // Safely pull global Singleton if legacy code passes a dead connection

                $url = "https://webbackend.cdsc.com.np/api/meroShare/capital/";

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Cleaned Headers & Modern User-Agent
                // Standardized the User-Agent to match your other updated functions, 
                // lowering the risk of Cloudflare/WAF flagging it as a bot.
                // -------------------------------------------------------------------------
                $headers = [
                    "Accept: application/json, text/plain, */*",
                    "Authorization: null",
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36",
                    "Origin: https://meroshare.cdsc.com.np",
                    "Referer: https://meroshare.cdsc.com.np/"
                ];

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    // ---------------------------------------------------------------------
                    // OPTIMIZATION 2: Timeout Protection
                    // ---------------------------------------------------------------------
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_CONNECTTIMEOUT => 10
                ]);

                $resp = curl_exec($curl);
                curl_close($curl);

                // -------------------------------------------------------------------------
                // OPTIMIZATION 3: API Failure Protection (Data Preservation)
                // If MeroShare is down, this prevents the script from overwriting 
                // your database with an empty string, which would break the 'Add User' page.
                // -------------------------------------------------------------------------
                if ($resp === false) return false;

                $json = json_decode($resp, true); // Decode as associative array for faster parsing
                if (empty($json) || !is_array($json)) return false;

                $options = "";

                // -------------------------------------------------------------------------
                // OPTIMIZATION 4: Efficient Foreach Loop & HTML Encoding
                // -------------------------------------------------------------------------
                foreach ($json as $item) {
                    // Safe defaults in case the API changes its payload structure
                    $id = $item['id'] ?? '';
                    $code = htmlspecialchars($item['code'] ?? '', ENT_QUOTES, 'UTF-8');

                    // Format the name safely
                    $rawName = $item['name'] ?? '';
                    $cname = htmlspecialchars(ucwords(strtolower($rawName)), ENT_QUOTES, 'UTF-8');

                    $options .= '<option value="' . $id . 'xxxxx' . $cname . '">' . $cname . ' (' . $code . ')</option>';
                }

                // -------------------------------------------------------------------------
                // OPTIMIZATION 5: Secure Prepared Statement
                // -------------------------------------------------------------------------
                $stmt = $db->prepare("UPDATE constant SET value = :val WHERE key = 'capital_as_options'");
                $stmt->bindValue(':val', $options, SQLITE3_TEXT);
                return $stmt->execute();
            }
            function validate($data)
            {
                $data = trim($data);
                $data = stripslashes($data);
                $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return $data;
            }


            function sudo_put_lastStatusLog($dmat_num, $log, $scrip, $db = null)
            {
                global $db; // Safely pull your global Singleton

                // 1. Format the status log date string
                $date = time() . "_scrip:" . $scrip;

                // 2. Use a Prepared Statement for security
                // This protects against SQL injection if $log or $scrip contain special characters
                $stmt = $db->prepare("UPDATE users SET lastStatusLog = :log, lastStatusLogTime = :date WHERE dmat_num = :dmat");

                $stmt->bindValue(':log', $log, SQLITE3_TEXT);
                $stmt->bindValue(':date', $date, SQLITE3_TEXT);
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);

                $stmt->execute();
            }

            function sudo_put_myshare($dmat_num, $resp, $db = null)
            {
                global $db; // Safely pull your global Singleton connection

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Secure Prepared Statement
                // Prevents SQL Injection if the $resp JSON string contains unexpected characters.
                // -------------------------------------------------------------------------
                $stmt = $db->prepare("UPDATE users SET myshare = :resp, myshare_time = :time WHERE dmat_num = :dmat");

                $stmt->bindValue(':resp', $resp, SQLITE3_TEXT);
                $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);

                $stmt->execute();
            }

            function sudo_get_time_diff($start)
            {
                $diff = time() - (int)$start;

                // Handle potential negative values (if system clocks are slightly out of sync)
                if ($diff < 0) return "Just now";

                if ($diff < 60) {
                    return $diff . ($diff === 1 ? " second ago" : " seconds ago");
                }

                if ($diff < 3600) {
                    $val = floor($diff / 60);
                    return $val . ($val === 1 ? " minute ago" : " minutes ago");
                }

                if ($diff < 86400) {
                    $val = floor($diff / 3600);
                    return $val . ($val === 1 ? " hour ago" : " hours ago");
                }

                if ($diff < 2592000) {
                    $val = floor($diff / 86400);
                    return $val . ($val === 1 ? " day ago" : " days ago");
                }

                if ($diff < 31104000) {
                    $val = floor($diff / 2592000);
                    return $val . ($val === 1 ? " month ago" : " months ago");
                }

                $val = floor($diff / 31104000);
                return $val . ($val === 1 ? " year ago" : " years ago");
            }


            function sudo_get_pl($dmat_num, $page = 1, $Authorization = null, $db = null)
            {
                global $db; // Safely pull global Singleton

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Graceful Authorization Fallback
                // If the legacy file doesn't pass the Auth token, this function 
                // now tries to fetch it from the database automatically using your new 
                // optimized sudo_get_Authorization function.
                // -------------------------------------------------------------------------
                if (empty($Authorization)) {
                    $Authorization = sudo_get_Authorization($dmat_num, $db);
                }

                if (empty($Authorization)) return json_encode(["object" => []]);

                $url = "https://webbackend.cdsc.com.np/api/EDIS/report/search/";

                // -------------------------------------------------------------------------
                // OPTIMIZATION 2: Sanitized Payload
                // Ensure $page is always an integer to prevent invalid API requests.
                // -------------------------------------------------------------------------
                $payload = json_encode([
                    "filterFieldParams" => [
                        ["key" => "requestStatus.name", "value" => "", "alias" => "Status"],
                        ["key" => "contractObligationMap.obligation.settleId", "alias" => "Settlement Id"],
                        ["key" => "contractObligationMap.obligation.scriptCode", "alias" => "Script"],
                        ["key" => "contractObligationMap.obligation.sellCmId", "alias" => "CM ID", "condition" => "="]
                    ],
                    "page" => (int)$page,
                    "size" => 200,
                    "searchRoleViewConstants" => "VIEW",
                    "filterDateParams" => [
                        ["key" => "contractObligationMap.obligation.settleDate", "condition" => "", "alias" => "", "value" => ""],
                        ["key" => "contractObligationMap.obligation.settleDate", "condition" => "", "alias" => "", "value" => ""],
                        ["key" => "requestedDate", "condition" => "", "alias" => "", "value" => ""],
                        ["key" => "requestedDate", "condition" => "", "alias" => "", "value" => ""]
                    ]
                ]);

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 20, // Increased to 20s for large EDIS reports
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER     => [
                        "Authorization: " . $Authorization,
                        "Content-Type: application/json",
                        "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36",
                        "Origin: https://meroshare.cdsc.com.np",
                        "Referer: https://meroshare.cdsc.com.np/",
                        "Accept: application/json, text/plain, */*"
                    ],
                    CURLOPT_POSTFIELDS     => $payload
                ]);

                $resp = curl_exec($curl);
                $error = curl_error($curl);
                curl_close($curl);

                // Return the response or a safe empty object if the network fails
                return ($resp !== false) ? $resp : json_encode(["object" => []]);
            }

            function sudo_put_pl_data($dmat_num, $rx, $db = null)
            {
                global $db; // Safely pull your global Singleton

                // 1. Prepare data
                // Encode the data safely as a JSON string
                $rx_json = is_string($rx) ? $rx : json_encode($rx);
                $date = date("Y-m-d H:i"); // Using H for 24-hour format is safer for sorting

                // 2. Use a Prepared Statement to prevent SQL Injection
                // This is vital when saving large JSON blobs that might contain 
                // characters like quotes or backslashes.
                $stmt = $db->prepare("UPDATE users SET pl_json = :rx, pl_log = :log WHERE dmat_num = :dmat");

                $stmt->bindValue(':rx', $rx_json, SQLITE3_TEXT);
                $stmt->bindValue(':log', $date, SQLITE3_TEXT);
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);

                $stmt->execute();
            }
            function sudo_update_wacc($dmat_num, $symbol, $Authorization = null, $db = null)
            {
                global $db; // Safely pull global Singleton

                // Fallback for Auth Token
                if (empty($Authorization)) {
                    $Authorization = sudo_get_Authorization($dmat_num, $db);
                }

                $date = date("Y-m-d H:i:s");
                $url = "https://webbackend.cdsc.com.np/api/myPurchase/search/";

                // 1. Modernized JSON payload construction
                $payload = json_encode([
                    "demat" => $dmat_num,
                    "scrip" => $symbol
                ]);

                $headers = [
                    "Authorization: " . $Authorization,
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36",
                    "Origin: https://meroshare.cdsc.com.np",
                    "Referer: https://meroshare.cdsc.com.np/",
                    "Accept: application/json, text/plain, */*"
                ];

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => $payload
                ]);

                $resp = curl_exec($curl);
                $curl_error = curl_error($curl);
                curl_close($curl);

                // 2. Error handling: If MeroShare returns empty or an error
                if ($resp === false || empty($resp)) {
                    return "{$symbol} - API Error: {$curl_error}<br>";
                }

                $json = json_decode($resp);

                // 3. Logic check: If JSON is empty or indicates no purchase history found
                if (empty($json)) {
                    return "{$symbol} - Already Updated - {$date}<br>";
                }

                // 4. Delegate to the sync function
                return sudo_upload_wacc($Authorization, $resp, $symbol, $dmat_num, $date);
            }

            function sudo_upload_wacc($Authorization, $data, $symbol, $dmat_num, $date)
            {
                $url = "https://webbackend.cdsc.com.np/api/myPurchase/upload/";

                // 1. Hardened Headers for the POST request
                $headers = [
                    "Authorization: " . $Authorization,
                    "Content-Type: application/json",
                    "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36",
                    "Origin: https://meroshare.cdsc.com.np",
                    "Referer: https://meroshare.cdsc.com.np/",
                    "Accept: application/json, text/plain, */*"
                ];

                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_TIMEOUT        => 15, // Protect your cron job from hanging
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_POSTFIELDS     => $data // $data is already a JSON string from your update function
                ]);

                $resp = curl_exec($curl);
                $error = curl_error($curl);
                curl_close($curl);

                // 2. Optimized Error Handling
                if ($resp === false) {
                    return "{$symbol} - Network Error: {$error} - {$date}<br>";
                }

                $json = json_decode($resp);

                // 3. Clean Logic: Using property_exists is safer than isset() for API objects
                if (isset($json->status) && !$json->status) {
                    // Handle cases where the API returns status: false
                    return "{$symbol} - " . ($json->message ?? 'Unknown Error') . " - {$date}<br>";
                }

                // Default Success Response
                $status = $json->status ?? 'Success';
                return "{$symbol} - {$status} - {$date}<br>";
            }
            //updated till here.
            function sudo_fetch_ipo_result_symbol_as_options()
            {
                $url = "https://iporesult.cdsc.com.np/result/companyShares/fileUploaded";

                // 1. Cleaned Headers: Removed dead GA cookies and updated User-Agent
                $headers = [
                    "Accept: application/json, text/plain, */*",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
                    "Origin: https://iporesult.cdsc.com.np",
                    "Referer: https://iporesult.cdsc.com.np/",
                    "Accept-Language: en-US,en;q=0.9,ne;q=0.8"
                ];

                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15, // 2. Timeout protection added
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_SSL_VERIFYHOST => false, // Kept false as requested for CDSC stability
                    CURLOPT_SSL_VERIFYPEER => false
                ]);

                $resp = curl_exec($curl);
                curl_close($curl);

                // 3. Network/API Failure Safeguard
                if ($resp === false || empty($resp)) {
                    return '<option value="">Network Error - CDSC API Down</option>';
                }

                $obj = json_decode($resp);

                // 4. Data Structure Safety (Prevents "Trying to get property of non-object" errors)
                if (!isset($obj->success) || !$obj->success || !isset($obj->body->companyShareList)) {
                    return '<option value="">No companies available currently</option>';
                }

                $company_names = array_reverse($obj->body->companyShareList);
                $options = "";

                foreach ($company_names as $body) {
                    // 5. XSS Protection: Escape API data before rendering as HTML
                    $id = htmlspecialchars($body->id ?? '', ENT_QUOTES, 'UTF-8');
                    $name = htmlspecialchars($body->name ?? '', ENT_QUOTES, 'UTF-8');
                    $scrip = htmlspecialchars($body->scrip ?? '', ENT_QUOTES, 'UTF-8');

                    $options .= '<option value="' . $id . '_' . $name . '_' . $scrip . '">' . $name . ' (' . $scrip . ')</option>';
                }

                // Cache the output in the session as in your original logic
                $_SESSION['company_names'] = $options;

                return $options;
            }


            function sudo_force_fetch_ipo_result_table($db, $id, $symbol)
            {
                // 1. Global Singleton Fallback (just in case legacy code passes a null $db)
                if (!$db) {
                    global $db;
                }

                // 2. Memory-Optimized Query: Only select the columns we actually need
                $query = "SELECT dmat_num, name FROM users";
                $result = $db->query($query);

                $table = "";
                $sn = 1;

                // -------------------------------------------------------------------------
                // OPTIMIZATION 1: Move cURL initialization OUTSIDE the loop!
                // We only open the connection to CDSC once, and reuse it for every user.
                // This utilizes HTTP Keep-Alive and is drastically faster.
                // -------------------------------------------------------------------------
                $url = "https://iporesult.cdsc.com.np/result/result/check";
                $curl = curl_init();

                $headers = [
                    "Accept: application/json, text/plain, */*",
                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
                    "Content-Type: application/json",
                    "Origin: https://iporesult.cdsc.com.np",
                    "Referer: https://iporesult.cdsc.com.np/",
                    "Accept-Language: en-US,en;q=0.9,ne;q=0.8"
                ];

                curl_setopt_array($curl, [
                    CURLOPT_URL            => $url,
                    CURLOPT_POST           => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 10, // Short timeout per request so a single hang doesn't break the whole loop
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);

                // Sanitize symbol once before the loop
                $safe_symbol = htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8');

                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $dmat_num = $row['dmat_num'];
                    $name = $row['name'];

                    // 3. Safe JSON Payload Encoding
                    $payload = json_encode([
                        "companyShareId" => $id,
                        "boid" => $dmat_num
                    ]);

                    // Inject the unique user payload into our existing cURL connection
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                    $resp = curl_exec($curl);

                    // 4. Robust Error Handling (If API drops connection mid-loop)
                    $success = false;
                    $message = "Network/API Error";

                    if ($resp !== false && !empty($resp)) {
                        $obj = json_decode($resp);
                        if (isset($obj->success)) {
                            $success = $obj->success;
                            $message = $obj->message ?? "Checked";
                        }
                    }

                    // 5. XSS Protection: Sanitize all variables before putting them in HTML
                    $safe_name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                    $safe_dmat = htmlspecialchars($dmat_num, ENT_QUOTES, 'UTF-8');
                    $safe_msg  = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

                    // 6. Clean HTML Construction
                    if ($success === true) {
                        $table .= "<tr>
                <td class=\"success\"><b>{$sn}</b></td>
                <td class=\"success\"><b>{$safe_name}</b></td>
                <td class=\"success\"><b>{$safe_dmat}</b></td>
                <td class=\"success\"><b>{$safe_symbol}</b></td>
                <td class=\"success\"><b>{$safe_msg}</b></td>
            </tr>";
                    } else {
                        $table .= "<tr>
                <td>{$sn}</td>
                <td>{$safe_name}</td>
                <td>{$safe_dmat}</td>
                <td>{$safe_symbol}</td>
                <td>{$safe_msg}</td>
            </tr>";
                    }

                    $sn++;
                }

                // Close the connection only after all users are checked
                curl_close($curl);

                return $table;
            }


            /**
             * Optimized Cron Scheduler with Drift Buffer
             * @param SQLite3 $db
             * @param string $cronKey
             * @param string|null $reason Output variable for why it skipped
             * @param int $bufferMinutes Minutes allowed for +/- execution window
             */
            function should_run_cron($db, $cronKey, &$reason = null, $bufferMinutes = 30)
            {
                $stmt = $db->prepare("SELECT * FROM system_crons WHERE cron_key = :key");
                $stmt->bindValue(':key', $cronKey, SQLITE3_TEXT);
                $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

                if (!$result) {
                    $reason = "Cron key '$cronKey' not found.";
                    return false;
                }

                if ($result['status'] !== 'enabled') {
                    $reason = "Cron status is '{$result['status']}' (expected 'enabled').";
                    return false;
                }

                if (!empty($result['last_run_at'])) {
                    $lastRun = strtotime($result['last_run_at']);
                    $frequencySeconds = ($result['frequency_minutes'] * 60);

                    // Target next run time
                    $nextRun = $lastRun + $frequencySeconds;

                    // Define the Buffer Window
                    $bufferSeconds = ($bufferMinutes * 60);
                    $windowStart = $nextRun - $bufferSeconds;
                    $now = time();

                    // LOGIC: If current time is AFTER the buffer window start, we are due.
                    // We no longer require it to be strictly GREATER than $nextRun.
                    if ($now < $windowStart) {
                        $minutesUntil = ceil(($windowStart - $now) / 60);
                        $reason = "Interval not met. Last run: {$result['last_run_at']}. Next allowed window in ~{$minutesUntil} min.";
                        return false;
                    }
                }

                $reason = "Ready to run.";
                return true;
            }

            function mark_cron_run($db, $cronKey, $status = 'SUCCESS')
            {
                $stmt = $db->prepare("UPDATE system_crons SET 
                          last_run_at = DATETIME('now', 'localtime'), 
                          last_status = :status 
                          WHERE cron_key = :key");
                $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                $stmt->bindValue(':key', $cronKey, SQLITE3_TEXT);
                $stmt->execute();
            }
            // Helper: Custom Logger
            function cron_log($db, $userId, $type, $status, $message, $step = 'INFO')
            {
                $timestamp = date('Y-m-d H:i:s');
                $logMsg = "[$timestamp] [$type] [$step] [User ID: $userId] $status: $message";
                echo $logMsg . "<br>" . PHP_EOL;

                // Optional: Save to a log table for your notification system
                $stmt = $db->prepare("INSERT INTO system_logs (user_id, log_type, status, step, message, created_at) 
                          VALUES (:uid, :type, :stat, :step, :msg, :ts)");
                $stmt->bindValue(':uid', $userId, SQLITE3_INTEGER);
                $stmt->bindValue(':type', $type, SQLITE3_TEXT);
                $stmt->bindValue(':stat', $status, SQLITE3_TEXT);
                $stmt->bindValue(':step', $step, SQLITE3_TEXT);
                $stmt->bindValue(':msg', $message, SQLITE3_TEXT);
                $stmt->bindValue(':ts', $timestamp, SQLITE3_TEXT);
                @$stmt->execute();
            }


            //Ledger Entry Function to use

            function initiateLedgerEntry($db, $dmat_num, $particular, $deposit_amt, $withdraw_amt, $type = 'IPO_ALLOTMENT')
            {

                // 1. OPTIMIZED DUPLICATE CHECK: Use SELECT 1 with LIMIT 1
                $checkStmt = $db->prepare("SELECT 1 FROM ledgers 
                               WHERE dmat_num = :dmat 
                               AND particular = :particular 
                               AND deposit_amt = :deposit 
                               AND withdraw_amt = :withdraw 
                               LIMIT 1");
                $checkStmt->bindValue(':dmat', $dmat_num);
                $checkStmt->bindValue(':particular', $particular);
                $checkStmt->bindValue(':deposit', $deposit_amt);
                $checkStmt->bindValue(':withdraw', $withdraw_amt);

                // If fetchArray returns data, a duplicate exists
                if ($checkStmt->execute()->fetchArray(SQLITE3_NUM)) {
                    return false;
                }

                // 2. BULLETPROOF BALANCE CALCULATION: Aggregate all historical data
                // This dynamically calculates the true balance, ignoring row order or deleted records.
                $balStmt = $db->prepare("SELECT SUM(deposit_amt) as total_deposit, SUM(withdraw_amt) as total_withdraw 
                             FROM ledgers 
                             WHERE dmat_num = :dmat");
                $balStmt->bindValue(':dmat', $dmat_num);
                $res = $balStmt->execute()->fetchArray(SQLITE3_ASSOC);

                // If SUM returns NULL (e.g., this is the user's very first ledger entry), fallback to 0.0
                $total_historical_deposit = isset($res['total_deposit']) ? (float)$res['total_deposit'] : 0.0;
                $total_historical_withdraw = isset($res['total_withdraw']) ? (float)$res['total_withdraw'] : 0.0;

                // 3. Calculate true mathematical balance
                $previous_balance = $total_historical_deposit - $total_historical_withdraw;

                $deposit = (float)$deposit_amt;
                $withdraw = (float)$withdraw_amt;
                $new_balance = $previous_balance + $deposit - $withdraw;

                // 4. Fetch Date and Insert
                $date = fetchNepaliDateToday(date('Y-m-d'));

                $insertStmt = $db->prepare("INSERT INTO ledgers (dmat_num, date, particular, deposit_amt, withdraw_amt, balance) 
                                VALUES (:dmat, :date, :particular, :deposit, :withdraw, :balance)");
                $insertStmt->bindValue(':dmat', $dmat_num);
                $insertStmt->bindValue(':date', $date);
                $insertStmt->bindValue(':particular', $particular);
                $insertStmt->bindValue(':deposit', $deposit);
                $insertStmt->bindValue(':withdraw', $withdraw);
                $insertStmt->bindValue(':balance', $new_balance);

                return $insertStmt->execute();
            }

            function fetchNepaliDateToday($targetADString)
            {
                // 1. Expanded Matrix Dataset (BS 2083 - BS 2103)
                $nepaliYearDays = [
                    2083 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 30],
                    2084 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2085 => [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
                    2086 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2087 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2088 => [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
                    2089 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 32],
                    2090 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2091 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2092 => [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
                    2093 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    // Added 10 Years 
                    2094 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2095 => [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
                    2096 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 32],
                    2097 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2098 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2099 => [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30],
                    2100 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 32],
                    2101 => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2102 => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 30],
                    2103 => [31, 32, 31, 32, 31, 30, 31, 29, 30, 29, 30, 30]
                ];

                // 2. Exception safety for invalid target date strings
                try {
                    $anchorAD = new DateTime("2026-04-14");
                    $targetAD = new DateTime($targetADString);
                } catch (Exception $e) {
                    return "2083.01.01";
                }

                $currentBSYear = 2083;

                // Calculate difference in days natively
                $diffDays = (int)$anchorAD->diff($targetAD)->format('%r%a');

                // Early exit for dates prior to the anchor
                if ($diffDays < 0) {
                    return "2083.01.01";
                }

                // -------------------------------------------------------------------------
                // OPTIMIZATION: Year-by-Year Fast-Forwarding
                // Instead of subtracting days month-by-month (up to 240+ iterations),
                // we jump entire years at once, reducing loops to a maximum of ~20.
                // -------------------------------------------------------------------------
                while ($diffDays >= 365 && isset($nepaliYearDays[$currentBSYear])) {
                    $daysInThisYear = array_sum($nepaliYearDays[$currentBSYear]);

                    if ($diffDays >= $daysInThisYear) {
                        $diffDays -= $daysInThisYear;
                        $currentBSYear++;
                    } else {
                        break; // Less than a full year remaining, exit year-loop
                    }
                }

                $currentBSMonth = 0; // Baishakh (Index 0)

                // -------------------------------------------------------------------------
                // Resolve the remaining days month-by-month
                // -------------------------------------------------------------------------
                if (isset($nepaliYearDays[$currentBSYear])) {
                    while ($diffDays > 0) {
                        $daysInMonth = $nepaliYearDays[$currentBSYear][$currentBSMonth];
                        if ($diffDays >= $daysInMonth) {
                            $diffDays -= $daysInMonth;
                            $currentBSMonth++;
                        } else {
                            break; // Less than a full month remaining, exit month-loop
                        }
                    }
                }

                // Days start at 1, so add the remaining diffDays to 1
                $currentBSDay = $diffDays + 1;

                // Format to YYYY.MM.DD
                return sprintf("%04d.%02d.%02d", $currentBSYear, $currentBSMonth + 1, $currentBSDay);
            }




            function deleteLedgerEntry($db, $dmat_num, $particular, $deposit_amt, $withdraw_amt)
            {
                // 1. Strict Type Casting: Prevents "100" (string) failing to match 100.0 (float)
                $deposit = (float)$deposit_amt;
                $withdraw = (float)$withdraw_amt;

                // 2. OPTIMIZATION: The "Limit 1" RowID Deletion
                // SQLite does not natively support LIMIT on DELETE statements.
                // We use a subquery to find the exact rowid of the single most recent matching transaction.
                $stmt = $db->prepare("DELETE FROM ledgers 
                          WHERE rowid = (
                              SELECT rowid FROM ledgers 
                              WHERE dmat_num = :dmat 
                              AND particular = :particular 
                              AND deposit_amt = :deposit 
                              AND withdraw_amt = :withdraw 
                              ORDER BY rowid DESC 
                              LIMIT 1
                          )");

                $stmt->bindValue(':dmat', $dmat_num);
                $stmt->bindValue(':particular', $particular);
                $stmt->bindValue(':deposit', $deposit);
                $stmt->bindValue(':withdraw', $withdraw);

                return $stmt->execute();
            }
            function updateProfitDistributionStatus($db, $dmat_num, $date, $scrip_name, $new_status = 'PENDING')
            {
                // 1. Clean the input variable in PHP (saves the database from doing it)
                $clean_scrip = trim($scrip_name);

                // 2. OPTIMIZATION: Execute the UPDATE directly
                // We skip the SELECT COUNT(*) entirely.
                $stmt = $db->prepare("UPDATE profit_distributions 
                          SET status = :status 
                          WHERE dmat_num = :dmat 
                          AND TRIM(scrip_name) = :scrip_name");

                $stmt->bindValue(':status', $new_status);
                $stmt->bindValue(':dmat', $dmat_num);
                $stmt->bindValue(':scrip_name', $clean_scrip);

                $result = $stmt->execute();

                // 3. OPTIMIZATION: Check if the update actually modified any rows
                if ($db->changes() === 0) {
                    error_log("NO MATCH FOUND for DMAT $dmat_num, Date '$date', Scrip '$clean_scrip'.");
                    return false;
                }

                return $result;
            }


            function getLatestReportAndToken($db, $prefix)
            {
                // 1. Safe Global Fallback
                // If $db is null, false, or empty, grab it from the global scope instead
                if (!$db) {
                    global $db;
                }

                // 2. OPTIMIZATION: Use SQLite's native JSON functions to sort at the database level.
                $stmt = $db->prepare("SELECT key, value 
                          FROM constant 
                          WHERE key LIKE :prefix 
                          ORDER BY json_extract(value, '$.generated_at') DESC 
                          LIMIT 1");

                $stmt->bindValue(':prefix', $prefix . '_%', SQLITE3_TEXT);
                $result = $stmt->execute();

                // 3. Fetch only the single latest row
                $row = $result->fetchArray(SQLITE3_ASSOC);

                // 4. Process the data safely if a row was found
                if ($row && !empty($row['value'])) {
                    $payload = json_decode($row['value'], true);

                    // Extract token safely
                    $keyParts = explode('_', $row['key']);
                    $latestToken = end($keyParts);

                    return [
                        'token'   => $latestToken,
                        'payload' => $payload ?: []
                    ];
                }

                // 5. Clean fallback state if no records exist
                return [
                    'token'   => '',
                    'payload' => []
                ];
            }

            /**
             * Master Dashboard Metrics Engine (Bulletproof Version)
             */
            function getDashboardMetrics($db)
            {
                $allsharesData = [];
                $edisData = [];
                $scannerData = [];
                $webData = [];
                $ipoResultData = [];

                // 1. Fetch Reports safely with Try/Catch
                try {
                    $allsharesData = getLatestReportAndToken($db, 'allshares_report') ?: [];
                    $edisData      = getLatestReportAndToken($db, 'edis_report') ?: [];
                    $scannerData   = getLatestReportAndToken($db, 'scanner_report') ?: [];
                    $webData       = getLatestReportAndToken($db, 'web_report') ?: [];
                    $ipoResultData = getLatestReportAndToken($db, 'ipo_result_report') ?: [];
                } catch (Exception $e) {
                    // Suppress missing table errors gracefully
                }

                // 2. Process Wealth safely
                $wealthLtp = $allsharesData['payload']['metrics']['total_value_ltp'] ?? 0;
                $totalAccounts = $allsharesData['payload']['metrics']['total_accounts'] ?? 0;

                // 3. Process Renewals Safely
                $urgentRenewals = [];
                $totalRenewalIssues = 0;
                $date_today = new DateTime('today');

                // Wrap the users query in try/catch in case the table doesn't exist
                try {
                    $query = "SELECT name, username, owndetails FROM users WHERE is_active=1 AND ownDetails IS NOT NULL AND owndetails != ''";
                    $results = $db->query($query);

                    if ($results) {
                        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
                            $displayName = !empty($row['name']) ? $row['name'] : $row['username'];

                            $rawDetails = html_entity_decode($row['ownDetails'], ENT_QUOTES, 'UTF-8');
                            $jsonStart = strpos($rawDetails, '{');

                            if ($jsonStart !== false) {
                                $detailsData = json_decode(substr($rawDetails, $jsonStart), true);

                                $checks = [
                                    'MeroShare' => $detailsData['meroShareExpiredDate'] ?? null,
                                    'DMAT'      => $detailsData['dematExpiredDate'] ?? null
                                ];

                                foreach ($checks as $type => $dateStr) {
                                    if (empty($dateStr)) continue;

                                    $dateOnly = explode(' ', trim($dateStr))[0];

                                    try {
                                        $date_expiry = new DateTime($dateOnly);
                                        $days__ = (int)$date_today->diff($date_expiry)->format('%r%a');

                                        if ($days__ < 60) {
                                            $totalRenewalIssues++;
                                            $urgentRenewals[] = [
                                                'name' => $displayName,
                                                'type' => $type,
                                                'days' => $days__
                                            ];
                                        }
                                    } catch (Exception $e) {
                                        continue;
                                    }
                                }
                            }
                        }

                        if (count($urgentRenewals) > 1) {
                            usort($urgentRenewals, function ($a, $b) {
                                return $a['days'] <=> $b['days'];
                            });
                        }
                    }
                } catch (Exception $e) {
                    // Suppress missing 'users' table error gracefully
                }

                // 4. Process EDIS safely
                $edisErrors = 0;
                $edisTransfers = 0;

                // Ensure companyPayload is actually an array before looping to prevent string loop crashes
                if (!empty($edisData['payload']['companyPayload']) && is_array($edisData['payload']['companyPayload'])) {
                    foreach ($edisData['payload']['companyPayload'] as $data) {
                        $edisTransfers += $data['report']['transfers_done'] ?? 0;
                        if (!empty($data['report']['danger_obligations']) || !empty($data['report']['errors'])) {
                            $edisErrors++;
                        }
                    }
                }

                // Safely enforce that scanner_logs is always an array
                $rawScannerLogs = $scannerData['payload']['processing_report'] ?? [];
                $safeScannerLogs = is_array($rawScannerLogs) ? $rawScannerLogs : [];

                // 5. Structure payload
                return [
                    'tokens' => [
                        'allshares'  => $allsharesData['token'] ?? null,
                        'edis'       => $edisData['token'] ?? null,
                        'renewal'    => 'LIVE',
                        'scanner'    => $scannerData['token'] ?? null,
                        'web'        => $webData['token'] ?? null,
                        'ipoResults' => $ipoResultData['token'] ?? null
                    ],
                    'wealth' => [
                        'ltp'      => $wealthLtp,
                        'accounts' => $totalAccounts
                    ],
                    'renewals' => [
                        'total'  => $totalRenewalIssues,
                        'urgent' => $urgentRenewals
                    ],
                    'edis' => [
                        'transfers' => $edisTransfers,
                        'errors'    => $edisErrors
                    ],
                    'scanner_logs' => $safeScannerLogs
                ];
            }
            function getIpoResultTableDashboard($db)
            {
                $ipoDataSummary = [];

                try {
                    // 1. OPTIMIZATION: Database-Level Aggregation
                    // We group by 'scrip' and use Conditional SUM to count the statuses dynamically.
                    // We also handle the sorting (ORDER BY) right here in the SQL.
                    $query = "
            SELECT 
                scrip, 
                MAX(companyName) as companyName, 
                MAX(last_updated) as last_seen,
                COUNT(dmat_num) as total_actions,
                SUM(CASE WHEN LOWER(statusName) LIKE '%not allot%' THEN 1 ELSE 0 END) as c_not_allot,
                SUM(CASE WHEN LOWER(statusName) LIKE '%allot%' AND LOWER(statusName) NOT LIKE '%not allot%' THEN 1 ELSE 0 END) as c_allot,
                SUM(CASE WHEN LOWER(statusName) LIKE '%verified%' AND LOWER(statusName) NOT LIKE '%unverified%' THEN 1 ELSE 0 END) as c_veri,
                SUM(CASE WHEN LOWER(statusName) LIKE '%reject%' OR LOWER(statusName) LIKE '%cancel%' THEN 1 ELSE 0 END) as c_rej,
                SUM(CASE WHEN LOWER(statusName) LIKE '%reapply%' OR LOWER(statusName) LIKE '%fail%' OR LOWER(statusName) LIKE '%error%' THEN 1 ELSE 0 END) as c_reapply
            FROM ipo_results
            GROUP BY scrip
            ORDER BY last_seen DESC, total_actions DESC
        ";

                    $stmtIpo = $db->query($query);

                    if ($stmtIpo) {
                        // 2. Loop through the tiny, pre-calculated results
                        while ($row = $stmtIpo->fetchArray(SQLITE3_ASSOC)) {
                            $scrip = $row['scrip'];

                            // Fallback: Anything that isn't expressly caught above is mathematically "Unverified"
                            // This perfectly mimics your original if/else fallback waterfall logic.
                            $c_unverified = $row['total_actions'] - (
                                $row['c_not_allot'] +
                                $row['c_allot'] +
                                $row['c_veri'] +
                                $row['c_rej'] +
                                $row['c_reapply']
                            );

                            $ipoDataSummary[$scrip] = [
                                'scrip'         => $scrip,
                                'companyName'   => $row['companyName'] ?? 'Unknown',
                                'last_seen'     => $row['last_seen'] ?? '2000-01-01',
                                'metrics'       => [
                                    'Verified'     => (int)$row['c_veri'],
                                    'Unverified'   => (int)$c_unverified,
                                    'Rejected'     => (int)$row['c_rej'],
                                    'Allotted'     => (int)$row['c_allot'],
                                    'Not Allotted' => (int)$row['c_not_allot'],
                                    'Reapply'      => (int)$row['c_reapply']
                                ],
                                'total_actions' => (int)$row['total_actions']
                            ];
                        }
                    }
                } catch (Exception $e) {
                    error_log("IPO Result Dashboard Error: " . $e->getMessage());
                }

                // 3. Return the exact same output structure
                return [
                    // array_slice preserves the keys and limits to the top 5
                    'top_5' => array_slice($ipoDataSummary, 0, 5, true),
                    'total_active' => count($ipoDataSummary)
                ];
            }


            function alertMessage($type, $message, $includeTimestamp = false)
            {
                // Normalize input: Lowercase and strip all spaces/underscores 
                // This makes 'not_alloted', 'Not Allotted', and 'notalloted' match perfectly
                $type = strtolower(preg_replace('/[^a-z0-9]/i', '', $type));

                $map = [
                    // Standard Statuses & Aliases
                    'success' => "✅",
                    'suc' => "✅",
                    'succ' => "✅",
                    'ok' => "✅",
                    'done' => "✅",
                    'error'   => "❌",
                    'err' => "❌",
                    'fail' => "❌",
                    'failed' => "❌",
                    'fatal' => "❌",
                    'warning' => "⚠️",
                    'warn' => "⚠️",
                    'alert' => "⚠️",
                    'notice'  => "ℹ️",
                    'info' => "ℹ️",
                    'note' => "ℹ️",
                    'log' => "ℹ️",
                    'denied'  => "🚨",
                    'danger' => "🚨",
                    'critical' => "🚨",
                    'block' => "🚨",

                    // IPO Result Statuses
                    'allotted'     => "🎯",
                    'alloted' => "🎯",
                    'allot' => "🎯",
                    'win' => "🎯",
                    'notallotted'  => "⭕",
                    'notalloted' => "⭕",
                    'none' => "⭕",
                    'miss' => "⭕",
                    'notallot' => "⭕",
                    'rejected'     => "🚫",
                    'reject' => "🚫",
                    'cancel' => "🚫",
                    'verified'     => "☑️",
                    'veri' => "☑️",
                    'verify' => "☑️",
                    'unverified'   => "⏳",
                    'unveri' => "⏳",
                    'pending' => "⏳",
                    'neverchecked' => "❓",
                    'never' => "❓",
                    'unknown' => "❓",

                    // HamroShare Specific
                    'applied' => "🟣",
                    'apply' => "🟣",
                    'skipped' => "⏭️",
                    'skip' => "⏭️",
                    'bypass' => "⏭️",
                    'reapply' => "🔄",
                    'retry' => "🔄",
                    'refresh' => "🔄",
                    'action'  => "⚡",
                    'req' => "⚡",
                    'prompt' => "⚡",

                    // State Modifiers
                    'locked'   => "🔒",
                    'lock' => "🔒",
                    'unlocked' => "🔓",
                    'unlock' => "🔓",

                    // Process Flow
                    'init'      => "⚙️",
                    'initialize' => "⚙️",
                    'setup' => "⚙️",
                    'boot' => "⚙️",
                    'start'     => "🚀",
                    'starting' => "🚀",
                    'begin' => "🚀",
                    'launch' => "🚀",
                    'run' => "🚀",
                    'waiting'   => "⏳",
                    'wait' => "⏳",
                    'pause' => "⏳",
                    'delay' => "⏳",
                    'sleep' => "⏳",
                    'completed' => "🏁",
                    'complete' => "🏁",
                    'finish' => "🏁",
                    'finished' => "🏁",
                    'end' => "🏁",

                    // Report & Dashboard Sections
                    'dashboard' => "📊",
                    'summary' => "📊",
                    'metrics' => "📊",
                    'stats' => "📊",
                    'report'    => "📋",
                    'ledger' => "📋",
                    'document' => "📋",
                    'file' => "📋",
                    'wealth'    => "💰",
                    'finance' => "💰",
                    'money' => "💰",
                    'portfolio' => "💰",
                    'users'     => "👥",
                    'accounts' => "👥",
                    'profiles' => "👥",
                    'clients' => "👥",
                    'user'      => "👤",
                    'profile' => "👤",
                    'account' => "👤",
                    'history'   => "📜",
                    'log' => "📜",
                    'archive' => "📜",
                    'records' => "📜",
                    'vault'     => "🗄️",
                    'database' => "🗄️",
                    'db' => "🗄️",
                    'data' => "🗄️",
                    'time'      => "🕒",
                    'date' => "🕒",
                    'clock' => "🕒",
                    'schedule' => "🕒",
                    'security'  => "🔐",
                    'auth' => "🔐",
                    'protect' => "🔐",
                    'settings'  => "🛠️",
                    'config' => "🛠️",
                    'tools' => "🛠️",
                    'matrix'    => "🧩",
                    'grid' => "🧩",
                    'table' => "🧩",
                    'company'   => "🏢",
                    'business' => "🏢",
                    'scrip' => "🏢",
                    'organization' => "🏢",
                    'wallet'    => "👛",
                    'balance' => "👛",
                    'funds' => "👛",
                ];

                // Grab the mapped icon or default to the orange diamond
                $icon = $map[$type] ?? '🔸';

                // Format the timestamp if requested
                $timestamp = $includeTimestamp ? "[" . date('Y-m-d H:i:s') . "] " : "";

                // Smart Line Endings: PHP_EOL for log files, \n for API messages
                $lineEnding = $includeTimestamp ? PHP_EOL : "\n";

                return $timestamp . $icon . " " . $message . $lineEnding;
            }

            /**
             * High-Precision Bikram Sambat (B.S.) to Gregorian (A.D.) Date Converter
             * Matches standard official Panchanga mapping rules.
             *
             * @param string $bsDateStr Format: YYYY-MM-DD (e.g., "2083-03-32")
             * @return string|null Format: YYYY-MM-DD in A.D., or null if out of bounds/invalid
             */
            function bs2ad($bsDateStr)
            {
                // Static ensures the array is only allocated in memory once per PHP lifecycle
                static $calendarMap = [
                    // Historic Mappings (2070 - 2079)
                    2070 => ['anchor' => '2013-04-14', 'days' => [31, 31, 31, 32, 31, 31, 29, 30, 30, 29, 30, 30]],
                    2071 => ['anchor' => '2014-04-14', 'days' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30]],
                    2072 => ['anchor' => '2015-04-14', 'days' => [31, 32, 31, 32, 31, 30, 30, 29, 30, 29, 30, 30]],
                    2073 => ['anchor' => '2016-04-13', 'days' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31]],
                    2074 => ['anchor' => '2017-04-14', 'days' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31]],
                    2075 => ['anchor' => '2018-04-14', 'days' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30]],
                    2076 => ['anchor' => '2019-04-14', 'days' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30]],
                    2077 => ['anchor' => '2020-04-13', 'days' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 31]],
                    2078 => ['anchor' => '2021-04-14', 'days' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 29, 31]],
                    2079 => ['anchor' => '2022-04-14', 'days' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30]],

                    // Original Mappings (2080 - 2095)
                    2080 => ['anchor' => '2023-04-14', 'days' => [31, 32, 31, 31, 31, 31, 30, 29, 30, 29, 30, 30]],
                    2081 => ['anchor' => '2024-04-12', 'days' => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 30]],
                    2082 => ['anchor' => '2025-04-13', 'days' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 29]],
                    2083 => ['anchor' => '2026-04-13', 'days' => [31, 31, 31, 32, 31, 31, 30, 29, 30, 29, 30, 30]],
                    2084 => ['anchor' => '2027-04-14', 'days' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 29, 30, 30]],
                    2085 => ['anchor' => '2028-04-13', 'days' => [31, 32, 31, 32, 31, 31, 29, 30, 29, 30, 30, 30]],
                    2086 => ['anchor' => '2029-04-13', 'days' => [31, 31, 32, 31, 31, 31, 30, 29, 30, 29, 30, 30]],
                    2087 => ['anchor' => '2030-04-14', 'days' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30]],
                    2088 => ['anchor' => '2031-04-14', 'days' => [30, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 29]],
                    2089 => ['anchor' => '2032-04-12', 'days' => [31, 31, 32, 31, 32, 30, 30, 29, 30, 29, 30, 30]],
                    2090 => ['anchor' => '2033-04-13', 'days' => [31, 31, 32, 32, 31, 30, 30, 30, 29, 30, 29, 30]],
                    2091 => ['anchor' => '2034-04-14', 'days' => [31, 31, 31, 32, 32, 31, 29, 30, 30, 29, 30, 29]],
                    2092 => ['anchor' => '2035-04-14', 'days' => [31, 32, 31, 31, 32, 30, 31, 30, 29, 30, 29, 29]],
                    2093 => ['anchor' => '2036-04-13', 'days' => [31, 32, 31, 32, 31, 30, 30, 30, 29, 30, 30, 29]],
                    2094 => ['anchor' => '2037-04-13', 'days' => [31, 31, 32, 31, 32, 30, 30, 30, 29, 30, 29, 30]],
                    2095 => ['anchor' => '2038-04-14', 'days' => [31, 31, 32, 32, 31, 30, 30, 29, 30, 29, 30, 30]]
                ];

                // High-speed string parsing
                $parts = explode('-', trim($bsDateStr));
                if (count($parts) !== 3) {
                    return null;
                }

                $bsYear  = (int)$parts[0];
                $bsMonth = (int)$parts[1];
                $bsDay   = (int)$parts[2];

                // Bounds check on year
                if (!isset($calendarMap[$bsYear])) {
                    return null;
                }

                $yearData = $calendarMap[$bsYear];

                // Strict bounds check to prevent an invalid month (like month 13)
                if ($bsMonth < 1 || $bsMonth > 12) {
                    return null;
                }

                // === NEW CLAMPING LOGIC ===
                // Find the actual maximum number of days for this specific month
                $maxDaysInMonth = $yearData['days'][$bsMonth - 1];

                // If MeroShare asks for a day less than 1, snap it to 1
                if ($bsDay < 1) {
                    $bsDay = 1;
                }
                // If MeroShare asks for day 32, but the month only has 31 days, snap it down to 31
                elseif ($bsDay > $maxDaysInMonth) {
                    $bsDay = $maxDaysInMonth;
                }

                // High-performance total day offset calculation
                $totalDaysOffset = array_sum(array_slice($yearData['days'], 0, $bsMonth - 1)) + ($bsDay - 1);

                // Fast procedural date mutation
                return date('Y-m-d', strtotime($yearData['anchor'] . " + $totalDaysOffset days"));
            }

            /**
             * Helper function to parse NotificationManager results and log errors
             */
            function processBackupResult($results, string $type, array &$errors, SQLite3 $db): void
            {

                // Internal helper closure to prevent repeating the same 3 lines of error logic
                $handleError = function (string $errMessage) use (&$errors, $db) {
                    echo alertMessage('error', $errMessage, true);
                    cron_log($db, 'SYSTEM', 'ERROR', 'BACKUP CRON', $errMessage, 'CRON EXCEPTION');
                    $errors[] = $errMessage;
                };

                // 1. Handle String Results (e.g., .env disabled or missing credentials)
                if (is_string($results)) {
                    $handleError("$type Skipped/Failed: " . $results);
                    return;
                }

                // 2. Handle Array Results (e.g., multiple Telegram Chat IDs)
                if (is_array($results)) {
                    foreach ($results as $id => $res) {
                        // Use stripos for faster, native case-insensitive checking
                        if (!$res || (is_string($res) && stripos($res, 'error') !== false)) {
                            $reason = is_string($res) ? $res : 'API Rejected File (possibly >50MB)';
                            $handleError("$type Telegram Upload Failed to ID $id: " . $reason);
                        } else {
                            echo alertMessage('success', "$type successfully uploaded to Telegram ID $id.", true);
                        }
                    }
                    return;
                }

                // 3. Fallback for unexpected data types (null, bool false, etc.)
                $handleError("$type Telegram Upload Failed: Unexpected error payload.");
            }

            function getSuggestedPassGuardian($current, $name = 'Mero Share')
            {
                $parts = explode(' ', trim($name));

                // 1. Extract the first part and strip out any punctuation (like the period in "Md.")
                $rawFirst = preg_replace('/[^a-zA-Z]/', '', $parts[0] ?? '');

                // 2. Enforce the 3-letter minimum rule
                if (strlen($rawFirst) >= 3) {
                    // Force exactly one capital letter at the start (e.g., "aMiT" becomes "Amit")
                    $prefix = ucfirst(strtolower($rawFirst));
                } else {
                    // Fallback for names like "Md", "Om", or empty strings
                    $prefix = 'Mero';
                }

                // 3. Password toggle logic
                if ($current === $prefix . '@1234') {
                    return $prefix . '@4321';
                }

                return $prefix . '@1234';
            }

            function fetchMeroShareOwnDetails_v2($token, $uid, $db)
            {
                $ch = curl_init('https://webbackend.cdsc.com.np/api/meroShare/ownDetail/');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER => [
                        "Authorization: $token",
                        "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36"
                    ]
                ]);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_code == 200 && $response) {
                    $safeJson = $db->escapeString($response);
                    $db->exec("UPDATE users SET ownDetails = '$safeJson', last_updated_owndetails = DATETIME('now', 'localtime') WHERE id = $uid");
                    return json_decode($response, true);
                }
                return false;
            }

            function executeMeroSharePasswordRotation_v2($token, $oldPass, $newPass)
            {
                $ch = curl_init('https://webbackend.cdsc.com.np/api/meroShare/changePassword/');
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_POSTFIELDS => json_encode([
                        "oldPassword" => $oldPass,
                        "newPassword" => $newPass,
                        "confirmPassword" => $newPass
                    ]),
                    CURLOPT_HTTPHEADER => [
                        "Authorization: $token",
                        "Content-Type: application/json",
                        "User-Agent: Mozilla/5.0"
                    ]
                ]);

                $api_resp = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $resData = json_decode($api_resp, true);
                $apiMsg = $resData['message'] ?? 'No response message';

                return [
                    'success' => ($http_code == 200 || stripos($apiMsg, 'successfully') !== false),
                    'message' => $apiMsg,
                    'http_code' => $http_code
                ];
            }
            /**
             * Converts a database integer ID into a random-looking 5-char string
             */
            function encodeShortId($id)
            {
                // ---------------------------------------------------------
                // SHORT URL ENCRYPTION (s.php for Telegram/WhatsApp)
                // ---------------------------------------------------------
                // The Prime scatters sequential numbers widely (Requires a prime number)
                $prime = (int)($_ENV['URL_MASK_PRIME'] ?? getenv('URL_MASK_PRIME') ?: 10007);

                // The Salt Mask scrambles the bits
                $salt = (int)($_ENV['URL_MASK_SALT'] ?? getenv('URL_MASK_SALT') ?: 1234567);

                $obfuscated = ($id * $prime) ^ $salt;

                // Convert to Base36 (0-9, a-z) for a clean, URL-safe string
                return base_convert($obfuscated, 10, 36);
            }

            /**
             * Reverses the 5-char string back into the exact database integer ID
             */
            function decodeShortId($hash)
            {
                // The Prime scatters sequential numbers widely (Requires a prime number)
                $prime = (int)($_ENV['URL_MASK_PRIME'] ?? getenv('URL_MASK_PRIME') ?: 10007);

                // The Salt Mask scrambles the bits
                $salt = (int)($_ENV['URL_MASK_SALT'] ?? getenv('URL_MASK_SALT') ?: 1234567);

                // Reverse the Base36 string back to a large integer
                $obfuscated = (int) base_convert($hash, 36, 10);

                // Reverse the XOR mask and divide by the prime
                $id = ($obfuscated ^ $salt) / $prime;

                return (int) $id;
            }
            /**
             * Compresses a long application URL into a compact tracking ID link
             * * @param SQLite3 $db
             * @param string $longUrl
             * @return string Compressed routing URL
             */
            function getShortUrl($db, $longUrl)
            {
                $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'https://localhost:8000';

                $insertStmt = $db->prepare("INSERT INTO short_urls (long_url) VALUES (:long_url)");
                $insertStmt->bindValue(':long_url', $longUrl, SQLITE3_TEXT);
                $insertStmt->execute();

                // Grab the predictable database ID (e.g., 12)
                $shortId = $db->lastInsertRowID();

                // Scramble it into a secure hash (e.g., "51evp")
                $secureHash = encodeShortId($shortId);

                return "{$baseUrl}/s.php?i={$secureHash}";
            }

            function getSafeLogOutput($filePath, $linesToFetch = 100)
            {
                if (!file_exists($filePath)) {
                    return "Log file not found or empty.";
                }

                // Safety Net: If the log somehow bypasses your 200KB cron limit and reaches 5MB, stop it from crashing PHP
                if (filesize($filePath) > 5242880) {
                    return "⚠️ Log file is too large (>5MB) to display safely in browser. Please clear it.";
                }

                // Read file into an array (ignoring empty lines)
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines === false) {
                    return "Error reading log file.";
                }

                // Slice the last 100 lines, and reverse the array so the newest logs are at the top
                $lastLines = array_slice($lines, -$linesToFetch);
                $reversedLines = array_reverse($lastLines);

                // Sanitize for XSS protection and output
                return htmlspecialchars(implode("\n", $reversedLines));
            }

            /**
             * Extracts and calculates advanced technicals from the local JSON cache.
             * Returns global stats and an array of analyzed scrips.
             */
            function get_market_signals($cacheDir)
            {
                $analyzedScrips = [];
                $globalStats = [
                    'total_tracked' => 0,
                    'total_strong_sell' => 0,
                    'avg_roi' => 0,
                    'highest_drawdown' => 0
                ];

                if (!is_dir($cacheDir)) return ['stats' => $globalStats, 'scrips' => []];

                $files = glob($cacheDir . '/*.json');
                $totalProfitSum = 0;

                foreach ($files as $file) {
                    $scripCode = basename($file, '.json');
                    $cacheJson = json_decode(file_get_contents($file), true);
                    $history = $cacheJson['history'] ?? [];
                    if (empty($history)) continue;

                    // Base Metrics
                    $wacc = 100.00;
                    $ltp = (float)$history[0]['close'];
                    $dailyChange = (float)$history[0]['percent_change'];
                    $volume = (int)$history[0]['volume'];
                    $lastDate = $history[0]['f_date'];

                    $profitPct = (($ltp - $wacc) / $wacc) * 100;
                    $highestHigh = max(array_column($history, 'high'));
                    $drawdown = (($highestHigh - $ltp) / $highestHigh) * 100;

                    // Advanced Technicals: Simple Moving Averages (SMA)
                    $sma5 = count($history) >= 5 ? array_sum(array_column(array_slice($history, 0, 5), 'close')) / 5 : $ltp;
                    $sma10 = count($history) >= 10 ? array_sum(array_column(array_slice($history, 0, 10), 'close')) / 10 : $ltp;
                    $trend = ($ltp >= $sma5) ? 'BULLISH' : 'BEARISH';

                    // Chart Data (Last 14 days, reversed for chronological left-to-right rendering)
                    $chartDataObj = array_reverse(array_slice($history, 0, 14));
                    $chartDates = array_column($chartDataObj, 'f_date');
                    $chartPrices = array_column($chartDataObj, 'close');

                    // Risk Engine
                    $score = 0;
                    if ($profitPct >= 100.0) $score += 40;
                    if ($drawdown >= 10.0) $score += 40;
                    if ($ltp < $sma5) $score += 15; // Break down below weekly trend
                    if (isset($history[1]) && $history[0]['percent_change'] < 0 && $history[1]['percent_change'] < 0) $score += 15;

                    // Globals
                    $globalStats['total_tracked']++;
                    $totalProfitSum += $profitPct;
                    if ($score >= 70) $globalStats['total_strong_sell']++;
                    if ($drawdown > $globalStats['highest_drawdown']) $globalStats['highest_drawdown'] = $drawdown;

                    $analyzedScrips[] = [
                        'scrip'       => $scripCode,
                        'ltp'         => $ltp,
                        'dailyChange' => $dailyChange,
                        'profitPct'   => $profitPct,
                        'drawdown'    => $drawdown,
                        'peak'        => $highestHigh,
                        'sma5'        => $sma5,
                        'trend'       => $trend,
                        'score'       => $score,
                        'volume'      => $volume,
                        'lastDate'    => $lastDate,
                        'memoryDays'  => count($history),
                        'chartDates'  => json_encode($chartDates),
                        'chartPrices' => json_encode($chartPrices)
                    ];
                }

                if ($globalStats['total_tracked'] > 0) $globalStats['avg_roi'] = $totalProfitSum / $globalStats['total_tracked'];

                usort($analyzedScrips, function ($a, $b) {
                    if ($b['score'] === $a['score']) return $b['profitPct'] <=> $a['profitPct'];
                    return $b['score'] <=> $a['score'];
                });

                return ['stats' => $globalStats, 'scrips' => $analyzedScrips];
            }

            /**
             * Renders the HTML cards dynamically. Call this from any page.
             */
            function render_signal_cards($analyzedScrips)
            {
                if (empty($analyzedScrips)) {
                    echo '<div class="bg-[#161b22] border border-slate-800 rounded-2xl p-12 text-center col-span-full">
                <i class="fas fa-satellite-dish text-4xl text-slate-600 mb-3"></i>
                <h3 class="text-slate-400 font-medium">Matrix Offline: No Market Cache Found</h3>
              </div>';
                    return;
                }

                foreach ($analyzedScrips as $scrip) {
                    $verdict = "HOLD POSITION";
                    $colorTheme = "emerald";
                    $bgTheme = "bg-emerald-500";
                    $textTheme = "text-emerald-400";
                    $hexColor = "#10b981";

                    if ($scrip['score'] >= 70) {
                        $verdict = "STRONG SELL";
                        $colorTheme = "rose";
                        $bgTheme = "bg-rose-500";
                        $textTheme = "text-rose-500";
                        $hexColor = "#f43f5e";
                    } elseif ($scrip['score'] >= 40) {
                        $verdict = "REDUCE EXPOSURE";
                        $colorTheme = "amber";
                        $bgTheme = "bg-amber-500";
                        $textTheme = "text-amber-400";
                        $hexColor = "#f59e0b";
                    }

                    $dailyColor = $scrip['dailyChange'] >= 0 ? 'text-emerald-400' : 'text-rose-400';
                    $trendIcon = $scrip['trend'] === 'BULLISH' ? '<i class="fas fa-arrow-trend-up text-emerald-400"></i>' : '<i class="fas fa-arrow-trend-down text-rose-400"></i>';

                    echo '
        <div class="matrix-card group bg-[#161b22] border border-slate-800 rounded-2xl overflow-hidden hover:border-slate-600 transition-colors duration-300 relative flex flex-col" data-scrip="' . strtolower($scrip['scrip']) . '">
            <div class="h-1 w-full ' . $bgTheme . '"></div>
            
            <div class="p-5 flex-1 flex flex-col">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h2 class="text-2xl font-black text-white tracking-wider">' . $scrip['scrip'] . '</h2>
                        <div class="text-[10px] text-slate-500 font-mono mt-0.5">L.UPD: ' . $scrip['lastDate'] . '</div>
                    </div>
                    <div class="px-3 py-1 rounded-full bg-slate-800/50 border border-slate-700/50 text-[10px] font-bold uppercase tracking-widest ' . $textTheme . '">
                        ' . $verdict . '
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-4">
                    <div class="bg-slate-900/50 rounded-xl p-3 border border-slate-800/50">
                        <div class="text-[10px] text-slate-500 uppercase font-bold mb-1">Current LTP</div>
                        <div class="text-xl font-black font-mono text-white">Rs. ' . number_format($scrip['ltp'], 2) . '</div>
                        <div class="text-xs font-mono font-bold ' . $dailyColor . ' mt-0.5">
                            ' . ($scrip['dailyChange'] > 0 ? '+' : '') . number_format($scrip['dailyChange'], 2) . '%
                        </div>
                    </div>
                    <div class="bg-slate-900/50 rounded-xl p-3 border border-slate-800/50">
                        <div class="text-[10px] text-slate-500 uppercase font-bold mb-1">Total ROI</div>
                        <div class="text-xl font-black font-mono ' . ($scrip['profitPct'] >= 0 ? 'text-emerald-400' : 'text-rose-400') . '">
                            +' . number_format($scrip['profitPct'], 1) . '%
                        </div>
                        <div class="text-xs text-slate-600 font-mono mt-0.5 font-bold">WACC: 100.00</div>
                    </div>
                </div>

                <div class="w-full h-24 mb-4 bg-slate-900/30 rounded-xl p-2 border border-slate-800/30 relative">
                    <canvas class="signal-chart" data-color="' . $hexColor . '" data-dates=\'' . $scrip['chartDates'] . '\' data-prices=\'' . $scrip['chartPrices'] . '\'></canvas>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-2 mb-5 text-xs font-mono">
                    <div class="flex justify-between border-b border-slate-800/50 pb-1">
                        <span class="text-slate-500">SMA-5</span>
                        <span class="text-slate-300">' . number_format($scrip['sma5'], 2) . '</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-800/50 pb-1">
                        <span class="text-slate-500">Trend</span>
                        <span class="font-bold">' . $trendIcon . ' ' . $scrip['trend'] . '</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-800/50 pb-1">
                        <span class="text-slate-500">Peak(60d)</span>
                        <span class="text-slate-300">' . number_format($scrip['peak'], 2) . '</span>
                    </div>
                    <div class="flex justify-between border-b border-slate-800/50 pb-1">
                        <span class="text-slate-500">Drawdown</span>
                        <span class="text-amber-400 font-bold">-' . number_format($scrip['drawdown'], 1) . '%</span>
                    </div>
                </div>

                <div class="mt-auto">
                    <div class="flex justify-between text-[10px] mb-1.5">
                        <span class="text-slate-500 font-bold uppercase tracking-wider">Algorithmic Risk Score</span>
                        <span class="' . $textTheme . ' font-bold font-mono text-xs">' . $scrip['score'] . '/100</span>
                    </div>
                    <div class="w-full bg-slate-800 rounded-full h-1.5 mb-2">
                        <div class="' . $bgTheme . ' h-1.5 rounded-full transition-all duration-1000" style="width: ' . min(100, max(5, $scrip['score'])) . '%"></div>
                    </div>
                    <div class="text-right text-[9px] text-slate-600 font-mono tracking-widest uppercase">
                        V: ' . number_format($scrip['volume']) . ' | MEM: ' . $scrip['memoryDays'] . 'D
                    </div>
                </div>
            </div>
        </div>';
                }
            }

            /**
             * Extracts the latest semantic version from a Markdown changelog string.
             */
            function parse_version_from_changelog($content)
            {
                if ($content !== false && preg_match_all('/^##\s+(.+)$/m', $content, $matches)) {
                    foreach ($matches[1] as $header) {
                        if (preg_match('/([0-9]+\.[0-9]+\.[0-9]+)/', $header, $version_match)) {
                            return $version_match[1]; // Returns the first found version (e.g., "1.0.2")
                        }
                    }
                }
                return '1.0.0'; // Safe fallback
            }

            /**
             * Reads a changelog file from either a local path or a remote URL.
             */
            function get_version_from_source($source)
            {
                // If it's a remote URL, fetch it securely with cURL
                if (filter_var($source, FILTER_VALIDATE_URL)) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $source);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Hamroshare-AppImage');
                    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                    $content = curl_exec($ch);
                    curl_close($ch);
                    return parse_version_from_changelog($content);
                }

                // Otherwise, handle it as a local file path
                if (file_exists($source)) {
                    $content = file_get_contents($source);
                    return parse_version_from_changelog($content);
                }

                return '1.0.0';
            }

            /**
             * Main update checker leveraging local and remote Markdown files.
             */
            function check_for_updates()
            {
                $cache_file = __DIR__ . '/.version_cache.json';
                $cache_time = 14400; // 4 hours

                // 1. Return cache if it is still fresh
                if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_time) {
                    return json_decode(file_get_contents($cache_file), true);
                }

                // 2. Resolve paths dynamically
                $local_path = __DIR__ . '/CHANGELOG.md';
                $remote_url = "https://raw.githubusercontent.com/adonisamitsah/hamroshare/main/CHANGELOG.md";

                $local_version  = get_version_from_source($local_path);
                $remote_version = get_version_from_source($remote_url);

                $update_available = version_compare($remote_version, $local_version, '>');

                // 3. Cache the calculated metrics
                $cache_data = [
                    'update_available' => $update_available,
                    'local_version'    => $local_version,
                    'latest_version'   => $remote_version,
                    'checked_at'       => time()
                ];
                file_put_contents($cache_file, json_encode($cache_data));

                return $cache_data;
            }

                ?>