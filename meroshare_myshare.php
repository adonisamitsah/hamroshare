<?php
//error_reporting(E_ALL);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
if (!isset($_GET['dmat_num'])) {
   exit;
}
$e_msg = "";
$dmat_num = $_GET['dmat_num'];
$clientCode = substr($dmat_num,3,5);

$Authorization = sudo_get_Authorization($dmat_num,$db);
$url = "https://webbackend.cdsc.com.np/api/meroShareView/myPortfolio/";

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

$data = '{"sortBy":"CCY_SHORT_NAME","demat":["'.$dmat_num.'"],"clientCode":"'.$clientCode.'","page":1,"size":200,"sortAsc":true}';

curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
curl_close($curl);
$json = json_decode($resp);
if (!isset($json->message)) {




sudo_put_myshare($dmat_num,$resp,$db);

$rx = array('resp' => $json,'table' => true,'e_msg' => 'No Shares Found');
echo json_encode($rx);


} else {
$rx = array('e_msg' => $json->message,'table' => false,'resp' => $json);
echo json_encode($rx);  
}

?>
