<?php

class NotificationManager {
    // Toggles
    private $enableTelegram;
    private $enableTelegramBackup;
    private $enableWhatsApp;

    // Credentials & IDs
    private $telegramToken;
    private $telegramChatIds;
    private $telegramChannelId;
    
    private $waInstanceId;
    private $waToken;
    private $waTargetIds;

    public function __construct() {
        // Safely parse boolean strings from .env (converts "true" / "TRUE" to actual true)
        $this->enableTelegram       = filter_var($_ENV['ENABLE_TELEGRAM_NOTIFICATION'] ?? getenv('ENABLE_TELEGRAM_NOTIFICATION'), FILTER_VALIDATE_BOOLEAN);
        $this->enableTelegramBackup = filter_var($_ENV['ENABLE_TELEGRAM_BACKUP'] ?? getenv('ENABLE_TELEGRAM_BACKUP'), FILTER_VALIDATE_BOOLEAN);
        $this->enableWhatsApp       = filter_var($_ENV['ENABLE_WHATSAPP_NOTIFICATION'] ?? getenv('ENABLE_WHATSAPP_NOTIFICATION'), FILTER_VALIDATE_BOOLEAN);

        // Load credentials and IDs
        $this->telegramToken     = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN');
        $this->telegramChatIds   = $_ENV['TELEGRAM_CHAT_IDS'] ?? getenv('TELEGRAM_CHAT_IDS');
        $this->telegramChannelId = $_ENV['TELEGRAM_CHANNEL_ID'] ?? getenv('TELEGRAM_CHANNEL_ID');
        
        $this->waInstanceId = $_ENV['WHATSAPP_GREENAPI_INSTANCE_ID'] ?? getenv('WHATSAPP_GREENAPI_INSTANCE_ID');
        $this->waToken      = $_ENV['WHATSAPP_GREENAPI_TOKEN'] ?? getenv('WHATSAPP_GREENAPI_TOKEN');
        $this->waTargetIds  = $_ENV['WHATSAPP_TARGET_IDS'] ?? getenv('WHATSAPP_TARGET_IDS');
    }

    /**
     * ==========================================
     * TELEGRAM INTEGRATION
     * ==========================================
     */
    
    public function sendTelegramMessage($message, $toChannel = false) {
        // Verify if notification is enabled
        if (!$this->enableTelegram) {
            return "Skipped: Telegram notifications are disabled in .env";
        }
        if (!$this->telegramToken) return false;

        $targetStr = $toChannel ? $this->telegramChannelId : $this->telegramChatIds;
        
        // array_filter removes any empty elements if there are trailing commas
        $recipients = array_filter(array_map('trim', explode(',', $targetStr)));
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendMessage";
        
        $results = [];
        foreach ($recipients as $chatId) {
            $results[$chatId] = $this->executeCurl($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);
        }
        return $results;
    }

    public function sendTelegramDocument($filePath, $caption = "📂 Detailed Application Log", $toChannel = true) {
        // Verify specific toggle if sending to backup channel
        if ($toChannel && !$this->enableTelegramBackup) {
            return "Skipped: Telegram Backup is disabled in .env";
        } elseif (!$toChannel && !$this->enableTelegram) {
            return "Skipped: Telegram notifications are disabled in .env";
        }

        if (!$this->telegramToken || !file_exists($filePath)) return false;

        $targetStr = $toChannel ? $this->telegramChannelId : $this->telegramChatIds;
        $recipients = array_filter(array_map('trim', explode(',', $targetStr)));
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendDocument";

        $results = [];
        foreach ($recipients as $chatId) {
            $post_fields = [
                'chat_id'    => $chatId,
                'document'   => new CURLFile(realpath($filePath)),
                'caption'    => $caption,
                'parse_mode' => 'Markdown'
            ];

            $results[$chatId] = $this->executeCurl($url, $post_fields, true);
        }
        return $results;
    }

    public function sendTelegramPhoto($filePath, $caption = "", $toChannel = false) {
        // Verify specific toggle if sending to backup channel vs standard chat
        if ($toChannel && !$this->enableTelegramBackup) {
            return "Skipped: Telegram Backup is disabled in .env";
        } elseif (!$toChannel && !$this->enableTelegram) {
            return "Skipped: Telegram notifications are disabled in .env";
        }

        if (!$this->telegramToken || !file_exists($filePath)) return false;

        $targetStr = $toChannel ? $this->telegramChannelId : $this->telegramChatIds;
        
        // array_filter removes any empty elements if there are trailing commas
        $recipients = array_filter(array_map('trim', explode(',', $targetStr)));
        $url = "https://api.telegram.org/bot{$this->telegramToken}/sendPhoto";

        $results = [];
        foreach ($recipients as $chatId) {
            $post_fields = [
                'chat_id'    => $chatId,
                'photo'      => new CURLFile(realpath($filePath)),
                'caption'    => $caption,
                'parse_mode' => 'Markdown'
            ];

            // Assuming the 3rd parameter in your executeCurl method handles multipart/form-data for file uploads
            $results[$chatId] = $this->executeCurl($url, $post_fields, true);
        }
        return $results;
    }

    /**
     * ==========================================
     * WHATSAPP INTEGRATION
     * ==========================================
     */

