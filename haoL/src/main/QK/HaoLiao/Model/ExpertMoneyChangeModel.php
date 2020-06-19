<?php
/**
 * 专家金额变更信息处理类
 * User: YangChao
 * Date: 2018/10/30
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALUserExpertMoneyChange;

class ExpertMoneyChangeModel extends BaseModel {

    private $_redisModel;
    private $_dalUserExpertMoneyChange;

    public function __construct(){
        parent::__construct();
        $this->_redisModel = new RedisModel("expert");
        $this->_dalUserExpertMoneyChange = new DALUserExpertMoneyChange($this->_appSetting);
    }

    /**
     * 增加专家金额变更记录
     * @param     $userId
     * @param     $expertId
     * @param     $changeType
     * @param     $source
     * @param     $payAmount
     * @param int $separateAmount
     * @param int $settleAmount
     * @return int
     */
    public function setMoneyChange($userId, $expertId, $changeType, $source, $payAmount, $separateAmount = 0, $settleAmount = 0){
        $data = [];
        $data['user_id'] = $userId;
        $data['expert_id'] = $expertId;
        $data['change_type'] = $changeType;
        $data['source'] = $source;
        $data['pay_amount'] = $payAmount;

        //获取用户最后一条金额变更记录
//        $lastChange = $this->getExpertLastChange($expertId);
//        $lastTotalAmount = !empty($lastChange) ? $lastChange['total_amount'] : 0;

        if($changeType == 1){
            //增加
            //获取分成费率
            $expertRateModel = new ExpertRateModel();
            $rateInfo = $expertRateModel->getExpertRate($expertId);
            $rate = $rateInfo['rate'];
            //结算金额
            $data['settle_amount'] = $settleAmount = $this->ncPriceCalculate($payAmount, '*',  $rate, 0);
            //分成金额  支付总额-结算金额
            $data['separate_amount'] = $payAmount - $settleAmount;
        } else {
            //减少
            $data['separate_amount'] = $separateAmount;
            $data['settle_amount'] = $settleAmount;
        }

        //清除redis缓存
        $redisKey = EXPERT_LAST_CHANGE_INFO . $expertId;
        $this->_redisModel->redisDel($redisKey);

        $data['change_time'] = time();
        $res = $this->_dalUserExpertMoneyChange->newChange($data);
        return $res;
    }

    /**
     * 获取金额变更最后一条记录
     * @param $expertId
     * @return bool|mixed|null|string
     */
    public function getExpertLastChange($expertId){
        $redisKey = EXPERT_LAST_CHANGE_INFO . $expertId;
        $lastChange = $this->_redisModel->redisGet($redisKey, true);
        if(empty($lastChange)){
            $lastChange = $this->_dalUserExpertMoneyChange->getExpertLastChange($expertId);
            $this->_redisModel->redisSet($redisKey, $lastChange);
        }
        return $lastChange;
    }

}