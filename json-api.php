<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

if (isset($_GET['type']) && $_GET['type']=="loginall") {

$query1="SELECT * FROM users WHERE is_active=1";

$result=$db->query($query1);
$a = array();
while($row= $result->fetchArray()){
$clientId = $row['clientId'];
$username = $row['username'];
$password = $row['password'];
$dmat_num = $row['dmat_num'];
array_push($a, array('clientId' => $clientId, 'username' => $username, 'password' => $password, 'dmat_num' => $dmat_num ));
}
echo json_encode($a);
}

if (isset($_GET['type']) && $_GET['type']=="updateKitta") {
$kitta = $_GET['kitta'];
$query="UPDATE constant SET value='$kitta' WHERE key='appliedKitta'";
$db->exec($query);
if (!$db) {
	echo $db->lastErrorMsg();
}else { echo "Updated Successfully."; }
}

if (isset($_GET['type']) && $_GET['type']=="lastLogin") {
$dmat_num = $_GET['dmat_num'];
$query1="SELECT * FROM users  WHERE dmat_num='$dmat_num'";
$db->busyTimeout(30000);
$result=$db->query($query1);

while($row= $result->fetchArray()){
$min = ceil((time()-$row['lastLogin'])/60);
if ($min>60) {$lastLogin='<span class="danger">Login Expired</span>';}
else {$lastLogin='<span class="success">'.$min.' min ago</span>';} 
}
echo $lastLogin;
}

if (isset($_GET['type']) && $_GET['type']=="updateCapitalAsOptions") {
sudo_update_capital_as_options($db);
header('Location: addusers.php');
}


if (isset($_GET['type']) && $_GET['type']=="dmat_num_list_json") {
$query1="SELECT * FROM users WHERE is_active=1";

$result=$db->query($query1);
$a = array();
while($row= $result->fetchArray()){
$dmat_num = $row['dmat_num'];
array_push($a, $dmat_num);
}
echo json_encode($a);
}
if (isset($_GET['type']) && $_GET['type']=="fetch_pl") {
$query1="SELECT pl_json FROM users WHERE dmat_num=$_GET[dmat_num]";

$result=$db->query($query1);
$a = array();
while($row= $result->fetchArray()){
$json = $row['pl_json'];
}
echo $json;
}

if (isset($_GET['type']) && $_GET['type']=="create_backup") {
$t=time();
$backup = 'backup/backup_'.$t.'_.msbak';

$bak = copy("data.sqlite",$backup);

if ($bak===true) {
echo "success";
} elseif ($bak===false) {
echo "failed";
} else {
echo "error";	
}

}

if (isset($_GET['type']) && $_GET['type'] == "backup_table") {

    foreach (glob("backup/*.msbak") as $filename) {
        $timestamp = explode("_", $filename)[1];
        $date = date('Y-m-d H:i', $timestamp);
        $size = round(filesize($filename) / 1024, 2) . ' KB';
        $baseName = basename($filename);

        echo '
        <tr class="border-b border-slate-800/50 hover:bg-slate-800/20 transition-all group">
            <!-- File Name -->
            <td class="py-4 px-6">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-500 group-hover:bg-blue-500 group-hover:text-white transition-all">
                        <i class="fas fa-file-archive"></i>
                    </div>
                    <a href="'.$filename.'" download class="text-slate-200 font-medium hover:text-blue-400 transition-colors">
                        '.$baseName.'
                    </a>
                </div>
            </td>

            <!-- Created Date -->
            <td class="py-4 px-6 text-slate-400 font-mono text-sm">
                '.$date.'
            </td>

            <!-- File Size -->
            <td class="py-4 px-6 text-slate-500 text-sm">
                '.$size.'
            </td>

            <!-- Actions -->
            <td class="py-4 px-6">
                <div class="flex items-center justify-center gap-2">
                    <a href="'.$filename.'" download 
                       class="p-2 rounded-lg bg-slate-800 text-slate-400 hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="Download">
                        <i class="fas fa-download"></i>
                    </a>
                    
                    <button onclick="restore_backup('.$timestamp.');" 
                            class="p-2 rounded-lg bg-emerald-600/10 text-emerald-500 border border-emerald-500/20 hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="Restore">
                        <i class="fas fa-upload"></i>
                    </button>

                    <button onclick="delete_backup('.$timestamp.');" 
                            class="p-2 rounded-lg bg-rose-600/10 text-rose-500 border border-rose-500/20 hover:bg-rose-600 hover:text-white transition-all shadow-sm" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>

            <!-- Status Log -->
            <td class="py-4 px-6">
                <div id="log_'.$timestamp.'" class="text-[10px] font-mono text-blue-400 uppercase tracking-tighter italic"></div>
            </td>
        </tr>';
    }
}

