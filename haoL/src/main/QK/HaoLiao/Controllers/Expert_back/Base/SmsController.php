<?php
/**
 * 验证码发送与验证
 * User: WangHui
 * Date: 2018/10/9
 * Time: 9:31
 */

namespace QK\HaoLiao\Controllers\Expert\Base;


use QK\HaoLiao\Common\SmsSend;
use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\SmsModel;

class SmsController extends ExpertController {

    /**
     * 发送验证码
     */
    public function getCode() {
        $must = ['tel'];
        $params = $this->checkApiParam($must);
        $smsSend = new SmsSend();
        if (!$smsSend->mobileCheck($params['tel'])) {
            $this->responseJsonError(1105);
        }
        $smsModel = new SmsModel();
        $sendTimes = $smsModel->todaySendCount($params['tel']);
        if ($sendTimes > 3) {
            $this->responseJsonError(1102);
        }
        $code = $this->getRand();
//        $content = '提现验证码' . $code . '，五分钟内有效！为保障资金安全，请勿将验证码透露给他人。【给料小程序】';
        $content = '注册验证码' . $code . '，五分钟内有效！为保障您的账户安全，请勿将验证码透露给他人。【好料精选】';
        $smsSend->send($params['tel'], $content);
        $smsModel->sendLog($params['tel'], $code);
        $redisModel = new RedisModel("user");
        $redisModel->redisSet(SMS_CODE . $params['tel'], $code, 300);
        $this->responseJson();
    }

    private function getRand() {
        return rand(1000, 9999);
    }

    /**
     * 验证码检查
     */
    public function codeVerify() {
        $must = [
            'tel', 'code'
        ];
        $params = $this->checkApiParam($must);
        $redisModel = new RedisModel("user");
        $code = $redisModel->redisGet(SMS_CODE . $params['tel']);
        if ($code != $params['code']) {
            //验证失败
            $this->responseJsonError(1103);
        } else {
            //验证成功
            $this->responseJson();
        }

    }

}