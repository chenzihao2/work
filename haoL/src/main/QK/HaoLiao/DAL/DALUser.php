<?php
/**
 * User: WangHui
 * Date: 2018/9/29
 * Time: 11:01
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;
use QK\WSF\Settings\AppSetting;

class DALUser extends BaseDAL
{
	protected $_table = "hl_user";
    private $device_table = "hl_user_device";

	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}

    /**
     * 获取用户总量
     * @param $where
     * @return mixed
     */
	public function getUserTotal($where){
        $sql = "SELECT count(`user_id`) AS total FROM `$this->_table` WHERE 1";
        if(!empty($where)){
          foreach($where as $key => $val){
            if ($key == 'nick_name') {
              $sql .= " AND $key LIKE '%$val%'";
            } else {
                if(!empty($val) && !in_array($key, ['create_time_start', 'create_time_end'])){
                    $sql .= " AND $key = $val";
                } elseif ($key == 'create_time_start' && !empty($val)){
                    $sql .= " AND `create_time` >= $val";
                } elseif ($key == 'create_time_end' && !empty($val)){
                    $sql .= " AND `create_time` < $val";
                }
            }
          }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取用户列表
     * @param $where
     * @param $start
     * @param $size
     * @return array|bool
     */
	public function getUserList($where, $start, $size){
        $sql = "SELECT `user_id`,`cid`, `phone`, `nick_name`, `sex`, `country`, `province`, `city`, `headimgurl`, `idcard_type`, `idcard_number`, `source`, `identity`, `dist_id`,`user_status`, `create_time`, `modify_time`, `last_login_time`, `last_login_ip`,`forbidden_say` FROM `$this->_table` WHERE 1";
        if(!empty($where)){
          foreach($where as $key => $val){
            if ($key == 'nick_name') {
              $sql .= " AND $key LIKE '%$val%'";
            }else {
                if(!empty($val) && !in_array($key, ['create_time_start', 'create_time_end'])){
                    $sql .= " AND $key = $val";
                } elseif ($key == 'create_time_start' && !empty($val)){
                    $sql .= " AND `create_time` >= $val";
                } elseif ($key == 'create_time_end' && !empty($val)){
                    $sql .= " AND `create_time` < $val";
                }
            }
          }
        }
        $sql .= " ORDER BY `create_time` DESC LIMIT $start, $size";

        return $this->getDB($sql)->executeRows($sql);
    }

    //后台用户管理获取用户数量-新增
    public function getUserTotalV2($where){
        $sql = "SELECT count(a.user_id) AS total FROM $this->_table as a left join hl_channel as b on a.cid=b.cid WHERE 1";
        if(!empty($where)){
            foreach($where as $key => $val){
                if ($key == 'nick_name') {
                    $sql .= " AND a.$key LIKE '%$val%'";
                }else{
                    if(!empty($val) && !in_array($key, ['create_time_start', 'create_time_end'])){
                        if($key=='platform' || $key=='channel'){
                            if($val){
                                $sql .= " AND b.$key = '$val'";
                            }
                        }else{
                            $sql .= " AND a.$key = $val";
                        }
                    } elseif ($key == 'create_time_start' && !empty($val)){
                        $sql .= " AND a.create_time >= $val";
                    } elseif ($key == 'create_time_end' && !empty($val)){
                        $sql .= " AND a.create_time < $val";
                    }
                }

            }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    //后台获取用户列表-新增
    public function getUserListV2($where, $start, $size,$order=[]){
        //$sql = "SELECT a.user_id,a.cid, a.phone, a.nick_name, a.sex, a.country, a.province, a.city, a.headimgurl, a.idcard_type, a.idcard_number, a.source, a.identity, a.dist_id,a.user_status, a.create_time, a.modify_time,a.last_login_time, a.last_login_ip,a.forbidden_say FROM $this->_table as a left join hl_channel as b on a.cid=b.cid left join hl_user_extra as c on a.user_id=c.user_id WHERE 1";
        $sql = "SELECT a.user_id,a.cid, a.phone, a.nick_name, a.sex, a.country, a.province, a.city, a.headimgurl, a.idcard_type, a.idcard_number, a.source, a.identity, a.dist_id,a.user_status, a.create_time, a.modify_time,a.last_login_time, a.last_login_ip,a.forbidden_say,c.pay_amount FROM $this->_table as a left join hl_channel as b on a.cid=b.cid left join (select user_id,sum(pay_amount) as pay_amount from hl_order where order_type<= 6 and order_status=1 GROUP BY user_id) as c on a.user_id=c.user_id WHERE 1";

        if(!empty($where)){
            foreach($where as $key => $val){
                if ($key == 'nick_name') {
                    $sql .= " AND a.$key LIKE '%$val%'";
                }else{
                    if(!empty($val) && !in_array($key, ['create_time_start', 'create_time_end'])){
                        if($key=='platform' || $key=='channel'){
                            if($val){
                                $sql .= " AND b.$key = '$val'";
                            }
                        }else{
                            $sql .= " AND a.$key = $val";
                        }
                    } elseif ($key == 'create_time_start' && !empty($val)){
                        $sql .= " AND a.create_time >= $val";
                    } elseif ($key == 'create_time_end' && !empty($val)){
                        $sql .= " AND a.create_time < $val";
                    }
                }

            }
        }
        $sql .= " ORDER BY ";
        if($order){
            foreach($order as $k=>$v){
                if(!empty($v)){
                    $sql.="c.$k $v ,";
                }
            }
        }
        $sql .=" a.create_time DESC LIMIT $start, $size";
       // $sql .= " ORDER BY a.create_time DESC LIMIT $start, $size";

        return $this->getDB($sql)->executeRows($sql);
    }

	/**
	 * 获取用户信息
	 * @param $userId
	 * @return mixed
	 */
	public function getUserInfo($userId) {
		$sql = "select   `user_id`,`cid`, `uuid`, `phone`,`nick_name`,`sex`,`country`,`province`,`city`,`headimgurl`,`idcard_type`,`idcard_number`,`source`,`identity`,`dist_id`,`user_status`,`create_time`,`modify_time`,`last_login_time`,`last_login_ip`,`forbidden_say`,`forbidden_day`,`forbidden_time` from `$this->_table` WHERE `user_id` = $userId";
		return $this->getDB($sql)->executeRow($sql);
	}

    /**
     * 根据手机号获取用户信息
     * @param $phone
     * @return mixed
     */
	public function getUserInfoByPhone($phone){
        $sql = "select   `user_id`,`phone`,`nick_name`,`sex`,`country`,`province`,`city`,`headimgurl`,`idcard_type`,`idcard_number`,`source`,`identity`,`dist_id`,`user_status`,`create_time`,`modify_time`,`last_login_time`,`last_login_ip` from `$this->_table` WHERE `phone` = $phone";
        return $this->getDB($sql)->executeRow($sql);
  }

  public function getUserByCondition($condition = array(), $columns = array()) {
    $fields = " * ";
    if(!empty($columns)) {
      $fields = implode(', ', $columns);
    }

    $sql = "select $fields from `$this->_table`";
    if(!empty($condition)) {
      $sql .= " WHERE 1 = 1 ";
      foreach($condition as $fieldName => $value) {
        $sql .= " AND `$fieldName` = $value ";
      }
    }
    return $this->getDB($sql)->executeRow($sql);
  }

  public function getUsersByUUid($uuid) {
    $sql = "select `user_id`,`uuid`, `cid` from `$this->_table` where uuid = $uuid";
    return $this->getDB($sql)->executeRows($sql);
  }

	/**
	 * 新建一个用户
	 * @param $params
	 */
	public function newUser($params) {
		$this->insertData($params, $this->_table);
  }

  public function createUser($params) {
    $this->create($this->_table, $params);
    return $this->getInsertId();
  }

	/**
	 * 更新用户信息
	 * @param $uid
	 * @param $params
	 * @return int
	 */
	public function updateUser($uid, $params) {
		$updateString = StringHandler::newInstance()->getDBUpdateString($params);
		$sql = "update `" . $this->_table . "` set $updateString where `user_id`=$uid";
		$result = $this->getDB($sql)->executeNoResult($sql);
		return $result;
  }

  public function getUserDevice($condition = [], $platform = '') {
      if ($platform) {
            $sql = "select u.user_id,u.device_token from $this->_table u left join hl_channel c on u.cid = c.cid where u.user_status =1 and u.device_token <> '' and c.platform = '$platform'";
            return $this->getDB($sql)->executeRows($sql);
      }
      $condition['user_status'] = 1;
      $condition['device_token'] = [' <> ', "''"];
      return $this->select($this->_table, $condition, ['user_id', 'device_token'], 0, false, []);
  }


    //获取所有用户设备
    public function getUserDeviceAll($condition = []){
        $sql = "select u.user_id,u.device_token,c.platform from $this->_table u left join hl_channel c on u.cid = c.cid where u.user_status =1 and u.device_token <> '' ";

        if($condition){
            $sql.=$this->parseCondition($condition);
        }
        return $this->getDB($sql)->executeRows($sql);
    }


  public function getPayingUser($platform = '') {
     // $sql = "SELECT DISTINCT u.user_id, u.device_token FROM hl_user u LEFT JOIN hl_order o ON u.user_id = o.user_id WHERE o.`order_status` = 1 AND o.trade_type IN (3,6) AND u.device_token != '' AND o.order_amount > 0";
      $sql = "SELECT DISTINCT u.user_id, u.device_token FROM hl_user u LEFT JOIN hl_order o ON u.user_id = o.user_id LEFT JOIN hl_channel c on u.cid=c.cid WHERE o.`order_status` = 1 AND o.trade_type IN (3,6) AND u.device_token != '' AND o.order_amount > 0";
      if($platform){
          $sql.=" AND c.platform='$platform'";
      }
      return $this->getDB($sql)->executeRows($sql);
  }

  public function getDeviceByCondition($condition) {
      return $this->get($this->device_table, $condition);
  }

  public function addDevice($params) {
      return $this->insertData($params, $this->device_table);
  }

  public function updateDeviceByCondition($condition, $data) {
      return $this->updateByCondition($condition, $data, $this->device_table);
  }

}