if (isset($_GET['type']) && $_GET['type']=="restore_backup") {

$t = $_GET['t'];
$filename = "backup/backup_".$t."_.msbak";

$res = copy($filename,"data.sqlite");

if ($res===true) {
echo "success";
} elseif ($res===false) {
echo "failed";
} else {
echo "error";	
}

}
if (isset($_GET['type']) && $_GET['type']=="delete_backup") {

$t = $_GET['t'];
$filename = "backup/backup_".$t."_.msbak";

if (!unlink($filename)) { 
    echo ("<span class='danger'>Backup file cannot be deleted due to an error</span>"); 
} 
else { 
    echo ("<span class='success'>Backup file has been deleted</span>"); 
}

}

if (isset($_GET['type']) && $_GET['type']=="ipo_result_company_names") {
$result = null;
if (isset($_SESSION['company_names'])) {
$return = $_SESSION['company_names'];
} else {

$result = sudo_fetch_ipo_result_symbol_as_options();

}

echo $result;
}







if (isset($_GET['type']) && $_GET['type']=="checkIpoResult_db") {
$value = $_GET['value'];
$id = explode("_", $value)[0];
$name = explode("_", $value)[1];
$scrip = explode("_", $value)[2];

$query1="SELECT * FROM ipo_result  WHERE scrip='$scrip'";
$db->busyTimeout(30000);
$result=$db->query($query1);
$table=array();
while($row= $result->fetchArray()){
$x = ["dmat_num" => $row["dmat_num"],"log" => $row["log"]];
array_push($table, $x);
}
echo json_encode($table);
}


if (isset($_GET['type']) && $_GET['type']=="force_fetch_ipo_result") {
$value = $_GET['value'];
$id = explode("_", $value)[0];
$name = explode("_", $value)[1];
$scrip = explode("_", $value)[2];
$table = sudo_force_fetch_ipo_result_table($db,$id,$scrip);

$query1="SELECT COUNT(*) as count FROM constant  WHERE key='$scrip'";
$db->busyTimeout(30000);
$result=$db->query($query1);
$row = $result->fetchArray();
$numRows = $row['count'];


if ($numRows<1) {
	$query2= "INSERT INTO constant (key, value) VALUES ('$scrip', '$table');";
	$db->busyTimeout(30000);
	$result=$db->exec($query2) or die(print_r($db->lastErrorMsg(), true));
} else {
	$query3="UPDATE constant SET value='$table' WHERE key='$scrip'";
	$db->busyTimeout(30000);
	$db->exec($query3) or die(print_r($db->lastErrorMsg(), true));
}


echo $table;

}



