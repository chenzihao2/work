<?php
require(__DIR__ . "/../cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ERROR);


use QK\HaoLiao\Model\BasketballIndexsModel;


function run() {
  $basketball = new BasketballIndexsModel();
  return $basketball->import_basketball_lottery();
}



run();
