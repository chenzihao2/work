<?php

namespace QK\HaoLiao\DAL;

class DALPushMsg extends BaseDAL {
    private $msg_table = 'hl_push_msg';
    protected $_user_msg_table = "hl_user_msg";

  //public function __construct(AppSetting $appSetting) {
  //  parent::__construct($appSetting);
  //}

  public function getMsgList($condition = [], $fields = [], $page = 1, $pagesize = 20, $orderBy = [], $relation = 0) {
    $offset = ($page - 1) * $pagesize;
    $select_fields = " * ";
    if (!empty($fields)) {
      $select_fields = implode(',', $fields);
    }
    $sql = "SELECT $select_fields FROM `$this->msg_table` ";
    if ($relation) {
      $sql .= " LEFT JOIN `$this->_user_msg_table` ON `$this->msg_table`.`id` = `$this->_user_msg_table`.`msg_id` ";
    }
    $sql .= " WHERE 1 = 1 ";
    $sql .= $this->parseCondition($condition);

    if (!empty($orderBy)) {
      $ordersArr = array();
      foreach($orderBy as $orderKey => $orderVal) {
        $ordersArr[] = "`$orderKey` $orderVal";
      }
      $orderStr = implode(',', $ordersArr);
      $sql .= " ORDER BY $orderStr";
    }

    if ($pagesize) {
      $sql .= " limit $pagesize";
    }
    if (!empty($offset)) {
      $sql .= " offset $offset";
    }
    return $this->getDB($sql)->executeRows($sql);
  }
  
  public function getMsgCount($condition = [], $relation = 0) {
    $sql = "SELECT count(*) as count FROM `$this->msg_table` ";
    if ($relation) {
      $sql .= " LEFT JOIN `$this->_user_msg_table` ON `$this->msg_table`.`id` = `$this->_user_msg_table`.`msg_id` ";
    }
    $sql .= " WHERE 1 = 1 ";
    $sql .= $this->parseCondition($condition);
    return $this->getDB($sql)->executeRows($sql);
  }

  public function getMsgByUserId($user_id, $page, $pagesize) {
      $start = ($page - 1) * $pagesize; 
      $sql = "select m.title, m.text, m.send_time, m.after_open from $this->_user_msg_table r left join $this->msg_table m on r.msg_id = m.id where r.user_id = $user_id and m.msg_type = 1 and (m.status = 1 or m.ios_status = 1) order by r.send_time desc limit $start, $pagesize";
      $data = $this->getDB($sql)->executeRows($sql);
      $sql_count = "select count(m.id) as count from $this->_user_msg_table r left join $this->msg_table m on r.msg_id = m.id where r.user_id = $user_id and m.msg_type = 1 and (m.status = 1 or m.ios_status = 1) order by r.send_time desc";
      $total = $this->getDB($sql_count)->executeValue($sql_count);
      return ['data' => $data, 'total' => $total];
   }


    public function addMsg($params) {
        $this->insertData($params, $this->msg_table);
        return $this->getInsertId();
    }

    public function addRelation($params) {
        return $this->insertData($params, $this->_user_msg_table);
    }

    public function existsMsg($condition) {
        return $this->get($this->msg_table, $condition, [], 0, false, []);
    }

    public function getMsgByCondition($condition) {
        return $this->select($this->msg_table, $condition, [], 0, false, []);
    }

    public function getDeviceByMsgId($msg_id) {
        return $this->select($this->_user_msg_table, ['msg_id' => $msg_id], ['device_token','platform'], 0, false, []);
    }

    public function updateMsg($condition, $data) {
        return $this->updateByCondition($condition, $data, $this->msg_table);
    }

    public function getMsgRelation($condition = [], $fields = [], $page = 1, $pagesize = 50, $orderBy = []) {
      $offset = ($page - 1) * $pagesize;
      return $this->select($this->_user_msg_table, $condition, $fields, $offset, $pagesize, []);
    }
}
