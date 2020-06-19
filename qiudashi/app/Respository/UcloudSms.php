<?php
namespace App\Respository;
include_once('uc_sdk.php');

use Illuminate\Support\Facades\Redis;
use App\Models\SmsLog;

class UcloudSms {
    private $today_max = 10;
    private $redix_prefix = 'ucsms_';
    private $key_code = 101118;

    public function __construct() {
        $this->public_key = config('app.ucloud_sms_pu_key');
        $this->private_key = config('app.ucloud_sms_pr_key');
        $this->product_Id = config('app.ucloud_sms_pr_id');
    }

    public function sendCode($phone) {
        if (!$this->mobileCheck($phone)) {
            throw new \Exception('', 1000201);
        }
        $todayCount = SmsLog::todaySendCount($phone);
        if ($todayCount > $this->today_max) {
            throw new \Exception('', 1000202);
        }
        $code = mt_rand(100000,999999);
        $result = $this->send($phone, $code);
        if ($result['RetCode'] == 0) {
            Redis::set($this->redix_prefix . $phone, $code, 600);
            SmsLog::doLog($phone, $code);
        } else {
            SmsLog::doLog($phone, $code, 0);
            info('sms_result' . json_encode($result));
            throw new \Exception('', 1000203);
        }
    }

    public function checkCode($phone, $code) {
        if ($code == $this->key_code) {
            return true;
        }
        $real_code = Redis::get($this->redix_prefix . $phone);
        if ($real_code == $code) {
            return true;
        } else {
            return false;
        }
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
      return $response = $conn->get("/", $params);
    }
}
