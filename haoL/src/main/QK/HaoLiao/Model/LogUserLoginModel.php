<?php
/**
 * 用户登录日志处理类
 * User: YangChao
 * Date: 2018/7/30
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\DAL\DALLogUserLogin;

class LogUserLoginModel extends BaseModel{

    protected $_redisModel;
    protected $_DAlLogUserLogin;
    protected $_rds_user_login_log;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel('log');
        $this->_DAlLogUserLogin = new DALLogUserLogin($this->_appSetting);
        $this->_rds_user_login_log = "user_login_log";
    }

    /**
     * 设置用户登录日志toRedis
     * @param $userId
     * @param int $is_reg
     */
    public function setLoginLogToRds($userId, $is_reg = 0){
        $logData['user_id'] = $userId;
        $logData['channel'] = $GLOBALS['From'];
        $logData['channel_sub'] = $GLOBALS['FromSub'];
        $logData['is_reg'] = $is_reg ;
        $logData['login_ip'] = CommonHandler::newInstance()->clientIpAddress();
        $logData['log_time'] = time();
        $this->_redisModel->redisLpush($this->_rds_user_login_log, json_encode($logData));
    }

    /**
     * 设置用户登录日志toMysql
     */
    public function setLoginLog(){
        while ($value = $this->_redisModel->redisRpop($this->_rds_user_login_log)) {
            $value = trim($value);
            $data = json_decode($value, true);
            $this->_DAlLogUserLogin->loginLog($data);
        }
    }

	public function getUserLastLoginLog($uid,$start=0) {
		return $this->_DAlLogUserLogin->getUserLoginLastLog($uid,$start);
	}



}