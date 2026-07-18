<?php
require_once __DIR__ . '/config.php'; /** @var SQLite3 $db */
require_once __DIR__ . '/php_function.php'; // Ensure your decode function is loaded

$shortHash = $_GET['i'] ?? null;

if ($shortHash) {
    // 1. Decode the secure hash back into the real database ID
    $cleanId = decodeShortId($shortHash);

    // 2. Failsafe: Only query the DB if the decoded ID is a valid positive number
    if ($cleanId > 0) {
        $stmt = $db->prepare("SELECT long_url FROM short_urls WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $cleanId, SQLITE3_INTEGER);
        $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($result && !empty($result['long_url'])) {
            $targetUrl = $result['long_url'];

            // WhatsApp / Social Media Bot Interceptor (Stealth Mode)
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $isBot = preg_match('/(WhatsApp|facebookexternalhit|Twitterbot|TelegramBot|Slackbot|LinkedInBot|Discordbot)/i', $userAgent);

            if ($isBot) {
                header("HTTP/1.1 200 OK");
                echo '<!DOCTYPE html><html lang="en"><head><meta name="robots" content="noindex, nofollow, nosnippet"></head><body></body></html>';
                exit();
            } else {
                // Real Human Redirect
                header("Location: " . $targetUrl);
                exit();
            }
        }
    }
}

// Fallback if the hash is invalid, guessed incorrectly, or expired
header("HTTP/1.0 404 Not Found");
echo "Secure link expired or not found.";
exit();
?>