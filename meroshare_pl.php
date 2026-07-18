<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';

if (!isset($_GET['dmat_num'])) {
	exit;
}
$dmat_num = $_GET['dmat_num'];
$Authorization = sudo_get_Authorization($dmat_num,$db);

$e_msg = "";

$json = json_decode(sudo_get_pl($db,$dmat_num,1,$Authorization));

if (!isset($json->message)) {

$merged_json = array();
for ($i=0; $i < count($json->object) ; $i++) { 
    array_push($merged_json, $json->object[$i]);
}

if ($json->totalCount>200) {
echo $json->totalCount;
$t_page = ceil($json->object->totalCount/200);
echo $t_page;
for ($p=2; $p <= $t_page; $p++) {

$json = json_decode(sudo_get_pl($db,$dmat_num,$p,$Authorization));

if (!isset($json->message)) {

for ($i=0; $i < count($json->object) ; $i++) { 
    array_push($merged_json, $json->object[$i]);
}

}
$e_msg.="Error during Page".$p."<br>";
}

}



$rx = array('table' => $merged_json,'e_msg' => $e_msg);
sudo_put_pl_data($db,$dmat_num,$rx);
echo json_encode($rx);

} else {
$rx = array('e_msg' => $json->message,'table' => '','resp' => $json);
echo json_encode($rx);	
}



 ?>

