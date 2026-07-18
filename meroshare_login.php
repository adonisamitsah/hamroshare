<?php
//error_reporting(0);
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
if (!isset($_GET['clientId']) && !isset($_GET['username']) && !isset($_GET['password'])) {
  $e_count = 1;
  $e_msg = "clientid,username,password cannot be empty";
  exit;
}

$clientId = $_GET['clientId'];
$username = $_GET['username'];
$password = $_GET['password'];
$dmat_num = $_GET['dmat_num'];

$url = "https://webbackend.cdsc.com.np/api/meroShare/auth/";

$curl = curl_init($url);
if (!$curl) {
    die("Couldn't initialize a cURL handle");
}
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_HEADER,1);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$headers = array(
   "Connection: keep-alive",
   "Pragma: no-cache",
   "Cache-Control: no-cache",
   "Accept: application/json, text/plain, */*",
   "Authorization: null",
   "User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/89.0.4389.114 Safari/537.36",
   "Content-Type: application/json",
   "Origin: https://meroshare.cdsc.com.np",
   "Sec-Fetch-Site: same-site",
   "Sec-Fetch-Mode: cors",
   "Sec-Fetch-Dest: empty",
   "Referer: https://meroshare.cdsc.com.np/",
   "Accept-Language: en-US,en;q=0.9",
);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

$data = '{"clientId":'.$clientId.',"username":"'.$username.'","password":"'.$password.'"}';

curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

//for debug only!
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

$resp = curl_exec($curl);
//curl_close($curl);
//var_dump($resp);
$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
$header = substr($resp, 0, $header_size);
$body = substr($resp, $header_size);
//echo $header;
$header_array = (explode("\r\n", $header));
$x = count($header_array);
$i = 0;
while ($i < $x) {
$pattern = "/Authorization:/i";
if (preg_match($pattern, $header_array[$i])==1) {
  
   $Authorization = str_replace("Authorization: ", "", $header_array[$i]);
   sudo_put_Authorization($dmat_num,$db,$Authorization);
 }

$i++; 
}


echo $body;


?>

