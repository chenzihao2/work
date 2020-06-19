<?php
/**
 * 用户订阅信息处理类
 * User: YangChao
 * Date: 2018/10/19
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALResource;
use QK\HaoLiao\DAL\DALUserSubscribe;

class UserSubscribeModel extends BaseModel {

    private $_redisModel;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("user");
    }

    /**
     * 设置用户订阅信息
     * @param $userId
     * @param $expertId
     * @param $startTime
     * @param $endTime
     * @return int
     */
    public function setUserSubscribe($userId, $expertId, $payAmount, $startTime, $endTime) {
        $data = [];
        $dalUserSubscribe = new DALUserSubscribe($this->_appSetting);
        $data['user_id'] = $userId;
        $data['expert_id'] = $expertId;
        $data['start_time'] = $startTime;
        $data['end_time'] = $endTime;
        $data['pay_amount'] = $payAmount;

        //清除对应rediskey
        $redisKey[] = USER_SUBSCRIBE_EXPERT . $expertId . ':' . $userId;
        $redisKey[] = USER_SUBSCRIBE_EXPERT_LIST . $userId;
        $redisKey[] = USER_SUBSCRIBE_EXPERT_ALL_LIST . $userId;
        $this->_redisModel->redisDel($redisKey);

        return $dalUserSubscribe->setUserSubscribe($data);
    }

    /**
     * 检查用户是否订阅某专家
     * @param $userId
     * @param $expertId
     * @return bool
     */
    public function checkUserSubscribeExpert($userId, $expertId) {
        if (!$userId || !$expertId) {
            return false;
        }
        //获取用户订阅详情
        $userSubscribe = $this->getUserSubscribeByExpertId($userId, $expertId);
        if (empty($userSubscribe)) {
            return false;
        }

        return $userSubscribe['end_time'] > time() ? true : false;
    }

    /**
     * 获取用户订阅详情
     * @param $userId
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function getUserSubscribeByExpertId($userId, $expertId) {
        $redisKey = USER_SUBSCRIBE_EXPERT . $expertId . ':' . $userId;
        $is_redisKey = $this->_redisModel->redisKeys($redisKey);
        if (!$is_redisKey) {
            $dalUserSubscribe = new DALUserSubscribe($this->_appSetting);
            $userSubscribeInfo = $dalUserSubscribe->getUserSubscribeByExpertId($userId, $expertId);
            $this->_redisModel->redisSet($redisKey, $userSubscribeInfo);
            $this->_redisModel->redisExpireAt($redisKey, $userSubscribeInfo['end_time']);
        } else {
            $userSubscribeInfo = $this->_redisModel->redisGet($redisKey, true);
        }

        return $userSubscribeInfo;
    }

    /**
     * 用户订阅列表
     * @param $userId
     * @param $page
     * @param $size
     * @return array
     */
    public function getUserSubscribeList($userId, $page = 0, $size = 0) {
        $redisKey = USER_SUBSCRIBE_EXPERT_LIST . $userId;
        //根据分值范围获取redis数据
        if ($page) {
            $start = ($page - 1) * $size;
            $max = $start + $size - 1;
        } else {
            $start = 0;
            $max = -1;
        }
        $expertList = $this->_redisModel->redisZRangeByScore($redisKey, $start, $max);
        if (empty($expertList)) {
            //获取mysql数据
            $dalUserSubscribe = new DALUserSubscribe($this->_appSetting);
            $subscribeExpertList = $dalUserSubscribe->getUserSubscribeList($userId, $page, $size);
            $expertList = [];
            if (!empty($subscribeExpertList)) {
                foreach ($subscribeExpertList as $key => $val) {
                    //相关数据入redis
                    $expertList[] = $expertId = $val['expert_id'];
                    $this->_redisModel->redisZAdd($redisKey, $key + $start, $expertId);
                }
            }
            $expireTime = $dalUserSubscribe->getSubscribeNearEndTime($userId);
            $this->_redisModel->redisExpireAt($redisKey, $expireTime);
        }
        $result = [];
        if (!empty($expertList)) {
            $expertModel = new ExpertModel();
            $betRecordModel = new BetRecordModel();
            $resourceModel = new ResourceModel();
            foreach ($expertList as $expertId) {
                //根据订单号获取订单详情
                $subscribeInfo = $this->getUserSubscribeByExpertId($userId, $expertId);
                $scoreInfo = $betRecordModel->nearTenScore($expertId);
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $subInfo = [];
                $subInfo['expert_id'] = $expertId;
                $subInfo['expert_name'] = $expertInfo['expert_name'];
                $subInfo['headimgurl'] = $expertInfo['headimgurl'];
                $subInfo['resource_num'] = $resourceModel->getResourceTotalByExpertId($expertId, 0);
                $subInfo['subscribe_end_time'] = $this->friendlyDate($subscribeInfo['end_time'], 'full');
                $subInfo['combat_gains_ten'] = $scoreInfo;
                $result[] = $subInfo;
            }
        }
        return $result;
    }

    /**
     * 用户订阅列表带料信息
     * @param $userId
     * @return array
     */
    public function getUserSubscribeListWithResource($userId) {
        $redisKey = USER_SUBSCRIBE_EXPERT_ALL_LIST . $userId;
        //根据分值范围获取redis数据
        $expertList = $this->_redisModel->redisZRangeByScore($redisKey, 0, -1);
        if (empty($expertList)) {
            //获取mysql数据
            $dalUserSubscribe = new DALUserSubscribe($this->_appSetting);
            $subscribeExpertList = $dalUserSubscribe->getUserSubscribeList($userId);
            $expertList = [];
            if (!empty($subscribeExpertList)) {
                foreach ($subscribeExpertList as $key => $val) {
                    //相关数据入redis
                    $expertList[] = $expertId = $val['expert_id'];
                    $this->_redisModel->redisZAdd($redisKey, $key, $expertId);
                }
            }
        }
        $result = [];
        if (!empty($expertList)) {
            $expertModel = new ExpertModel();
            $expertExtraModel = new ExpertExtraModel();
            $resourceModel = new ResourceModel();
            $betRecordModel = new BetRecordModel();
            foreach ($expertList as $expertId) {
                $scoreInfo = $betRecordModel->nearTenScore($expertId);
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);
                $subInfo = [];
                $subInfo['expert_id'] = $expertId;
                $subInfo['expert_name'] = $expertInfo['expert_name'];
                $subInfo['headimgurl'] = $expertInfo['headimgurl'];
                $subInfo['resource_num'] = $expertExtraInfo['publish_resource_num'];
                $subInfo['combat_gains_ten'] = $scoreInfo;
//                $dalResource = new DALResource($this->_appSetting);
//                $resourceList = $dalResource->getExpertResourceList($expertId);
//                foreach ($resourceList as $resourceId) {
//                    $resourceId = $resourceId['resource_id'];
//                    $resourceDetailInfo = $resourceModel->getResourceDetailedInfo($resourceId['resource_id']);
//                    $resourceInfo = [];
//                    $resourceInfo['resource_id'] = $resourceDetailInfo['resource_id'];
//                    $resourceInfo['title'] = $resourceDetailInfo['title'];
//                    $resourceInfo['price'] = $resourceDetailInfo['price'];
//                    $resourceInfo['resource_type'] = $resourceDetailInfo['resource_type'];
//                    $resourceInfo['view_num'] = $resourceDetailInfo['view_num'];
//                    $resourceInfo['is_schedule_over'] = $resourceDetailInfo['is_schedule_over'];
//                    $resourceInfo['create_time'] = $resourceDetailInfo['create_time'];
//                    $resourceInfo['schedule'] = $resourceModel->getResourceScheduleList($resourceId['resource_id']);
//
//                    $subInfo['resource_list'][] = $resourceInfo;
//                }
                $result[] = $subInfo;
            }
        }
        return $result;
    }

    /**
     * 获取指定有效期内的订阅数据
     * @param $nowTime
     * @return mixed
     */
    public function getEffectiveSubscribeList($nowTime){
        $dalUserSubscribe = new DALUserSubscribe();
        $effectiveSubscribeList = $dalUserSubscribe->getEffectiveSubscribeList($nowTime);
        return $effectiveSubscribeList;
    }

}