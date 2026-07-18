<?php
class EDISAutomation {
    private $db;
    private $auth;
    private $demat;
    
    // Tracking Properties for the Report Matrix
    private $transfersDone = 0;
    private $dangerObligations = [];
    private $errors = [];

    public function __construct($db, $dmat, $token) {
        $this->db = $db;
        $this->demat = $dmat;
        $this->auth = $token;
    }

    /**
     * Reusable HTTP Request method
     */
    private function request($url, $method = 'GET', $data = null) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                "Authorization: {$this->auth}",
                "Content-Type: application/json",
                "User-Agent: Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Mobile Safari/537.36"
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
        
        $resp = curl_exec($ch);
        
        if(curl_errno($ch)) {
            $this->errors[] = "cURL Error: " . curl_error($ch);
        }
        
        curl_close($ch);
        return json_decode($resp, true);
    }

    /**
     * Helper to return the standardized report format
     */
    private function getReport() {
        return [
            'status' => 'success',
            'transfers_done' => $this->transfersDone,
            'danger_obligations' => array_unique($this->dangerObligations),
            'errors' => $this->errors
        ];
    }

    /**
     * Main execution pipeline
     */
    public function run() {
        // 1. Reset trackers on run
        $this->transfersDone = 0;
        $this->dangerObligations = [];
        $this->errors = [];

        // 2. Initial EDIS Check
        $check = $this->request("https://webbackend.cdsc.com.np/api/EDIS/check/", 'GET');
        
        // 3. EARLY EXIT: Do not run if there is no EDIS today
        $message = $check['message'] ?? '';
        if (stripos($message, 'No EDIS') !== false || stripos($message, 'Data not found') !== false) {
            return $this->getReport(); // Exit cleanly
        }

        // 4. SMART WACC TRIGGER: If 409 Conflict, process WACC and Holdings first
        if (isset($check['errorCode']) && $check['errorCode'] == 409) {
            $this->processAllWaccAndHoldings();
        }

        // 5. Proceed to EDIS Settlement processing
        $this->processEDIS();

        // 6. Return final execution report to the main script
        return $this->getReport();
    }

    /**
     * Fetches all available scrips and updates their WACC and Holding Periods
     */
    private function processAllWaccAndHoldings() {
        // Step 1: Fetch Purchase Sources
        $scrips = $this->request("https://webbackend.cdsc.com.np/api/myPurchase/share/", 'POST', [
            "isFilterByAllScript" => false
        ]);

        if (is_array($scrips) && !empty($scrips)) {
            foreach ($scrips as $scrip) {
                $this->calculateWacc($scrip);
            }
        }

        // Step 2: Fetch Holdings that require CGT/Holding updates
        $holdingsList = $this->request("https://webbackend.cdsc.com.np/api/myHoldings/wacc/", 'GET');
        
        if (isset($holdingsList['data']['waccResponses']) && is_array($holdingsList['data']['waccResponses'])) {
            foreach ($holdingsList['data']['waccResponses'] as $holding) {
                $this->updateHoldingPeriod($holding['isin']);
            }
        }
    }

    /**
     * Executes WACC calculation for a specific scrip
     */
    private function calculateWacc($scrip) {
        $waccData = $this->request("https://webbackend.cdsc.com.np/api/myPurchase/search/wacc/", 'POST', [
            'demat' => $this->demat,
            'scrip' => $scrip
        ]);

        if (isset($waccData['waccUpdateResponse']) && !empty($waccData['waccUpdateResponse'])) {
            $payload = $waccData['waccUpdateResponse'];
            
            foreach ($payload as &$item) {
                $item['isEdit'] = true;
                $item['remarks'] = "";
            }
            
            $this->request("https://webbackend.cdsc.com.np/api/myPurchase/upload/", 'POST', $payload);
        }
    }

    /**
     * Executes Holding Period confirmation for a specific ISIN
     */
    private function updateHoldingPeriod($isin) {
        $holdingsData = $this->request("https://webbackend.cdsc.com.np/api/myHoldings/", 'POST', [
            'isin'  => $isin,
            'demat' => $this->demat
        ]);

        if (isset($holdingsData['data']) && !empty($holdingsData['data'])) {
            $payload = $holdingsData['data'];
            
            foreach ($payload as &$h) {
                $h['isEdit'] = true;
                $h['remarks'] = "";
                $h['previousHoldingDays'] = $h['holdingDays'] ?? 0;
                $h['previousHoldingFlag'] = $h['stLt'] ?? "ST";
            }
            
            $this->request("https://webbackend.cdsc.com.np/api/myHoldings/save/", 'POST', $payload);
        }
    }

    // /**
    //  * Processes EDIS Transfers after all WACC and Holdings are cleared v1
    //  */
    // private function processEDIS() {
    //     // Final EDIS Check before transfer
    //     $check = $this->request("https://webbackend.cdsc.com.np/api/EDIS/check/", 'GET');
        
    //     // Extract Scrip on 409 Conflict for Danger Alert if it STILL fails after processAllWaccAndHoldings
    //     if (isset($check['errorCode']) && $check['errorCode'] == 409) {
    //         $scrip = preg_replace('/[^A-Za-z0-9]/', '', str_replace('PLEASE CALCULATE WACC FOR THE FOLLOWING SCRIPS TO PROCEED FURTHER:', '', $check['message']));
    //         $this->dangerObligations[] = trim($scrip);
    //         $this->errors[] = "EDIS Blocked: Pending WACC for " . trim($scrip);
    //         return;
    //     }

    //     // Fetch Active Settlements
    //     $settlements = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/active/", 'POST', [
    //         'demat' => $this->demat
    //     ]);

    //     if (!is_array($settlements) || empty($settlements)) return;

    //     foreach ($settlements as $s) {
    //         if (!isset($s['settleId'])) continue;
            
    //         $settleId = $s['settleId'];
            
    //         // View Transfer Details
    //         $details = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/detail/$settleId", 'GET');
            
    //         if (!is_array($details) || empty($details)) continue;

    //         foreach ($details as $transfer) {
    //             $transfer['isEdit'] = true; 
                
    //             // Transfer Check
    //             $checkTransfer = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/check/", 'POST', [$transfer]);
                
    //             // Transfer Confirmation
    //             if (is_array($checkTransfer) && !empty($checkTransfer)) {
    //                 $finalResp = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/", 'POST', $checkTransfer);
                    
    //                 // Track Successful Transfers
    //                 if (isset($finalResp['statusCode']) && $finalResp['statusCode'] == 202) {
    //                     $this->transfersDone++;
    //                 } else {
    //                     $this->errors[] = "Failed to confirm transfer for Scrip: " . ($transfer['obligation']['scriptCode'] ?? 'Unknown');
    //                 }
    //             }
    //         }
    //     }
    // }

    /**
     * Processes EDIS Transfers after all WACC and Holdings are cleared v2
     */
    private function processEDIS() {
        // Final EDIS Check before transfer
        $check = $this->request("https://webbackend.cdsc.com.np/api/EDIS/check/", 'GET');
        
        $message = $check['message'] ?? '';
        
        // Safety Catch: If the API says No EDIS but throws a weird status code, exit cleanly.
        if (stripos($message, 'No EDIS') !== false || stripos($message, 'Data not found') !== false) {
            return; 
        }
        
        // Extract Scrip on 409 Conflict for Danger Alert if it STILL fails after processAllWaccAndHoldings
        if (isset($check['errorCode']) && $check['errorCode'] == 409) {
            
            // Look for the specific MeroShare WACC prompt string
            $promptString = 'PLEASE CALCULATE WACC FOR THE FOLLOWING SCRIPS TO PROCEED FURTHER:';
            
            if (stripos($message, $promptString) !== false) {
                // If the prompt exists, strip it out, and we are left with just the scrip(s) (e.g., " TPKHL, HBL ")
                $rawScripText = str_ireplace($promptString, '', $message);
                
                // Trim spaces and remove periods at the end of the sentence
                $scrip = trim($rawScripText, " .\t\n\r\0\x0B"); 
                
                $this->dangerObligations[] = $scrip;
                $this->errors[] = "EDIS Blocked: Pending WACC for " . $scrip;
            } else {
                // If it's a 409 error but NOT about WACC, log the actual raw message so you know what happened
                $this->errors[] = "EDIS Blocked (Conflict): " . $message;
            }
            return;
        }

        // Fetch Active Settlements
        $settlements = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/active/", 'POST', [
            'demat' => $this->demat
        ]);

        if (!is_array($settlements) || empty($settlements)) return;

        foreach ($settlements as $s) {
            if (!isset($s['settleId'])) continue;
            
            $settleId = $s['settleId'];
            
            // View Transfer Details
            $details = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/detail/$settleId", 'GET');
            
            if (!is_array($details) || empty($details)) continue;

            foreach ($details as $transfer) {
                $transfer['isEdit'] = true; 
                
                // Transfer Check
                $checkTransfer = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/check/", 'POST', [$transfer]);
                
                // Transfer Confirmation
                if (is_array($checkTransfer) && !empty($checkTransfer)) {
                    $finalResp = $this->request("https://webbackend.cdsc.com.np/api/EDIS/transfer/", 'POST', $checkTransfer);
                    
                    // Track Successful Transfers
                    if (isset($finalResp['statusCode']) && $finalResp['statusCode'] == 202) {
                        $this->transfersDone++;
                    } else {
                        $this->errors[] = "Failed to confirm transfer for Scrip: " . ($transfer['obligation']['scriptCode'] ?? 'Unknown');
                    }
                }
            }
        }
    }
}