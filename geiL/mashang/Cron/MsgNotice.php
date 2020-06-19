<?php
/**
 * User: WangHui
 * Date: 2018/5/21
 * Time: 15:58
 */


$urlToSendToSourceSeller= "https://yxapi.qiudashi.com/pub/msg/";
$urlToSendToSourceSellerBuyer= "https://yxapi.qiudashi.com/pub/msg/red";
file_get_contents($urlToSendToSourceSeller);
file_get_contents($urlToSendToSourceSellerBuyer);