    public function sendWhatsAppMessage($markdownMsg, $customChatIds = null) {
        // Verify if notification is enabled
        if (!$this->enableWhatsApp) {
            return "Skipped: WhatsApp notifications are disabled in .env";
        }

        $targetStr = $customChatIds ?? $this->waTargetIds;
        if (!$this->waToken || !$targetStr) return false;

        if (!$this->ensureWhatsAppAuthorized()) {
            return "Error: WhatsApp Instance not authorized.";
        }

        $url = "https://7107.api.greenapi.com/waInstance{$this->waInstanceId}/sendMessage/{$this->waToken}";
        $plainText = $this->parseMarkdownToWhatsApp($markdownMsg);
        
        // Loop through multiple WhatsApp target IDs
        $recipients = array_filter(array_map('trim', explode(',', $targetStr)));
        $results = [];

        foreach ($recipients as $chatId) {
            $results[$chatId] = $this->executeCurl($url, [
                'chatId' => $chatId,
                'message' => $plainText,
                'linkPreview' => true
            ], false, true); // Send as JSON payload
        }
        
        return $results;
    }

    public function sendWhatsAppFile($filePath, $fileName, $customChatIds = null) {
        // Verify if notification is enabled
        if (!$this->enableWhatsApp) {
            return "Skipped: WhatsApp notifications are disabled in .env";
        }

        $targetStr = $customChatIds ?? $this->waTargetIds;
        if (!$this->waToken || !$targetStr || !file_exists($filePath)) return false;

        if (!$this->ensureWhatsAppAuthorized()) {
            return "Error: WhatsApp Instance not authorized.";
        }

        $url = "https://7107.api.greenapi.com/waInstance{$this->waInstanceId}/sendFileByUpload/{$this->waToken}";
        
        // Loop through multiple WhatsApp target IDs
        $recipients = array_filter(array_map('trim', explode(',', $targetStr)));
        $results = [];

        foreach ($recipients as $chatId) {
            // Instantiate a fresh CURLFile object for each loop to avoid file pointer errors
            $cfile = new CURLFile($filePath, mime_content_type($filePath), $fileName);
            
            $results[$chatId] = $this->executeCurl($url, [
                'chatId' => $chatId,
                'file'   => $cfile
            ], true); // true forces multipart/form-data
        }
        
        return $results;
    }

    /**
     * ==========================================
     * PRIVATE HELPERS
     * ==========================================
     */

    private function ensureWhatsAppAuthorized() {
        $state = $this->getWhatsAppState();

        if (in_array($state, ['starting', 'sleepMode', 'yellowCard'])) {
            $this->rebootWhatsAppInstance();
            sleep(30); // Give the API time to restart
            $state = $this->getWhatsAppState();
        }

        return $state === 'authorized';
    }

    private function getWhatsAppState() {
        $url = "https://7107.api.greenapi.com/waInstance{$this->waInstanceId}/getStateInstance/{$this->waToken}";
        $response = $this->executeCurl($url, [], false, false, 'GET');
        $data = json_decode($response, true);
        return $data['stateInstance'] ?? 'unknown';
    }

    private function rebootWhatsAppInstance() {
        $url = "https://7107.api.greenapi.com/waInstance{$this->waInstanceId}/reboot/{$this->waToken}";
        $response = $this->executeCurl($url, [], false, false, 'GET');
        return json_decode($response, true)['isReboot'] ?? false;
    }

    private function parseMarkdownToWhatsApp($markdown) {
    $urls = [];
    
    // 1. SHIELD MARKDOWN LINKS
    // Extracts [Text](URL) and saves it safely away from the regex
    $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function($matches) use (&$urls) {
        $placeholder = "@@URL_" . count($urls) . "@@";
        // Placing a newline before the URL guarantees WhatsApp makes it clickable
        $urls[$placeholder] = $matches[1] . ":\n" . $matches[2]; 
        return $placeholder;
    }, $markdown);

    // 2. SHIELD BARE URLS
    // Just in case you ever send a raw URL with underscores, this protects it too.
    $text = preg_replace_callback('/(https?:\/\/[^\s]+)/', function($matches) use (&$urls) {
        $placeholder = "@@URL_" . count($urls) . "@@";
        $urls[$placeholder] = $matches[1];
        return $placeholder;
    }, $text);

    // 3. CLEANUP
    $text = preg_replace('/^#+\s+/m', '', $text); // Remove Headers
    $text = preg_replace('/^>\s+/m', '', $text);  // Remove Blockquotes

    // 4. FORMATTING PROTECTION
    // We use safe temporary tags so Bold doesn't accidentally trigger Italic rules
    $text = preg_replace('/(\*\*|__)(.*?)\1/', '@@B@@$2@@B@@', $text); // Map Bold
    $text = preg_replace('/(\*|_)(.*?)\1/', '@@I@@$2@@I@@', $text);    // Map Italic

    // Apply strict WhatsApp syntax
    $text = str_replace('@@B@@', '*', $text);
    $text = str_replace('@@I@@', '_', $text);

    // 5. RESTORE URLS
    // Put the untouched, underscore-heavy URLs safely back into the text
    if (!empty($urls)) {
        $text = strtr($text, $urls);
    }

    return trim($text);
}

    // Universal cURL executor to prevent redundant setup code
    private function executeCurl($url, $data = [], $isMultipart = false, $isJson = false, $method = 'POST') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Protect cron jobs from hanging
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            
            if ($isJson) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            } elseif ($isMultipart) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}