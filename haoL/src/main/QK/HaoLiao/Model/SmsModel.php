<?php
/**
 * 短信处理
 * User: WangHui
 * Date: 2018/10/9
 * Time: 9:37
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALLogSmsSend;

class SmsModel extends BaseModel {
    private $_dalLogSmsSend;

    public function __construct() {
        parent::__construct();
        $this->_dalLogSmsSend = new DALLogSmsSend($this->_appSetting);
    }

    /**
     * 短信发送日志
     * @param $tel
     * @param $desc
     * @param string $uid
     */
    public function sendLog($tel, $desc, $uid = "") {
        $params['user_id'] = $uid;
        $params['telephone'] = $tel;
        $params['description'] = $desc;
        $params['send_time'] = time();
        $this->_dalLogSmsSend->sendLog($params);
    }

    /**
     * 验证码发送次数
     * @param $tel
     * @return mixed
     */
    public function todaySendCount($tel) {
        return $this->_dalLogSmsSend->sendCount($tel);
    }
}