<?php
namespace QK\HaoLiao\Common;

use Ksyun\Service\Sms;
use QK\HaoLiao\Model\RedisModel;

include_once('sdk.php');

class UcloudSms {
    private $public_key = "rX/3xdXXpvOHLyRB8hMx5IV4/usfv/ogeEHEro60V/C8pXMMKgOOYQ==";
    private $private_key = "kbb6zzMiBjxuhijDD8CgkSd+Xv8tc3QdlVa7Z9c2Wbxz/yX4etnHei4B48tHWQJu";
    private $product_Id = "org-4bnymp";

    public function __construct() {
    }

    public function mobileCheck($telephone) {
        if( !preg_match('/^1[3|4|5|6|7|8]\d{9}$/', $telephone)) {
            return false;
        }else{
            return true;
        }
    }

    public function send($mobile, $code) {
      //send
      $conn = new \UcloudApiClient("http://api.ucloud.cn", $this->public_key, $this->private_key, $this->product_Id);
      $params['Action'] = "SendUSMSMessage";
      $params['SigContent'] = "球大师体育";
      $params['TemplateId'] = "UTA19101285DF02";
      //$params['TemplateId'] = "UTA19101097A3A0";
      $params["PhoneNumbers.0"] = $mobile;
      $params["TemplateParams.0"] = $code;
      $response = $conn->get("/", $params);
      error_log(print_r($response, true));
      if ($response['RetCode'] == 0) {
        $redisModel = new RedisModel("user");
        $redisModel->redisSet(SMS_CODE . $mobile, $code, 300);
        return ['status_code' => 200];
      } else {
        return ['status_code' => $response['RetCode']];
      }
    }
}
