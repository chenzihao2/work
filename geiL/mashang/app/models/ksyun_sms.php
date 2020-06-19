<?php
namespace App\models;

require(__DIR__.'/../ksyun_sms/vendor/autoload.php');
use Illuminate\Support\Facades\Redis;
use Ksyun\Service\Sms;
use App\models\sms_log;

class ksyun_sms {
    private $ak = 'AKLTot5yJ5u6TL2Fv-i2hEC7VQ';
    private $sk = 'OIrekNxjL4f7YBIfZxr8S+G76iT8m/XknLdWse1GiZofvzIsiDI6S9jN7AZkdbGSUQ==';
    private $tpid_gl_cash = 182;
    private $warning_gl = 970;
    //private $tpid_hl = 181;
    private $params = [
        'Action' => 'SendSms',
        'Version' => '2019-05-01',
        'Mobile' => '',
        'SignName' => '给料',
        'TplId' => 0,
        'TplParams' => '',
    ];
    //private $host_phone = [13858516005, 15101165761];
    private $host_phone = [];

    public function __construct() {
        $this->sms_obj = Sms::getInstance();
        $this->sms_obj->setCredentials($this->ak, $this->sk);
    }

    private function getResponse($response) {
        return json_decode((string)$response->getBody(), true);
    }

    public function send($tel, $uid) {
        if( !preg_match('/^1[3|4|5|6|7|8|9]\d{9}$/', $tel)) {
            $return['status_code'] = "10002";
            $return['error_message'] = "手机号格式不正确";
            return $return;
        }
        $day = date("Y-m-d");
        $count = sms_log::where("uid", $uid)->where("createtime", 'like', $day.'%')->count();
        if( $count >= 3 ) {
            $return['status_code'] = "10003";
            $return['error_message'] = "每天最多发送三次短信验证";
            return $return;
        }
        $code = mt_rand(100000,999999);
        Redis::set('code_'.$uid, $code);
        Redis::expire('code_'.$uid, 1800);
        $this->params['Mobile'] = $tel;
        $this->params['TplId'] = $this->tpid_gl_cash;
        $this->params['TplParams'] = json_encode(['code' => $code]);
        try {
            $response = $this->sms_obj->request('SendSms', ['form_params' => $this->params]);
            $response = $this->getResponse($response);
            $return['status_code'] = "200";
            $this->insertLog($uid, $tel, $code);
            return $return;
        } catch (\Exception $e) {
            $return['status_code'] = "10010";
            $return['error_message'] = $e->getMessage();
            return $return;
        }
    }

    public function warning_send() {
        $this->params['TplId'] = $this->warning_gl;
        $this->params['TplParams'] = '';
        try {
            foreach ($this->host_phone as $phone) {
                $this->params['Mobile'] = $phone;
                $response = $this->sms_obj->request('SendSms', ['form_params' => $this->params]);
                $response = $this->getResponse($response);
            }
        } catch (\Exception $e) {
            \Log::info('warning_send_error =>' . $e->getMessage());
        }

    }

    private function insertLog($uid, $tel, $code) {
        $smsLog['uid'] = $uid;
        $smsLog['telephone'] = $tel;
        $smsLog['description'] = $code;
        $smsLog['createtime'] = date('Y-m-d H:i:s');
        sms_log::create($smsLog);
    }
}