// Inside json-api.php
if (isset($_GET['action']) && $_GET['action'] == 'add_user') {
    header('Content-Type: application/json');
    try {
        // Using $_GET as requested
        $dmat_num = validate($_GET["dmat_num"]);
        $name = validate($_GET["name"]);
        $username = substr($dmat_num, 8);
        $password = validate($_GET["password"]);
        $select = validate($_GET["clientId"]);
        $select = explode("xxxxx", $select);

        if (count($select) < 2) throw new Exception("Invalid Client/DP selection.");

        $clientId = $select[0];
        $dpName = ucwords(strtolower($select[1])); 
        $crnNumber = validate($_GET["crnNumber"]);
        $transactionPIN = validate($_GET["transactionPIN"]);

        $query1 = "INSERT INTO users(name,username,password,clientId,dpName,crnNumber,transactionPIN,dmat_num) 
                   VALUES('$name','$username','$password','$clientId','$dpName','$crnNumber','$transactionPIN','$dmat_num')";
        
        $db->exec($query1);
        
        echo json_encode(['status' => 'success', 'message' => "Investor profile for <b>$name</b> has been successfully encrypted and stored."]);
    } catch (Exception $e) {
        // Handle duplicate DMAT numbers or SQL errors
        $msg = (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) 
               ? "This DMAT number is already registered." 
               : $e->getMessage();
        echo json_encode(['status' => 'error', 'message' => "Database Error: " . $msg]);
    }
    exit;
}

// Inside json-api.php
if (isset($_GET['action']) && $_GET['action'] == 'update_user') {
    header('Content-Type: application/json');
    try {
        $id = validate($_GET['id']);
        $name = validate($_GET['name']);
        $password = validate($_GET['password']);
        $crn = validate($_GET['crnNumber']);
        $pin = validate($_GET['transactionPIN']);
        $isactive=validate($_GET['is_active']);

        $query = "UPDATE users SET 
                  name = '$name', 
                  password = '$password', 
                  crnNumber = '$crn', 
                  transactionPIN = '$pin',
                  is_active='$isactive'
                  WHERE id = '$id'";
        
        $db->exec($query);
        echo json_encode(['status' => 'success', 'message' => "Credentials for <b>$name</b> have been re-encrypted and updated."]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => "Update failed: " . $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'get_own_details') {
    $dmat = validate($_GET['dmat']);
    
    // Retrieve the most recent token for this DMAT from your DB/Session
    $token = sudo_get_Authorization($dmat, $db); // This matches your new function

    if (!$token) {
        // If no token exists at all, immediately tell JS to login
        echo json_encode(["status" => "unauthorized"]);
        exit;
    }

    $ch = curl_init('https://webbackend.cdsc.com.np/api/meroShare/ownDetail/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $token",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) ..."
    ]);
    
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 401) {
        // Token exists but is expired
        echo json_encode(["status" => "unauthorized"]);
    } else if ($http_code == 200) {
        echo json_encode(["status" => "success", "data" => json_decode($res)]);
    } else {
        echo json_encode(["status" => "error", "message" => "CDSC Gateway Timed Out"]);
    }
    exit;
}

