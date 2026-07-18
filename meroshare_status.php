<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
if (!isset($_GET['dmat_num'])) {
	exit;
}
$e_msg = "";
$Authorization = sudo_get_Authorization($_GET['dmat_num'],$db);

$url = "https://webbackend.cdsc.com.np/api/meroShare/applicantForm/active/search/";

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

$data = '{"filterFieldParams":[{"key":"companyShare.companyIssue.companyISIN.script","alias":"Scrip"},{"key":"companyShare.companyIssue.companyISIN.company.name","alias":"Company Name"}],"page":1,"size":200,"searchRoleViewConstants":"VIEW_APPLICANT_FORM_COMPLETE","filterDateParams":[{"key":"appliedDate","condition":"","alias":"","value":""},{"key":"appliedDate","condition":"","alias":"","value":""}]}';

curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$json = json_decode($resp);

if (!isset($json->message)) {

$tE = count($json->object);
if ($tE>3) {$tE=3;}
$x=0;
$a=array();
while($x < $tE) {

  	//display apply buttons for all available shares
  	array_push($a, array('btnType' => 'checkStatus','applicantFormId' => $json->object[$x]->applicantFormId,'scrip' => $json->object[$x]->scrip));
  $x++;
}
if (empty($a)) {$e_msg="Nothing to display.";}
$rx = array('resp' => $json,'table' => $a,'e_msg' => $e_msg);
echo json_encode($rx);
} else {
$rx = array('e_msg' => $json->message,'table' => '','resp' => $json);
echo json_encode($rx);	
}

 ?>