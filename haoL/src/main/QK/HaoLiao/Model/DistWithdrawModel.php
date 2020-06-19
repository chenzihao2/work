<?php
/**
 * 分销商提现处理模块
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALUserDistMoneyChange;
use QK\HaoLiao\DAL\DALUserDistWitdraw;

class DistWithdrawModel extends BaseModel {

    private $_dalUserDistWitdraw;
    private $_dalUserDistMoneyChange;

    public function __construct() {
        parent::__construct();
        $this->_dalUserDistWitdraw = new DALUserDistWitdraw($this->_appSetting);
        $this->_dalUserDistMoneyChange = new DALUserDistMoneyChange($this->_appSetting);
    }

    /**
     * 提交提现申请
     * @param $distId
     * @param $withdrawMoney
     * @return bool
     */
    public function doWithDraw($distId, $withdrawMoney){
        //写入提现表
        $withDrawData = [];
        $withDrawData['dist_id'] = $distId;
        $withDrawData['withdraw_money'] = $withdrawMoney;
        $withDrawData['withdraw_status'] = 1;
        $withDrawData['withdraw_time'] = time();
        $withDrawId = $this->_dalUserDistWitdraw->newWithDraw($withDrawData);
        if($withDrawId){
            //写入用户扩展表
            $distExtraModel = new DistExtraModel();
            $res = $distExtraModel->updateWithdrawInfo($distId, $withdrawMoney);
            if($res){
                //写入变更记录表
                $changeData = [];
                $changeData['dist_id'] = $distId;
                $changeData['change_type'] = 2;
                $changeData['source'] = 5;
                $changeData['settle_amount'] = $withdrawMoney;
                $changeData['change_time'] = time();
                $this->_dalUserDistMoneyChange->newChange($changeData);
            }
        }
        return true;
    }

    /**
     * 获取分销商提现列表
     * @param $where
     * @param $page
     * @param $pageSize
     * @return array
     */
    public function getDistWithDrawList($where, $page, $pageSize){
        $start = ($page - 1) * $pageSize;
        $res = [];
        $res['total'] = $this->_dalUserDistWitdraw->getDistWithDrawListTotal($where);
        $res['list'] = $this->_dalUserDistWitdraw->getDistWithDrawList($where, $start, $pageSize);
        return $res;
    }

    /**
     * 设置提现信息
     * @param $withdrawId
     * @param $data
     * @return bool
     */
    public function setDistWithDraw($withdrawId, $data) {
        $this->_dalUserDistWitdraw->setDistWithDraw($withdrawId, $data);
        return true;
    }

    /**
     * 获取提现中的金额
     * @param $distId
     * @return array|bool
     */
    public function getDistWithDraw($distId){
        $distWithDrawInfo = $this->_dalUserDistWitdraw->getDistWithDraw($distId);
        if(!empty($distWithDrawInfo)){
            $distWithDrawInfo['withdraw_money_yuan'] = $this->ncPriceFen2Yuan($distWithDrawInfo['withdraw_money']);
        }
        return $distWithDrawInfo;
    }

}