<?php
namespace QK\HaoLiao\Common;

require(__DIR__.'/../../../../../library/ksyun_sms/vendor/autoload.php');
use Ksyun\Service\Sms;
use QK\HaoLiao\Model\RedisModel;

class KsyunSms {
    private $ak = 'AKLTot5yJ5u6TL2Fv-i2hEC7VQ';
    private $sk = 'OIrekNxjL4f7YBIfZxr8S+G76iT8m/XknLdWse1GiZofvzIsiDI6S9jN7AZkdbGSUQ==';
    private $tpid_hl = 181;
    private $params = [
        'Action' => 'SendSms',
        'Version' => '2019-05-01',
        'Mobile' => '',
        'SignName' => '好料',
        'TplId' => 0,
        'TplParams' => '',
    ];

    public function __construct() {
        $this->sms_obj = Sms::getInstance();
        $this->sms_obj->setCredentials($this->ak, $this->sk);
    }

    private function getResponse($response) {
        return json_decode((string)$response->getBody(), true);
    }

    public function mobileCheck($telephone) {
        if( !preg_match('/^1[3|4|5|6|7|8]\d{9}$/', $telephone)) {
            return false;
        }else{
            return true;
        }
    }

    public function send($tel, $code) {
        $redisModel = new RedisModel("user");
        $this->params['Mobile'] = $tel;
        $this->params['TplId'] = $this->tpid_hl;
        $this->params['TplParams'] = json_encode(['code' => $code]);
        try {
            $response = $this->sms_obj->request('SendSms', ['form_params' => $this->params]);
            $response = $this->getResponse($response);
            $redisModel->redisSet(SMS_CODE . $tel, $code, 300);
            $return['status_code'] = "200";
            return $return;
        } catch (\Exception $e) {
            $return['status_code'] = "10010";
            $return['error_message'] = $e->getMessage();
            return $return;
        }
    }
}
