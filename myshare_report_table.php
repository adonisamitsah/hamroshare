 <?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
echo sudo_get_header("allshares");
 ?>
    <div id="main">

        <div id="content" class="content">




<?php 


$query1="SELECT * FROM users WHERE is_active=1;";
$result=$db->query($query1);
$sn=1;
while($row= $result->fetchArray()){
//decode json here lastupdatedmyshare
//also lastupdatemysharetime 
/* 
table = '<table class="pure-table pure-table-bordered"><thead><tr><th>Symbol</th><th>Kitta</th></tr></thead><tbody>';
    for (i = 0; i < json.resp.totalItems; i++) {
      table += '<tr><td>'+json.resp.meroShareDematShare[i].script+'</td><td>'+Math.floor(json.resp.meroShareDematShare[i].currentBalance)+'</td></tr>';
  }
  table +='</tbody></table>';
*/
$json = $row['myshare'];
$table = "";
$json = json_decode($json);
if (!empty($json) && $json->totalItems>0) {
    if ($json->totalValueOfLastTransPrice>$json->totalValueOfPrevClosingPrice) {
       $px = '<span class="success">('.round((($json->totalValueOfLastTransPrice-$json->totalValueOfPrevClosingPrice)/$json->totalValueOfLastTransPrice)*100,2).' % <i class="fas fa-arrow-up"></i>)</span>'; 
    } elseif ($json->totalValueOfLastTransPrice<$json->totalValueOfPrevClosingPrice) {
       $px = '<span class="danger">('.round((($json->totalValueOfLastTransPrice-$json->totalValueOfPrevClosingPrice)/$json->totalValueOfLastTransPrice)*100,2).' % <i class="fas fa-arrow-down"></i>)</span>'; 
    } else {$px="";}
$table = '

<table class="pure-table pure-table-bordered my-1" style="width:100%;"><thead><tr><th style="background-color:#fff;font-weight:normal;line-height:1.4em;color:#777777">
Name : <b>'.$row['name'].'</b><br>
Value (LTP) : '.number_format($json->totalValueOfLastTransPrice).' '.$px.'<br>
Value (Previous Closing) : '.number_format($json->totalValueOfPrevClosingPrice).'<br>

Last Updated: '.sudo_get_time_diff($row['myshare_time']).'
</th></tr></thead></table>

<table class="pure-table pure-table-bordered" style="width:100%;"><thead><tr><th>Symbol</th><th>Kitta</th><th>LTP</th><th>Value (LTP)</th><th>PCP</th><th>Value (PCP)</th><th style="text-align: right;">(<i class="fas fa-arrow-up success"></i>/<i class="fas fa-arrow-down danger"></i>)</th></tr></thead><tbody>';
for ($i = 0; $i < $json->totalItems; $i++) {
    $ltpval = $json->meroShareMyPortfolio[$i]->valueAsOfLastTransactionPrice;
    $pcpval = $json->meroShareMyPortfolio[$i]->valueAsOfPreviousClosingPrice;
    if ($ltpval>$pcpval) {
       $percent = '<span class="success">'.round((($ltpval-$pcpval)/$ltpval)*100,2).' % <i class="fas fa-arrow-up"></i></span>'; 
    } elseif ($ltpval<$pcpval) {
       $percent = '<span class="danger">'.round((($ltpval-$pcpval)/$ltpval)*100,2).' % <i class="fas fa-arrow-down"></i></span>'; 
    } else {$percent="";}
      $table.= '<tr><td  data-tippy-content="'.$json->meroShareMyPortfolio[$i]->scriptDesc.'">'.$json->meroShareMyPortfolio[$i]->script.'</td><td>'.$json->meroShareMyPortfolio[$i]->currentBalance.'</td><td>'.$json->meroShareMyPortfolio[$i]->lastTransactionPrice.'</td><td>'.number_format($json->meroShareMyPortfolio[$i]->valueAsOfLastTransactionPrice).'</td><td>'.number_format($json->meroShareMyPortfolio[$i]->previousClosingPrice).'</td><td>'.number_format($json->meroShareMyPortfolio[$i]->valueOfPrevClosingPrice).'</td><td style="text-align: right;">'.$percent.'</td></tr>';
    }

$table.= '</tbody></table>';
}

  echo $table;
  $sn++;

}
?>


</div>

</div>

<?php  include('footer.php'); ?>