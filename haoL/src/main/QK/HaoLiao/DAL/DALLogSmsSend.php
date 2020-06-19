<?php
/**
 * User: WangHui
 * Date: 2018/10/9
 * Time: 9:37
 */

namespace QK\HaoLiao\DAL;


class DALLogSmsSend extends BaseDAL {
    protected $_table = 'hl_log_sms_send';

    /**
     * 发送日志入库
     * @param $params
     */
    public function sendLog($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 获取验证码发送次数
     * @param $tel
     * @return mixed
     */
    public function sendCount($tel) {
        $startTime = strtotime(date('Y-m-d', time()));
        $sql = "select count(*) from `$this->_table` WHERE `telephone`='$tel' and `send_time`>=$startTime";
        return $this->getDB($sql)->executeValue($sql);
    }

}