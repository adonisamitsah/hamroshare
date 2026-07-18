<?php
//error_reporting(E_ALL);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
if (!isset($_GET['dmat_num'])) {
	exit;
}
if (!isset($_GET['reapply-scrip'])) {
  $_GET['reapply-scrip']="";
}
$e_msg = "";
$Authorization = sudo_get_Authorization($_GET['dmat_num'],$db);

$url = "https://webbackend.cdsc.com.np/api/meroShare/companyShare/applicableIssue/";

$curl = curl_init($url);
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_POST, true);
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
   "Content-Type: application/json",
   "Origin: https://meroshare.cdsc.com.np",
   "Sec-Fetch-Site: same-site",
   "Sec-Fetch-Mode: cors",
   "Sec-Fetch-Dest: empty",
   "Referer: https://meroshare.cdsc.com.np/",
   "Accept-Language: en-US,en;q=0.9,ne;q=0.8",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

$data = '{"filterFieldParams":[{"key":"companyIssue.companyISIN.script","alias":"Scrip"},{"key":"companyIssue.companyISIN.company.name","alias":"Company Name"},{"key":"companyIssue.assignedToClient.name","value":"","alias":"Issue Manager"}],"page":1,"size":10,"searchRoleViewConstants":"VIEW_APPLICABLE_SHARE","filterDateParams":[{"key":"minIssueOpenDate","condition":"","alias":"","value":""},{"key":"maxIssueCloseDate","condition":"","alias":"","value":""}]}';

curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$json = json_decode($resp);

if (!isset($json->message)) {

$tE = count($json->object);
$x=0;
$a=array();
$reapply_btn="";
while($x < $tE) {

  if (!isset($json->object[$x]->action)) {
  	//display apply buttons for all available shares
  	array_push($a, array('btnType' => 'applyIPO','companyShareId' => $json->object[$x]->companyShareId,'scrip' => $json->object[$x]->scrip));
}

  if (isset($json->object[$x]->action) && $json->object[$x]->action=="reapply") {
    //display reapply button for get variable script
    $companyShareId=$json->object[$x]->companyShareId;
    // Pushing reapply to the same array in the same format
    array_push($a, array('btnType' => 'reapplyIPO','companyShareId' => $json->object[$x]->companyShareId,'scrip' => $json->object[$x]->scrip));
    }

  $x++;
}
if (empty($a)) {$e_msg="Nothing to apply.";}
$rx = array('resp' => $json,'table' => $a,'e_msg' => $e_msg);
echo json_encode($rx);
} else {
$rx = array('e_msg' => $json->message,'table' => '','resp' => $json,'companyShareId' => $companyShareId);
echo json_encode($rx);	
}

 ?>