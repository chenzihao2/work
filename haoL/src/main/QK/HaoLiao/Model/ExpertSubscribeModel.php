<?php
/**
 * 专家订阅价格管理
 * User: YangChao
 * Date: 2018/10/20
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALUserExpertSubscribe;
use QK\HaoLiao\DAL\DALUserExpertWithDraw;

class ExpertSubscribeModel extends BaseModel {
    private $_redisModel;
    private $_dalUserExpertSubscribe;

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel('expert');
        $this->_dalUserExpertSubscribe = new DALUserExpertSubscribe($this->_appSetting);
    }

    /**
     * 根据时长获取专家订阅价格
     * @param $expertId
     * @param $lengthDay
     * @param bool $isYuan
     * @return mixed|null|string
     */
    public function getExpertSubscribeByDays($expertId, $lengthDay, $isYuan = true) {
        $redisKey = EXPERT_SUBSCRIBE_INFO . $expertId;
        $subscribeInfo = $this->_redisModel->redisGetHashList($redisKey, $lengthDay, true);
        if (empty($subscribeInfo)) {
            $subscribeInfo = $this->_dalUserExpertSubscribe->getExpertSubscribeByDays($expertId, $lengthDay);
            $this->_redisModel->redisSetHashList($redisKey, $lengthDay, $subscribeInfo);
        }
        if (!empty($subscribeInfo)) {
            $subscribeInfo['subscribe_price'] = $isYuan ? $this->ncPriceFen2Yuan($subscribeInfo['subscribe_price']) : $subscribeInfo['subscribe_price'];
        }
        return $subscribeInfo;
    }

    /**
     * 更新或新建订阅价格表（后台）
     * @param $params
     * @return int
     */
    public function updateSubscribe($params) {
        return $this->_dalUserExpertSubscribe->updateSubscribeInfo($params);
    }

}