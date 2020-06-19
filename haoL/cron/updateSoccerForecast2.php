<?php
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ERROR);


use QK\HaoLiao\Model\SoccerModel;


function run() {
  $soccer = new SoccerModel();
	$soccer->updateBaseMatchInfo(3903244);
  //$soccer->importMatchPast();
  //$soccer->importIndexs(374660);
  $soccer->updateBaseMatchInfo();
}



run();
