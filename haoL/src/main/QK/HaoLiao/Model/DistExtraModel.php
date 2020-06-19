<?php
/**
 * 分销商扩展处理模块
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALUserDistExtra;

class DistExtraModel extends BaseModel {

    private $_dalUserDistExtra;

    public function __construct() {
        parent::__construct();
        $this->_dalUserDistExtra = new DALUserDistExtra($this->_appSetting);
    }

    /**
     * 获取分销商信息
     * @param $distId
     * @return bool|mixed|null|string
     */
    public function getDistExtraInfo($distId) {
        $redisModel = new RedisModel('expert');
        $redisKey = DIST_EXTRA_INFO . $distId;
        $distExtraInfo = $redisModel->redisGet($redisKey, true);
        if (empty($distExtraInfo)) {
            $distExtraInfo = $this->_dalUserDistExtra->getDistExtraInfo($distId);
            $redisModel->redisSet($redisKey, $distExtraInfo);
        }
        if(!empty($distExtraInfo)){
            $distExtraInfo['income_yuan'] = $this->ncPriceFen2Yuan($distExtraInfo['income']);
            $distExtraInfo['balance_yuan'] = $this->ncPriceFen2Yuan($distExtraInfo['balance']);
            $distExtraInfo['withdrawed_yuan'] = $this->ncPriceFen2Yuan($distExtraInfo['withdrawed']);
        }
        return $distExtraInfo;
    }

    /**
     * 修改用户提现相关金额
     * @param $distId
     * @param $withdrawMoney
     * @return int
     */
    public function updateWithdrawInfo($distId, $withdrawMoney){
        $res = $this->_dalUserDistExtra->updateWithdrawInfo($distId, $withdrawMoney);
        $redisModel = new RedisModel('expert');
        $redisKey = DIST_EXTRA_INFO . $distId;
        $redisModel->redisDel($redisKey);
        return $res;
    }

    /**
     * 设置自增或者自减分销商扩展信息
     * @param $distId
     * @param $params
     * @return mixed
     */
    public function setDistExtraIncOrDec($distId, $params){
        $redisModel = new RedisModel('expert');
        $redisKey = DIST_EXTRA_INFO . $distId;
        $redisModel->redisDel($redisKey);
        return $this->_dalUserDistExtra->setDistExtraIncOrDec($distId, $params);
    }

}