<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
if (!isset($_GET['dmat_num'])) {
	exit;
}
$e_msg = "";
$dmat_num = $_GET['dmat_num'];
$applicantFormId = $_GET['applicantFormId'];
$scrip = $_GET['scrip'];
$Authorization = sudo_get_Authorization($dmat_num,$db);

$url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/report/detail/".$applicantFormId."";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
   "Connection: keep-alive",
   "Pragma: no-cache",
   "Cache-Control: no-cache",
   "sec-ch-ua: \" Not A;Brand\";v=\"99\", \"Chromium\";v=\"90\"",
   "Accept: application/json, text/plain, */*",
   "Authorization: ".$Authorization."",
   "sec-ch-ua-mobile: ?0",
   "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36",
   "Origin: https://meroshare.cdsc.com.np",
   "Sec-Fetch-Site: same-site",
   "Sec-Fetch-Mode: cors",
   "Sec-Fetch-Dest: empty",
   "Referer: https://meroshare.cdsc.com.np/",
   "Accept-Language: en-US,en;q=0.9,ne;q=0.8",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$json = json_decode($resp);

if (!isset($json->message)) {

sudo_put_lastStatusLog($dmat_num,$resp,$scrip,$db);

if ($json->meroshareRemark==$json->reasonOrRemark) {
$Remark = $json->meroshareRemark;
} else {
$Remark = $json->meroshareRemark."<br>Reason: ".$json->reasonOrRemark;    
}
if (isset($json->receivedKitta) && $json->receivedKitta!=null) {
    $AllotedKittaMsg = "Alloted Kitta: ".$json->receivedKitta."<br>";

    $alloted_amount = isset($json->amount) && $json->appliedKitta != 0 ? ($json->amount / $json->appliedKitta) * $json->receivedKitta : 0; // Calculate the alloted amount based on received kitta

    initiateLedgerEntry(
        $db, 
        $json->demat, 
        "Allotment of " . $scrip . " (Kitta: " . $json->receivedKitta . ")", 
        0,                 // Deposit
        $alloted_amount,   // Withdraw
        'IPO_ALLOTMENT'    // Type
    );




}else {$AllotedKittaMsg = "";}
if ($json->statusName=="Verified" || $json->statusName=="Alloted") {
    $statusNamemsg='<span class="success"><b>'.$json->statusName.'</b></span>';
} elseif($json->statusName=="Rejected") {
    $reapply_btn = '<button style="margin:5px 5px;" class="pure-button button-small-padding pure-button-primary" id="reapplyipo_'.$dmat_num.'" onclick="reapplyIPO(\''.$dmat_num.'\',\''.$scrip.'\');">Re Apply</button>';
    $statusNamemsg='<span class="danger">'.$json->statusName.'</span>'.$reapply_btn;
} else{$statusNamemsg='<span class="danger">'.$json->statusName.'</span>';}
$e_msg = "Company: ".$scrip."<br>"
       ."Applied Kitta: ".$json->appliedKitta."<br>"
       . $AllotedKittaMsg
       . "Status: ".$statusNamemsg."<br>"
       . "Remark: ".$Remark."<br>"
       . "Last Updated: ".sudo_get_time_diff(time())."<br>";

$rx = array('resp' => $json,'table' => '','e_msg' => $e_msg);
echo json_encode($rx);
} else {
$rx = array('e_msg' => $json->message,'table' => '','resp' => $json);
echo json_encode($rx);	
}

 ?>