<?php
namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\DAL\DALChannel;
use QK\HaoLiao\DAL\DALUser;

class ChannelModel extends BaseModel {

  public function __construct() {
    parent::__construct();
    $this->_channelDal = new DALChannel($this->_appSetting);
    $this->_userDal = new DALUser($this->_appSetting);
  }

  public function getUserByChannel($condition = array()) {
    $channelInfo = $this->_channelDal->getUserByChannel($condition);
    if(empty($channelInfo)) {
      return $channelInfo;
    }
    $fields = array('user_id','uuid', 'cid', 'phone','nick_name','sex','country','province','city','headimgurl','user_status','last_login_time','identity');
    $userInfo = $this->_userDal->getUserByCondition(['cid' => $channelInfo['cid']], $fields);
    $userInfo['target'] = $channelInfo['target'];
    $userInfo['platform'] = $channelInfo['platform'];
    $userInfo['cid'] = $userInfo['cid']?$userInfo['cid']:$channelInfo['cid'];
    return $userInfo;
  }

  public function getUserChannelInfo($uid) {
    return $this->_channelDal->getUserChannelInfo($uid);
  }

  public function getChannel($cid) {
    $condition = array('cid' => $cid);
    return $this->_channelDal->getUserByChannel($condition);
  }

  public function createChannel($channelEntity) {
    return $this->_channelDal->createChannel($channelEntity);
  }

  public function updateChannelInfo($cid, $updateInfo) {
    return $this->_channelDal->updateChannel($cid, $updateInfo);
  }

  public function getBoundInfo($uuid) {
    $res = array();
    $users = $this->_userDal->getUsersByUUid($uuid);
    foreach($users as $userInfo) {
      $channelInfo = $this->_channelDal->getUserByChannel(['cid' => $userInfo['cid']]);
      if($channelInfo['target'] == 'wx') {
        $res[$channelInfo['target']] = $channelInfo['nickname'];
      } else {
        $res[$channelInfo['target']] = $channelInfo['mobile'];
      }
    }
    return $res;
  }

}
