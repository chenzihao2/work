<?php
require(__DIR__ . "/../cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);


use QK\HaoLiao\Model\BasketballModel;


function run() {
  $basketball = new BasketballModel();
  $basketball->updateExceptionMatch();
}

run();
