<?php
//error_reporting(1);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
if (!isset($_GET['dmat_num'])) {
   exit;
}
$e_msg = "";
$dmat_num = $_GET['dmat_num'];
$clientCode = substr($dmat_num,3,5);

$Authorization = sudo_get_Authorization($dmat_num,$db);
$url = "https://webbackend.cdsc.com.np/api/myPurchase/myShare/";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
   "Connection: keep-alive",
   "sec-ch-ua: \" Not A;Brand\";v=\"99\", \"Chromium\";v=\"92\"",
   "Accept: application/json, text/plain, */*",
   "Authorization: ".$Authorization."",
   "sec-ch-ua-mobile: ?0",
   "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36",
   "Origin: https://meroshare.cdsc.com.np",
   "Sec-Fetch-Site: same-site",
   "Sec-Fetch-Mode: cors",
   "Sec-Fetch-Dest: empty",
   "Accept-Language: en-US,en;q=0.9,ne;q=0.8",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
// ... after json_decode($resp)
$json = json_decode($resp);
$e_msg = $s_msg = "";

// 1. Check if json is null (API error or empty response)
if ($json === null) {
    $e_msg = "Error: Failed to decode response from MeroShare (API might be down or session expired).";
} 
// 2. Check if $json is an array (Countable)
elseif (!is_array($json)) {
    // If it's an object with a message, use that
    $e_msg = isset($json->message) ? $json->message : "Received invalid data format.";
} 
// 3. Proceed only if it's a valid array
else {
    $tE = count($json);
    if ($tE === 0) {
        $e_msg = "Nothing to display.";
    } else {
        for ($x = 0; $x < $tE; $x++) {
            // Start updating WACC for all available shares
            $s_msg .= sudo_update_wacc($db, $dmat_num, $json[$x], $Authorization);
        }
    }
}

$rx = array('e_msg' => $e_msg, 's_msg' => $s_msg);
echo json_encode($rx);

?>

