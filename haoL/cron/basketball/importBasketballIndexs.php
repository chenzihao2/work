<?php
require(__DIR__ . "/../cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);


use QK\HaoLiao\Model\BasketballIndexsModel;


function run() {
  $basketballIndex = new BasketballIndexsModel();
  return $basketballIndex->graspLists();
}

run();
