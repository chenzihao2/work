<?php
/**
 * 分销商金额变更处理模块
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALUserDistMoneyChange;

class DistMoneyChangeModel extends BaseModel {

    private $_dalUserDistMoneyChange;

    public function __construct() {
        parent::__construct();
        $this->_dalUserDistMoneyChange = new DALUserDistMoneyChange($this->_appSetting);
    }

    /**
     * 获取分销商信息
     * @param $distId
     * @return bool|mixed|null|string
     */
    public function getDistMoneyChangeList($distId, $where = [], $page = 1, $pagesize = 10) {
        $start = ($page - 1) * $pagesize;
        $moneyChangeList = $this->_dalUserDistMoneyChange->getDistMoneyChangeList($distId, $where, $start, $pagesize);

        if (!empty($moneyChangeList)) {
            $userModel = new UserModel();
            foreach ($moneyChangeList as $key => $val) {
                $userInfo = $userModel->getUserInfo($val['user_id']);
                $moneyChangeList[$key]['headimgurl'] = $userInfo['headimgurl'];
                $moneyChangeList[$key]['nick_name'] = $userInfo['nick_name'];
                $moneyChangeList[$key]['settle_amount'] = $this->ncPriceFen2Yuan($val['settle_amount']);
            }
        }
        return $moneyChangeList;
    }


    /**
     * 增加分销商金额变更记录
     * @param     $userId
     * @param     $distId
     * @param     $changeType
     * @param     $source
     * @param     $payAmount
     * @param int $separateAmount
     * @param int $settleAmount
     * @return int
     */
    public function setMoneyChange($userId, $distId, $changeType, $source, $payAmount, $separateAmount = 0, $settleAmount = 0){
        $data = [];
        $data['user_id'] = $userId;
        $data['dist_id'] = $distId;
        $data['change_type'] = $changeType;
        $data['source'] = $source;
        $data['pay_amount'] = $payAmount;

        if($changeType == 1){
            //增加
            //获取分成费率
            $distRateModel = new DistRateModel();
            $rateInfo = $distRateModel->getDistRateInfo($distId);
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

        $data['change_time'] = time();
        $res = $this->_dalUserDistMoneyChange->newChange($data);
        return $res;
    }

}