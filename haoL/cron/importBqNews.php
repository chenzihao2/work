<?php
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ERROR);


use QK\HaoLiao\Model\NewsModel;
use QK\HaoLiao\Model\SoccerModel;


function run() {
  $soccer_model = new SoccerModel();
  $soccer_model->nowMatchList();
  $news_model = new NewsModel();
  return $news_model->importBqNews();
}



run();
