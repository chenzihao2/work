<?php
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ERROR);


use QK\HaoLiao\Model\CouponModel;


function run() {
  $coupon = new CouponModel();
  return $coupon->checkCouponTime();
}



run();
