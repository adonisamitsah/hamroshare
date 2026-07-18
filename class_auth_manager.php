<?php
class AuthManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Ensures the session is active, performs login if necessary.
     * @param array $user The database row for the user.
     * @return string|false The valid Authorization token, or false on failure.
     */
    public function getToken($user) {
        $token = $user['Authorization'] ?? '';
        $lastLogin = (int)($user['lastLogin'] ?? 0);
        $dmat = $user['dmat_num'] ?? '';

        if (empty($dmat)) {
            error_log("AuthManager Error: Missing DMAT number in user payload.");
            return false;
        }

        $minutesSinceLogin = ceil((time() - $lastLogin) / 60);

        if ($minutesSinceLogin <= 25 && !empty($token)) {
            return $token;
        }

        return $this->performLogin($user);
    }

    private function performLogin($user) {
        $login_url = "https://webbackend.cdsc.com.np/api/meroShare/auth/";
        
        $login_data = json_encode([
            "clientId" => (int)($user['clientId'] ?? 0),
            "username" => (string)($user['username'] ?? ''),
            "password" => (string)($user['password'] ?? '')
        ]);

        // --- THE RETRY ENGINE ---
        $max_retries = 2; // 1 Initial attempt + 1 Retry
        $attempt = 0;

        while ($attempt < $max_retries) {
            $attempt++;

            $ch = curl_init($login_url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $login_data,
                CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
                CURLOPT_HEADER => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 20, 
                CURLOPT_CONNECTTIMEOUT => 10 
            ]);

            $login_resp = curl_exec($ch);
            $curl_error = curl_error($ch);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 1. SUCCESS PATH
            if ($login_resp !== false && $http_code === 200) {
                $header = substr($login_resp, 0, $header_size);

                if (preg_match('/Authorization:\s*(.*)$/mi', $header, $matches)) {
                    $newToken = trim($matches[1]);
                    $dmat = $user['dmat_num'];
                    
                    try {
                        $this->db->busyTimeout(30000);
                        $stmt = $this->db->prepare("UPDATE users SET Authorization = :token, lastLogin = :time WHERE dmat_num = :dmat");
                        $stmt->bindValue(':token', $newToken, SQLITE3_TEXT);
                        $stmt->bindValue(':time', time(), SQLITE3_INTEGER);
                        $stmt->bindValue(':dmat', $dmat, SQLITE3_TEXT);
                        $stmt->execute();
                        
                        return $newToken;
                        
                    } catch (Exception $e) {
                        error_log("AuthManager DB Error for {$dmat}: " . $e->getMessage());
                        return $newToken; // Safe fallback: still return token even if SQLite cache fails
                    }
                }
            }

            // 2. FAILURE PATH - Evaluate if we should retry
            if ($attempt < $max_retries) {
                
                // SMART FAIL: If password is wrong (401/400), do NOT retry. It will lock the account.
                if ($http_code === 401 || $http_code === 400) {
                    error_log("AuthManager Auth Rejected (HTTP {$http_code}): Bad Credentials for {$user['dmat_num']}. Aborting.");
                    return false;
                }

                // Temporary Network/Server Error - Wait 3 seconds and retry
                $err_msg = ($login_resp === false) ? "cURL Timeout/Error" : "CDSC HTTP {$http_code}";
                error_log("AuthManager Warning for {$user['dmat_num']}: {$err_msg}. Retrying in 3 seconds...");
                sleep(3);

            } else {
                // 3. FINAL FATAL ERROR LOGGING (Both attempts failed)
                if ($login_resp === false) {
                    error_log("AuthManager FATAL cURL Error for {$user['dmat_num']}: " . $curl_error);
                } elseif ($http_code !== 200) {
                    error_log("AuthManager FATAL HTTP Error {$http_code} for {$user['dmat_num']}");
                } else {
                    error_log("AuthManager Regex Error: Could not extract Authorization header for {$user['dmat_num']}.");
                }
            }
        }

        return false; // Failed after all retries
    }
}
?>