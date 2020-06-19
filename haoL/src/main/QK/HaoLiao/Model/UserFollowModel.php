<?php
/**
 * 用户关注信息处理类
 * User: YangChao
 * Date: 2018/10/19
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALUserFollowExpert;
use QK\HaoLiao\DAL\DALUserFollowSchedule;

class UserFollowModel extends BaseModel {

    private $_redisModel;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("user");
    }

    /**
     * 关注专家列表
     * @param $userId
     * @param $page
     * @param $size
     * @return array
     */
    public function followExpertList($userId, $page = 0, $size = 0) {
        $redisKey = USER_FOLLOW_EXPERT . $userId;
        //根据分值范围获取redis数据
        if ($page) {
            $start = ($page - 1) * $size;
            $max = $start + $size - 1;
        } else {
            $start = 0;
            $max = -1;
        }
        //根据分值范围获取redis数据
        $expertIdList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $max);
        if (empty($expertIdList)) {
            $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
            //获取mysql数据
            //$expertList = $dalUserFollowExpert->getUserFollowExpertList($userId, $page, $size);
            //UPDATE:用户绑定后通过UUID查找
            $condition = array('follow_status' => 1);
            $userModel = new UserModel();
            $userInfo = $userModel->getUserInfo($userId);
            if($userInfo['uuid']) {
              $users = $userModel->getUsersByUUid($userInfo['uuid']);
              $uids = implode(', ', array_column($users, 'user_id'));
              $condition['user_id'] = ['in', "($uids)"];
            }else {
              $condition['user_id'] = $userId;
            }
            $expertList = $dalUserFollowExpert->getFollowList($condition, array('expert_id'), ($page - 1) * $size, $size, array());

            $expertIdList = [];
            if (!empty($expertList)) {
                foreach ($expertList as $key => $val) {
                    //相关数据入redis
                    $expertIdList[] = $expertId = $val['expert_id'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $expertId);
                }
            }
        }
        $followList = [];
        $expertModel = new ExpertModel();
        $betRecordModel = new BetRecordModel();
        $resourceModel = new ResourceModel();
        if (!empty($expertIdList)) {
            foreach ($expertIdList as $key => $expertId) {
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $followInfo['expert_id'] = $expertId;
                $followInfo['expert_name'] = $expertInfo['expert_name'];
                $followInfo['headimgurl'] = $expertInfo['headimgurl'];
                $followInfo['resource_num'] = $resourceModel->getResourceTotalByExpertId($expertId, 0);
                $followInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId);
                $followList[] = $followInfo;
            }
        }
        return $followList;
    }

    public function getFollowList($condition = array(), $fields = array(), $offset = 0, $limit = 10, $orderBy = array(), $platform = 1) {
      $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
      $followedList = $dalUserFollowExpert->getFollowList($condition, $fields, $offset, $limit, $orderBy);
      
      $expertModel = new ExpertModel();
      $betRecordModel = new BetRecordModel();
      $resourceModel = new ResourceModel();
      if (!empty($followedList)) {
        foreach ($followedList as $key => $followed) {
          $expertInfo = $expertModel->getExpertInfo($followed['expert_id']);
          $followedList[$key]['expert_name'] = $expertInfo['expert_name'];
          $followedList[$key]['headimgurl'] = $expertInfo['headimgurl'];
          $followedList[$key]['resource_num'] = $resourceModel->getResourceTotalByExpertId($followed['expert_id'], 0);
          $followedList[$key]['combat_gains_ten'] = $betRecordModel->nearTenScore($followed['expert_id'], $platform);
        }
      }
      return $followedList;
    }

    /**
     * 关注/取消专家
     * @param $userId
     * @param $expertId
     */
    public function followExpert($userId, $expertId, $status = 2) {
        //用户关注数修改
        $userModel = new UserModel();
        //增加专家冻结金额和订阅人数
        $expertExtraModel = new ExpertExtraModel();
        $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
        if ($this->checkFollowExpertStatus($userId, $expertId)) {
          if($status == 0 || $status == 2) {

	    $uids = [$userId];
            $userInfo = $userModel->getUserInfo($userId);
            if($userInfo['uuid']) {
              $users = $userModel->getUsersByUUid($userInfo['uuid']);
              $uids = array_column($users, 'user_id');
            }
            
            foreach($uids as $uid) {
              $dalUserFollowExpert->updateFollow($uid, $expertId, 0);
            }
            //$dalUserFollowExpert->updateFollow($userId, $expertId, 0);
            $this->unFollowExpert($userId, $expertId);
            //增加用户已支付金额和已订阅专家数
            $userExtraIncOrDec = ['follow_num' => '-' . 1];
            $expertExtraIncOrDec = ['follow_num' => '-' . 1];
            $userModel->setUserExtraIncOrDec($userId, $userExtraIncOrDec);
            $expertExtraModel->setExpertExtraIncOrDec($expertId, $expertExtraIncOrDec);
          }
        } else {
          if($status == 1 || $status == 2) {
            $dalUserFollowExpert->updateFollow($userId, $expertId);
            $this->setFollowExpert($userId, $expertId);
            //增加用户已支付金额和已订阅专家数
            $userExtraIncOrDec = ['follow_num' => '+' . 1];
            $expertExtraIncOrDec = ['follow_num' => '+' . 1];
            $userModel->setUserExtraIncOrDec($userId, $userExtraIncOrDec);
            $expertExtraModel->setExpertExtraIncOrDec($expertId, $expertExtraIncOrDec);
          }
        }
    }

    /**
     * 关注专家
     * @param $userId
     * @param $expertId
     */
    private function setFollowExpert($userId, $expertId) {
      $userModel = new UserModel();
      $userInfo = $userModel->getUserInfo($userId);
      if($userInfo['uuid']) {
        $users = $userModel->getUsersByUUid($userInfo['uuid']);
        $uids = array_column($users, 'user_id');
        foreach($uids as $uid) {
          $redisKey = USER_FOLLOW_EXPERT . $uid;
          $this->_redisModel->redisDel($redisKey);
        } 
      } else {
        $redisKey = USER_FOLLOW_EXPERT . $userId;
        $this->_redisModel->redisDel($redisKey);
      }
    }

    /**
     * 取消关注专家
     * @param $userId
     * @param $expertId
     */
    private function unFollowExpert($userId, $expertId) {
      $userModel = new UserModel();
      $userInfo = $userModel->getUserInfo($userId);
      if($userInfo['uuid']) { 
        $users = $userModel->getUsersByUUid($userInfo['uuid']);
        $uids = array_column($users, 'user_id');
        foreach($uids as $uid) { 
          $redisKey = USER_FOLLOW_EXPERT . $uid;
//        $this->_redisModel->redisZAdd($redisKey, $expertId,$expertId);
          $this->_redisModel->redisDel($redisKey);
        } 
      } else { 
        $redisKey = USER_FOLLOW_EXPERT . $userId;
        $this->_redisModel->redisDel($redisKey);
      }
    }

    /**
     * 检查是否关注专家
     * @param $userId
     * @param $expertId
     * @return bool
     */
    /*public function checkFollowExpertStatus($userId, $expertId) {
        $redisKey = USER_FOLLOW_EXPERT . $userId;
        $check = $this->_redisModel->redisZRank($redisKey, $expertId);
        if(is_numeric($check)){
            return true;
        }else{
            $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
            $followInfo = $dalUserFollowExpert->checkUserFollowExpert($userId, $expertId);
            if(!empty($followInfo)){
                return true;
            }
            return false;
        }
    }*/

    /**
     * 获取关注专家数 （TODO：走redis）
     * @param $userId
     * @return mixed
     */
    public function getUserFollowExpertNumber($userId) {
      $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
      $condition = array('follow_status' => 1);
      $userModel = new UserModel();
      $userInfo = $userModel->getUserInfo($userId);
      if($userInfo['uuid']) {
        $users = $userModel->getUsersByUUid($userInfo['uuid']);
        $uids = implode(', ', array_column($users, 'user_id'));
        $condition['user_id'] = ['in', "($uids)"];
      }else {
        $condition['user_id'] = $userId;
      }
      $followList = $dalUserFollowExpert->getFollowList($condition, array('expert_id'));
      return count($followList);
        //return $dalUserFollowExpert->getUserFollowExpertNumber($userId);
    }

    /**
     * 关注/取消某场比赛
     * @param $userId
     * @param $scheduleId
     */
    public function followSchedule($userId, $scheduleId) {
        $dalUserFollowSchedule = new DALUserFollowSchedule($this->_appSetting);
        if ($this->checkFollowScheduleStatus($userId, $scheduleId)) {
            $dalUserFollowSchedule->updateFollow($userId, $scheduleId, 0);
            $this->unFollowSchedule($userId, $scheduleId);
        } else {
            $dalUserFollowSchedule->updateFollow($userId, $scheduleId);
            $this->setFollowSchedule($userId, $scheduleId);
        }
    }

    /**
     * 关注某场比赛
     * @param $userId
     * @param $scheduleId
     */
    private function setFollowSchedule($userId, $scheduleId) {
        $redisKey = USER_FOLLOW_SCHEDULE . $userId;
        $this->_redisModel->redisSadd($redisKey, $scheduleId);
    }

    /**
     * 取消关注某场比赛
     * @param $userId
     * @param $scheduleId
     */
    private function unFollowSchedule($userId, $scheduleId) {
        $redisKey = USER_FOLLOW_SCHEDULE . $userId;
        $this->_redisModel->redisSRem($redisKey, $scheduleId);
    }

    /**
     * 检查是否关注某场比赛
     * @param $userId
     * @param $scheduleId
     * @return bool
     */
    public function checkFollowScheduleStatus($userId, $scheduleId) {
        $redisKey = USER_FOLLOW_SCHEDULE . $userId;
        return $this->_redisModel->redisSismember($redisKey, $scheduleId);
    }

    public function checkFollowExpertStatus($userId, $expertId) {
      if (empty($userId)) {
        return false;
      }
      $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
      $condition = array('expert_id' => $expertId, 'follow_status' => 1);
      $userModel = new UserModel();
      $userInfo = $userModel->getUserInfo($userId);
      if($userInfo['uuid']) {
        $users = $userModel->getUsersByUUid($userInfo['uuid']);
        $uids = implode(', ', array_column($users, 'user_id'));
        $condition['user_id'] = ['in', "($uids)"];
      }else {
        $condition['user_id'] = $userId;
      }
      $followInfo = $dalUserFollowExpert->getFollowByCondition($condition, array('expert_id'));
      if(!empty($followInfo)){
        return true;
      }
      return false;
    }
	
	
	
    /*
     *新关注专家列表---新增
     */

    public function followMyExpertList($userId, $page = 0, $size = 0,$platform) {
        $redisKey = USER_FOLLOW_EXPERT . $userId.':my';

        //根据分值范围获取redis数据
        if ($page) {
            $start = ($page - 1) * $size;
            $max = $start + $size - 1;
        } else {
            $start = 0;
            $max = -1;
        }
        //根据分值范围获取redis数据
        $expertIdList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $max);

       // if (empty($expertIdList)) {
            $dalUserFollowExpert = new DALUserFollowExpert($this->_appSetting);
            //获取mysql数据
            //$expertList = $dalUserFollowExpert->getUserFollowExpertList($userId, $page, $size);
            //UPDATE:用户绑定后通过UUID查找
            //$condition = array('follow_status' => 1);
            $userModel = new UserModel();
            $userInfo = $userModel->getUserInfo($userId);

            if($userInfo['uuid']) {
                $users = $userModel->getUsersByUUid($userInfo['uuid']);
                $uids = implode(', ', array_column($users, 'user_id'));

                $userId=$uids;
            }


            $expertList = $dalUserFollowExpert->getNewFollowList($userId,  ($page - 1) * $size, $size, array(),$platform);


            $expertIdList = [];
            if (!empty($expertList)) {
                foreach ($expertList as $key => $val) {
                    //专家id存入redis
                    $expertIdList[] = $expertId = $val['expert_id'];

                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $expertId);
                }
            }
        //}
        $followList = [];
        $expertModel = new ExpertModel();
        $betRecordModel = new BetRecordModel();
        $resourceModel = new ResourceModel();
        $ExpertExtraModel = new ExpertExtraModel();
        //专家详细信息
        if (!empty($expertIdList)) {
            foreach ($expertIdList as $key => $expertId) {
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $expertInfoExtra = $ExpertExtraModel->getExpertExtraInfo($expertId);
				$followInfo['tag'] = $expertInfo['tag'];
                $followInfo['expert_id'] = $expertId;
                $followInfo['expert_name'] = $expertInfo['expert_name'];
                $followInfo['headimgurl'] = $expertInfo['headimgurl'];
                $followInfo['identity_desc'] = $expertInfo['identity_desc'];
                // $followInfo['publish_resource_num'] = $resourceModel->getResourceTotalByExpertId($expertId, 0);//料总数
                $followInfo['publish_resource_num'] =$expertInfoExtra['publish_resource_num'];//料总数
                //$followInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId);//近十场战绩
                //新增
                $followInfo['resource_num'] = $resourceModel->getRecommendResourceCount($expertId);//推荐料总数
                $followInfo['profit_rate'] = $expertInfoExtra['profit_rate'];//盈利率
                $followInfo['profit_all'] = $expertInfoExtra['profit_all'];//盈利率
                $followInfo['max_bet_record'] = $expertInfoExtra['max_bet_record_v2'];//命中率
                $followInfo['max_red_num'] = $expertInfoExtra['max_red_num'];//最高连红
				//$r=$resourceModel->getExpertNewResource($expertId);//获取最新的料的时间
				//date("Y-m-d H:i:s",$r[0]['create_time'])
                $followList[] = $followInfo;

            }
        }

      //  dump($followList);
        return $followList;
    }






}
