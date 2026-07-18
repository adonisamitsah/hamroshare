<?php 
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
$id = $_GET['id'];
$query="DELETE FROM users WHERE id=$id;";

$db->exec($query);
header("location:addusers.php");

 ?>