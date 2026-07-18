<?php
include 'config.php'; // Get all the configurations, DB connection, environtment variables, and check session login status
$db = Database::getConnection(); // Get the DB connection
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputPassword = $_POST['password'] ?? '';

    if (password_verify($inputPassword, MASTER_HASH)) {
        session_regenerate_id(true);
        
        $_SESSION['master_logged_in'] = true;
        $_SESSION['last_activity'] = time();
        
        // 1. Initialize fallback destination routing
        $redirectUrl = 'index.php'; 
        
        // 2. Check if a specific target destination was cached by config.php
        if (!empty($_SESSION['redirect_to'])) {
            $capturedUrl = $_SESSION['redirect_to'];
            
            // Defensive validation: Ensure the path is relative or doesn't escape to an external host
            $parsed = parse_url($capturedUrl);
            
            // If the URL has no host (meaning it's a local relative path) OR its host matches your local environment
            if (!isset($parsed['host']) || $parsed['host'] === $_SERVER['HTTP_HOST']) {
                $redirectUrl = $capturedUrl;
            }
            
            // Clear the memory register so it doesn't persist on subsequent logins
            unset($_SESSION['redirect_to']);
        }
        
        // 3. Dispatch redirect execution
        header("Location: " . $redirectUrl);
        exit;
    } else {
        $error = "Invalid Master Credentials";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>HamroShare - Meroshare Automation Application</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
        <!-- Strict Anti-Indexing -->
<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
<meta name="googlebot" content="noindex, nofollow">
<meta name="bingbot" content="noindex, nofollow">

<!-- Security & Privacy -->
<meta name="referrer" content="no-referrer">
<meta http-equiv="X-Content-Type-Options" content="nosniff">
<meta name="theme-color" content="#0d1117">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

<!-- Favicon -->
<link rel="icon" type="image/png" href="favicon.png">
</head>
<body class="bg-[#0f1113] h-screen flex items-center justify-center antialiased">
    
    <div class="w-full max-w-md p-8">
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl shadow-xl shadow-blue-900/40 mb-4">
    <img src="favicon.png" alt="Logo" class="w-10 h-10 object-contain">
</div>
            <h2 class="text-2xl font-bold text-white">Hamroshare</h2>
            <p class="text-slate-500 text-sm mt-2">Authorized Access Only</p>
        </div>

        <form method="POST" class="bg-[#161b22] border border-slate-800 p-8 rounded-3xl shadow-2xl">
            <?php if($error): ?>
                <div class="mb-6 p-3 bg-rose-500/10 border border-rose-500/20 text-rose-500 text-xs font-bold rounded-xl text-center">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="mb-6">
                <label class="block text-slate-400 text-[10px] uppercase font-black tracking-widest mb-2">Master Password</label>
                <div class="relative">
                    <input type="password" name="password" required autofocus
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl py-3 px-4 text-white focus:ring-2 focus:ring-blue-600 focus:border-transparent transition-all outline-none">
                </div>
            </div>

            <button type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-blue-900/20">
                Verify Identity
            </button>
        </form>

        <p class="text-center mt-8 text-slate-600 text-[10px] uppercase tracking-tighter">
            &copy; <?php echo date('Y'); ?> Hamroshare Admin Panel. All rights reserved.
        </p>
    </div>

</body>
</html>