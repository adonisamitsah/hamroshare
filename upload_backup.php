<?php
require_once __DIR__ . '/config.php'; /** * @var SQLite3 $db */
require_once __DIR__ . '/php_function.php';
//ini_set ("display_errors", "1");
//error_reporting(E_ALL);
$target_dir = "backup/";
$t = time();
$target_file = $target_dir."backup_".$t."_.msbak" ;
$uploadOk = 1;
$basename = basename($_FILES["msbak_file"]["name"]);
$FileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

// Check if image file is a actual image or fake image

// Check if file already exists
if (file_exists($target_file)) {
  echo "Sorry, file already exists.";
  $uploadOk = 0;
}

// Check file size
if ($_FILES["msbak_file"]["size"] > 50000000) {
  echo "Sorry, your file is too large.";
  $uploadOk = 0;
}

// Allow certain file formats
if($FileType != "msbak") {
  echo "Sorry, only .msbak files are allowed.";
  echo $FileType;
  $uploadOk = 0;
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
  echo "Sorry, your file was not uploaded.";
// if everything is ok, try to upload file
} else {
  if (move_uploaded_file($_FILES["msbak_file"]["tmp_name"], $target_file)) {
    echo "The file ". htmlspecialchars( basename( $_FILES["msbak_file"]["name"])). " has been uploaded as ". htmlspecialchars($target_file)."";
  } else {
    echo "Sorry, there was an error uploading your file.";
  }
}
?>