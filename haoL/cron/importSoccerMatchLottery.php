<?php
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ERROR);


use QK\HaoLiao\Model\SoccerModel;


function run() {
  $soccer = new SoccerModel();
  return $soccer->importMatchLottery();
}



run();