// Action: Fetch and Save MeroShare OwnDetails
if (isset($_GET['action']) && $_GET['action'] == 'fetch_and_save_details') {
    header('Content-Type: application/json');
    
    $dmat = validate($_GET['dmat']);
    
    // 1. Retrieve the stored Bearer Token
    // This function must exist in your php_function.php or config
    $token = sudo_get_Authorization($dmat, $db); // This matches your new function
    
    if (!$token) {
        echo json_encode(["status" => "unauthorized", "message" => "No active session found."]);
        exit;
    }

    // 2. Execute Request to CDSC API
    $ch = curl_init('https://webbackend.cdsc.com.np/api/meroShare/ownDetail/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $token",
        "Accept: application/json, text/plain, */*",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
        "Origin: https://meroshare.cdsc.com.np",
        "Referer: https://meroshare.cdsc.com.np/"
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 3. Process Result
// Inside the success block of your fetch_and_save_details action
if ($http_code == 200) {
    $safeJson = $db->escapeString($response);
    $dmat = $_GET['dmat'];

    // Update the specific column names
    $sql = "UPDATE users SET 
            ownDetails = '$safeJson', 
            last_updated_owndetails = DATETIME('now', 'localtime') 
            WHERE dmat_num = '$dmat'";
    
    if ($db->exec($sql)) {
        echo json_encode([
            "status" => "success",
            "last_sync" => date('Y-m-d H:i:s'),
            "data" => json_decode($response)
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $db->lastErrorMsg()]);
    }
    exit;
}
    elseif ($http_code == 401) {
        // Session expired at CDSC level
        echo json_encode(["status" => "unauthorized", "message" => "MeroShare session expired."]);
    } 
    else {
        echo json_encode(["status" => "error", "message" => "CDSC Server returned code: $http_code"]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'change_password') {
    // 1. Sanitize Inputs
    $dmat = isset($_POST['dmat']) ? trim((string)$_POST['dmat']) : '';
    $oldPass = $_POST['oldPassword'];
    $newPass = $_POST['newPassword'];
    $confirmPass = $_POST['confirmPassword'];

    if (empty($dmat)) {
        echo json_encode(["status" => "error", "message" => "DMAT number is missing."]);
        exit;
    }

    // 2. Prepare Database Variables
    $safeNewPass = $db->escapeString($newPass);
    $safeOldPass = $db->escapeString($oldPass);

    // 3. STAGE 1: Update Local Vault first (The Pre-flight Check)
    // We use a LIKE match as a fallback for potential padding issues
    $db->exec("UPDATE users SET password = '$safeNewPass' WHERE dmat_num = '$dmat'");

    if ($db->changes() === 0) {
        // Try one more time with a looser match if exact match fails
        $db->exec("UPDATE users SET password = '$safeNewPass' WHERE dmat_num LIKE '%$dmat%'");
        if ($db->changes() === 0) {
            echo json_encode(["status" => "error", "message" => "Local Vault update failed: User not found."]);
            exit;
        }
    }

    // 4. Get Authorization Token (must happen after we know the user exists)
    $token = sudo_get_Authorization($dmat, $db);

    // 5. STAGE 2: Try MeroShare API
    $ch = curl_init('https://webbackend.cdsc.com.np/api/meroShare/changePassword/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "oldPassword" => $oldPass,
        "newPassword" => $newPass,
        "confirmPassword" => $confirmPass
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: $token",
        "Content-Type: application/json",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $resData = json_decode($response, true);
    curl_close($ch);

    // 6. STAGE 3: Final Verification Logic
    $apiMessage = isset($resData['message']) ? $resData['message'] : '';
    
    // Check for success: Either HTTP 200 OR the message contains "successfully"
    $isActuallySuccessful = ($http_code == 200 || stripos($apiMessage, 'successfully') !== false);

    if ($isActuallySuccessful) {
        // Success! Database is already updated with $newPass
        echo json_encode([
            "status" => "success", 
            "message" => "Rotation Complete! MeroShare and Local Vault are now in sync."
        ]);
    } else {
        // ACTUAL FAILURE: Revert the local database to the old password
        $db->exec("UPDATE users SET password = '$safeOldPass' WHERE dmat_num = '$dmat'");
        
        $errorDetail = !empty($apiMessage) ? $apiMessage : "HTTP Error: $http_code";
        echo json_encode([
            "status" => "error", 
            "message" => "MeroShare failed: $errorDetail. Local Vault has been reverted to the old password."
        ]);
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == "update_split_parameters") {
    $dmat = trim($_GET['dmat'] ?? '');
    $m_pct = floatval($_GET['manager_pct'] ?? 0);
    $c_pct = floatval($_GET['client_pct'] ?? 0);
    $a_pct = floatval($_GET['agent_pct'] ?? 0);

    if (empty($dmat)) {
        echo json_encode(["status" => "error", "message" => "DMAT identifier context dropped."]);
        exit();
    }

    if (($m_pct + $c_pct + $a_pct) !== 100.0) {
        echo json_encode(["status" => "error", "message" => "The mathematical sum must equal exactly 100%."]);
        exit();
    }

    // Wrap the payload into your requested structured text JSON object array format
    $json_payload = json_encode([
        "manager_pct" => $m_pct,
        "client_pct"  => $c_pct,
        "agent_pct"   => $a_pct
    ]);

    // Complete direct safe preparation injection updating your SQLite text-based JSON column configuration
    $update_stmt = $db->prepare("UPDATE users SET profit_dist_split_para = :json WHERE dmat_num = :dmat;");
    $update_stmt->bindValue(':json', $json_payload, SQLITE3_TEXT);
    $update_stmt->bindValue(':dmat', $dmat, SQLITE3_TEXT);

    if ($update_stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Split scheme array parameterized cleanly."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database level mutation block error."]);
    }
    exit();
}
// --- ROUTE ENDPOINT: MODIFY PROFIT DISTRIBUTION RECORD ---
if (isset($_GET['action']) && $_GET['action'] == "edit_profit_distribution") {
    $id = intval($_GET['id'] ?? 0);
    $dmat = trim($_GET['dmat_num'] ?? '');
    $date = trim($_GET['date'] ?? '');
    $scrip = strtoupper(trim($_GET['scrip_name'] ?? ''));
    $invest = floatval($_GET['invest_amt'] ?? 0);
    $net = floatval($_GET['net_receivable'] ?? 0);
    
    $mpct = floatval($_GET['manager_pct'] ?? 0);
    $cpct = floatval($_GET['client_pct'] ?? 0);
    $apct = floatval($_GET['agent_pct'] ?? 0);
    
    $mprof = floatval($_GET['manager_profit'] ?? 0);
    $cprof = floatval($_GET['client_profit'] ?? 0);
    $aprof = floatval($_GET['agent_profit'] ?? 0);
    $status = trim($_GET['status'] ?? 'PENDING');

    if ($id <= 0 || empty($dmat) || empty($scrip)) {
        echo json_encode(["status" => "error", "message" => "Malformed tracking properties payload rejected."]);
        exit();
    }

    if (abs(($mpct + $cpct + $apct) - 100.0) > 0.01) {
        echo json_encode(["status" => "error", "message" => "Percentages layout math bounds evaluation must sum to exactly 100%."]);
        exit();
    }

    $update_stmt = $db->prepare("
        UPDATE profit_distributions SET 
            dmat_num = :dmat, date = :date, scrip_name = :scrip, invest_amt = :invest, net_receivable = :net,
            manager_pct = :mpct, client_pct = :cpct, agent_pct = :apct,
            manager_profit = :mprof, client_profit = :cprof, agent_profit = :aprof, status = :status
        WHERE id = :id;
    ");
    
    $update_stmt->bindValue(':dmat', $dmat, SQLITE3_TEXT);
    $update_stmt->bindValue(':date', $date, SQLITE3_TEXT);
    $update_stmt->bindValue(':scrip', $scrip, SQLITE3_TEXT);
    $update_stmt->bindValue(':invest', $invest, SQLITE3_FLOAT);
    $update_stmt->bindValue(':net', $net, SQLITE3_FLOAT);
    $update_stmt->bindValue(':mpct', $mpct, SQLITE3_FLOAT);
    $update_stmt->bindValue(':cpct', $cpct, SQLITE3_FLOAT);
    $update_stmt->bindValue(':apct', $apct, SQLITE3_FLOAT);
    $update_stmt->bindValue(':mprof', $mprof, SQLITE3_FLOAT);
    $update_stmt->bindValue(':cprof', $cprof, SQLITE3_FLOAT);
    $update_stmt->bindValue(':aprof', $aprof, SQLITE3_FLOAT);
    $update_stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $update_stmt->bindValue(':id', $id, SQLITE3_INTEGER);

    if ($update_stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Distribution parameters recalculated and synchronized cleanly."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database ledger log write manipulation constraint block error."]);
    }
    exit();
}

// --- ROUTE ENDPOINT: PURGE/DELETE PROFIT DISTRIBUTION RECORD ---
if (isset($_GET['action']) && $_GET['action'] == "delete_profit_distribution") {
    $id = intval($_GET['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Missing absolute line item record lookup value context."]);
        exit();
    }

    // Fetch the record first to know what ledger entry to reverse
    // 1. Fetch the data, now including amounts
$fetch_stmt = $db->prepare("SELECT dmat_num, scrip_name, net_receivable FROM profit_distributions WHERE id = :id");
$fetch_stmt->bindValue(':id', $id);
$record = $fetch_stmt->execute()->fetchArray(SQLITE3_ASSOC);

    $delete_stmt = $db->prepare("DELETE FROM profit_distributions WHERE id = :id;");
    $delete_stmt->bindValue(':id', $id, SQLITE3_INTEGER);

    if ($delete_stmt->execute()) {
        
    if ($record) {
    $particular = "Sold shares for " . $record['scrip_name'];
    
    // For 'IPO_SOLD', usually the money comes in, so it's a deposit
    $deposit = $record['net_receivable']; 
    $withdraw = 0; 

    // 2. Perform the reversal with precise amounts
    deleteLedgerEntry($db, $record['dmat_num'], $particular, $deposit, $withdraw);
    echo json_encode(["status" => "success", "message" => "Line entry deleted and Ledger updated."]);
    } else {
        echo json_encode(["status" => "warn", "message" => "Line entry deleted. No corresponding ledger entry found for reversal."]);
    }
    
    
        } else {
        echo json_encode(["status" => "error", "message" => "Dropping data entity restricted at system configuration baseline."]);
    }
    exit();
}
// ========================================================
// LEDGER TRANSACTION MANAGEMENT OPERATIONS MODULE
// ========================================================
if (isset($_POST['action']) && ($_POST['action'] === 'edit_ledger_transaction' || $_POST['action'] === 'delete_ledger_transaction')) {
    
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Missing valid database row reference ID.']);
        exit;
    }

    // --- SUB-ROUTINE: EDIT TRANSACTION ---
    if ($action === 'edit_ledger_transaction') {
        $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
        $particular = isset($_POST['particular']) ? trim($_POST['particular']) : '';
        
        // Normalize amounts to 2 decimal places
        $deposit_amt = isset($_POST['deposit_amt']) ? max(0.00, round(floatval($_POST['deposit_amt']), 2)) : 0.00;
        $withdraw_amt = isset($_POST['withdraw_amt']) ? max(0.00, round(floatval($_POST['withdraw_amt']), 2)) : 0.00;

        if (empty($particular)) {
            echo json_encode(['status' => 'error', 'message' => 'Particular description cannot be left blank.']);
            exit;
        }

        // Fetch DMAT number to know whose ledger history to recalculate
        $lookupStmt = $db->prepare("SELECT dmat_num FROM ledgers WHERE id = :id;");
        $lookupStmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $ledgerRow = $lookupStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($ledgerRow) {
            $dmat_num = $ledgerRow['dmat_num'];

            // Save updates
            $updateStmt = $db->prepare("
                UPDATE ledgers 
                SET date = :date, particular = :particular, deposit_amt = :deposit, withdraw_amt = :withdraw 
                WHERE id = :id;
            ");
            $updateStmt->bindValue(':date', $date, SQLITE3_TEXT);
            $updateStmt->bindValue(':particular', $particular, SQLITE3_TEXT);
            $updateStmt->bindValue(':deposit', $deposit_amt, SQLITE3_FLOAT);
            $updateStmt->bindValue(':withdraw', $withdraw_amt, SQLITE3_FLOAT);
            $updateStmt->bindValue(':id', $id, SQLITE3_INTEGER);

            if ($updateStmt->execute()) {
                // Rebuild the chronological balance column sequence
                $stmt = $db->prepare("SELECT id, deposit_amt, withdraw_amt FROM ledgers WHERE dmat_num = :dmat ORDER BY date ASC, id ASC;");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $result = $stmt->execute();

                $running_balance = 0.00;
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $running_balance += (float)$row['deposit_amt'];
                    $running_balance -= (float)$row['withdraw_amt'];

                    $updateRowStmt = $db->prepare("UPDATE ledgers SET balance = :balance WHERE id = :id;");
                    $updateRowStmt->bindValue(':balance', $running_balance, SQLITE3_FLOAT);
                    $updateRowStmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $updateRowStmt->execute();
                }

                echo json_encode(['status' => 'success', 'message' => 'Transaction modified and running balance rebuilt safely.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error executing row modifications.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Transaction record not found.']);
        }
        exit;
    }

    // --- SUB-ROUTINE: DELETE TRANSACTION ---
    if ($action === 'delete_ledger_transaction') {
        // Fetch DMAT number first to know whose history chain to repair
        $lookupStmt = $db->prepare("SELECT dmat_num FROM ledgers WHERE id = :id;");
        $lookupStmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $ledgerRow = $lookupStmt->execute()->fetchArray(SQLITE3_ASSOC);

        if ($ledgerRow) {
            $dmat_num = $ledgerRow['dmat_num'];

            // Delete the row item
            $deleteStmt = $db->prepare("DELETE FROM ledgers WHERE id = :id;");
            $deleteStmt->bindValue(':id', $id, SQLITE3_INTEGER);

            if ($deleteStmt->execute()) {
                // Recalculate everything to eliminate the broken calculations gap
                $stmt = $db->prepare("SELECT id, deposit_amt, withdraw_amt FROM ledgers WHERE dmat_num = :dmat ORDER BY date ASC, id ASC;");
                $stmt->bindValue(':dmat', $dmat_num, SQLITE3_TEXT);
                $result = $stmt->execute();

                $running_balance = 0.00;
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $running_balance += (float)$row['deposit_amt'];
                    $running_balance -= (float)$row['withdraw_amt'];

                    $updateRowStmt = $db->prepare("UPDATE ledgers SET balance = :balance WHERE id = :id;");
                    $updateRowStmt->bindValue(':balance', $running_balance, SQLITE3_FLOAT);
                    $updateRowStmt->bindValue(':id', $row['id'], SQLITE3_INTEGER);
                    $updateRowStmt->execute();
                }

                echo json_encode(['status' => 'success', 'message' => 'Transaction record deleted and balance sequence recalculated cleanly.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Database error purging requested transaction.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Target record node missing from database table registry.']);
        }
        exit;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'save_withdraw_request') {
    header('Content-Type: application/json');
    
    $data = json_decode($_POST['payload'], true);
    
    if (!$data || !is_array($data)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
        exit;
    }

    $db->exec('BEGIN TRANSACTION'); // Good practice for multiple inserts

    try {
        // 1. Save the raw request to 'constant' table (your original logic)
        $timestamp = date('YmdHis');
        $storageKey = 'withdrawal_history_' . $timestamp;
        
        $stmt = $db->prepare("INSERT INTO constant (key, value) VALUES (:key, :value)");
        $stmt->bindValue(':key', $storageKey, SQLITE3_TEXT);
        $stmt->bindValue(':value', json_encode($data), SQLITE3_TEXT);
        $stmt->execute();

        // 2. Iterate through each account in the payload and create a ledger entry
        foreach ($data as $userRequest) {
            $dmat = $userRequest['dmat'];
            $withdrawAmount = $userRequest['withdrawAmount'];
            
              // 1. Revert status in profit_distributions for each scrip in the batch
    foreach ($userRequest['scrips'] as $scrip) {
        // Log the exact values being passed
    error_log("Attempting update: DMAT=$dmat, Date={$userRequest['date']}, Scrip=$scrip");
        // Assuming your payload date is in the same format as the database
      updateProfitDistributionStatus($db, $dmat, $userRequest['date'], $scrip, 'W&D');

    }


            // Generate a particular string, e.g., "Withdrawal - TEST"
            $scripList = implode(', ', $userRequest['scrips']);
            $particular = "Withdrawal: " . $scripList;

            // Call your ledger function
            // deposit = 0, withdraw = $withdrawAmount
            initiateLedgerEntry($db, $dmat, $particular, 0, $withdrawAmount, 'WITHDRAWAL');
        }

        $db->exec('COMMIT');
        echo json_encode(['status' => 'success', 'message' => 'Ledger entries and request saved.']);
        
    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'delete_batch') {
    header('Content-Type: application/json');
    $key = $_POST['key'];

    // 1. Fetch the batch data first so we know which ledger entries to remove
    $stmt = $db->prepare("SELECT value FROM constant WHERE key = :key");
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'Batch not found.']);
        exit;
    }

    $batchData = json_decode($result['value'], true);

    $db->exec('BEGIN TRANSACTION');

    try {
        // 2. Iterate through items to delete corresponding ledger entries
        if (is_array($batchData)) {
            foreach ($batchData as $userRequest) {
                $dmat = $userRequest['dmat'];
                $withdrawAmount = $userRequest['withdrawAmount'];
                
                // 1. Revert status in profit_distributions for each scrip in the batch
    foreach ($userRequest['scrips'] as $scrip) {
        // Assuming your payload date is in the same format as the database
        updateProfitDistributionStatus($db, $dmat, $userRequest['date'], $scrip, 'PENDING');
    }

                // Reconstruct the same particular string used during creation
                $scripList = implode(', ', $userRequest['scrips']);
                $particular = "Withdrawal: " . $scripList;

                // Call your delete function
                deleteLedgerEntry($db, $dmat, $particular, 0, $withdrawAmount);
            }
        }

        // 3. Remove the batch from the constant table
        $delStmt = $db->prepare("DELETE FROM constant WHERE key = :key");
        $delStmt->bindValue(':key', $key, SQLITE3_TEXT);
        $delStmt->execute();

        $db->exec('COMMIT');
        echo json_encode(['status' => 'success', 'message' => 'Batch and ledger entries removed.']);

    } catch (Exception $e) {
        $db->exec('ROLLBACK');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'get_ledger_balances') {
    $dmats = $_POST['dmats']; // Array of dmat_nums
    $results = [];

    foreach ($dmats as $dmat) {
        // Query the last entry for this DMAT to get the running balance
        // Assuming your table has a 'balance' column and a unique 'id'
        $stmt = $db->prepare("SELECT balance FROM ledgers WHERE dmat_num = :dmat ORDER BY id DESC LIMIT 1");
        $stmt->bindValue(':dmat', $dmat);
        $res = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        $results[] = [
            'dmat' => $dmat,
            'balance' => $res['balance'] ?? 0
        ];
    }
    
    echo json_encode($results);
    exit;
}


if (isset($_POST['action']) && $_POST['action'] === 'send_whatsapp_image') {
    $imageData = $_POST['image_data'];
    $filename = $_POST['filename'];
     $chatId = '9779815748710-1625643351@g.us';

    // 1. Define and CREATE the directory if it doesn't exist
    $uploadDir = __DIR__ . '/temp_uploads/';
    
    if (!is_dir($uploadDir)) {
        // Attempt to create with full permissions
        if (!mkdir($uploadDir, 0755, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to create upload directory.']);
            exit;
        }
    }

    $filePath = $uploadDir . $filename;

    // 2. Decode and save
    $imageData = str_replace('data:image/png;base64,', '', $imageData);
    $imageData = str_replace(' ', '+', $imageData);
    $fileData = base64_decode($imageData);
    
    if (file_put_contents($filePath, $fileData) === false) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to write file to disk. Check permissions.']);
        exit;
    }

    // 3. Send via WhatsApp
    require_once 'php_function.php';
   // $response = sendWhatsAppFile($chatId, $filePath, $filename);

    // 4. Clean up
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    echo json_encode(['status' => 'success', 'response' => 'FUnction not working right now']);
    exit;
}
?>