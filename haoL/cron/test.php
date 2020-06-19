<?php
require(__DIR__ . "/cron.php");

ini_set('display_errors', 'On');
error_reporting(E_ALL | E_STRICT);

use QK\HaoLiao\Model\OrderModel;

$orderModel = new OrderModel();

$orderInfo = $orderModel->successOrder('201906121928448356709310');

var_dump($orderInfo);
