<?php
// config.php
// --- INSTALLATION TRIPWIRE ---
$dbFile = $_ENV['DB_FILENAME'] ?? 'data.sqlite';
$dbPath = __DIR__ . '/' . $dbFile;

// If DB doesn't exist, and we aren't already on the install page, redirect!
if (!file_exists($dbPath)) {
    if (file_exists(__DIR__ . '/install.php') && basename($_SERVER['PHP_SELF']) !== 'install.php') {
        header("Location: ./install.php");
        exit;
    } else if (!file_exists(__DIR__ . '/install.php')) {
        die("Database missing and install.php not found. Please restore the database or installation file.");
    }
}
// -----------------------------

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =========================================================================
// 1. NATIVE .ENV RUNTIME LOADER FUNCTION
// =========================================================================
function loadEnv(string $dir): void
{
    $path = rtrim($dir, '/') . '/.env';

    if (!file_exists($path)) {
        return; // Gracefully skip if file is missing
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim(trim($value, '"\'')); // Clean strip

            putenv("{$key}={$value}");
            $_ENV[$key] = $value;

            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Fire the loader instantly targeting the current folder directory
loadEnv(__DIR__);

// =========================================================================
// 2. CONDITIONAL ENVIRONMENT ERROR CONTROLLER
// =========================================================================
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    ini_set("display_errors", "1");
    ini_set("display_startup_errors", "1");
    error_reporting(E_ALL);
} else {
    // Production Mode: Suppress display output completely to protect vault paths
    ini_set("display_errors", "0");
    ini_set("display_startup_errors", "0");
    error_reporting(0);
}

// =========================================================================
// 3. CENTRALIZED DATABASE PROVIDER (SINGLETON)
// =========================================================================
// =========================================================================
// 3. CENTRALIZED DATABASE PROVIDER (SINGLETON)
// =========================================================================
class Database
{
    private static ?SQLite3 $instance = null;

    // ---------------------------------------------------------------------
    // [UPGRADE 1]: STRICT SINGLETON ENFORCEMENT
    // ---------------------------------------------------------------------
    // Prevent developers (or rogue scripts) from using `new Database()`, 
    // `clone $db`, or unserializing to accidentally spawn a second connection 
    // that bypasses the busyTimeout and causes a database lock.
    private function __construct() {}
    private function __clone() {}
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a database singleton.");
    }

    public static function getConnection(): SQLite3
    {
        if (self::$instance === null) {
            $filename = defined('DB_FILENAME') ? DB_FILENAME : 'data.sqlite';
            $dbPath = __DIR__ . '/' . $filename;

            self::$instance = new SQLite3($dbPath);
            self::$instance->enableExceptions(true);

            // --- SQLITE CONCURRENCY & SPEED OPTIMIZATIONS ---

            self::$instance->busyTimeout(15000);
            self::$instance->exec('PRAGMA journal_mode = WAL;');
            self::$instance->exec('PRAGMA synchronous = NORMAL;');
            self::$instance->exec('PRAGMA temp_store = MEMORY;');
            self::$instance->exec('PRAGMA cache_size = -64000;');

            // -------------------------------------------------------------
            // [UPGRADE 2]: DATA INTEGRITY ENFORCEMENT
            // -------------------------------------------------------------
            // SQLite disables Foreign Keys by default. If your cron jobs 
            // delete a user, this ensures any linked records (like logs or 
            // IPO history) are safely handled or cascade-deleted.
            self::$instance->exec('PRAGMA foreign_keys = ON;');

            // -------------------------------------------------------------
            // [UPGRADE 3]: MEMORY-MAPPED I/O (LIGHTNING FAST READS)
            // -------------------------------------------------------------
            // Maps the database file directly into RAM for reading. 
            // Skips the OS file-system layer entirely. 
            // 536870912 = ~512MB limit for the memory map.
            self::$instance->exec('PRAGMA mmap_size = 536870912;');
        }
        return self::$instance;
    }

    // ---------------------------------------------------------------------
    // [UPGRADE 4]: TRANSACTION HELPERS FOR CRON LOOPS
    // ---------------------------------------------------------------------
    // Makes it effortlessly easy to wrap your heavy cron job loops inside 
    // transactions directly from the Database class.
    public static function beginTransaction(): void
    {
        self::getConnection()->exec('BEGIN TRANSACTION;');
    }

    public static function commit(): void
    {
        self::getConnection()->exec('COMMIT;');
    }

    public static function rollback(): void
    {
        self::getConnection()->exec('ROLLBACK;');
    }
}

// Instantiate global $db UNCONDITIONALLY so it's always available
$db = Database::getConnection();

// =========================================================================
// 4. CONFIGURATION ENGINE SETTINGS
// =========================================================================
if (!defined('MASTER_HASH')) {
    try {
        // $db is already available from right above
        $hash = $db->querySingle("SELECT value FROM constant WHERE key = 'master_password'");
        define('MASTER_HASH', $hash);
    } catch (Exception $e) {
        die("Vault Configuration Integrity Failure: " . $e->getMessage());
    }
}

// =========================================================================
// 5. WALL SESSION VERIFICATION GATEWAY (WITH RETURN-TO MEMORY CAPTURE)
// =========================================================================
$exemptedPages = ['auth.php', 'error_debug.php', 'run_all_crons.php', 's.php', 'webhook.php'];
$currentScript = basename($_SERVER['SCRIPT_NAME']);

$isExempt = in_array($currentScript, $exemptedPages) ||
    strpos($currentScript, 'cron_') === 0 ||
    strpos($currentScript, 'cr_') === 0;

if (!$isExempt) {
    if (!isset($_SESSION['master_logged_in']) || $_SESSION['master_logged_in'] !== true) {
        $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
        header("Location: auth.php");
        exit;
    }
}

// =========================================================================
// 5. SET TIME ZONE
// =========================================================================
$timezone = $env['APP_TIMEZONE'] ?? 'UTC'; // Fallback to UTC if not set

// Set the global server timezone
date_default_timezone_set($timezone);
