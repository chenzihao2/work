<?php

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;
use QK\WSF\Settings\AppSetting;

class DALChannel extends BaseDAL {
	protected $_table = "hl_channel";

	public function __construct(AppSetting $appSetting) {
		parent::__construct($appSetting);
	}

	public function getUserByChannel($condition = array()) {
    return $this->get($this->_table, $condition);
  }

	public function createChannel($params) {
    $this->create($this->_table, $params);
    return $this->getInsertId();
	}

  public function updateChannel($cid, $params) {
    return $this->update($this->_table, $params, array('cid' => $cid));
	}

  public function getUserChannelInfo($uid) {
    $sql = "SELECT * from $this->_table LEFT JOIN hl_user ON hl_user.cid = $this->_table.cid WHERE hl_user.user_id = $uid";
    return $this->getDB($sql)->executeRow($sql);
  }
}
