<?php
/**
 * 专家提现管理
 * User: WangHui
 * Date: 2018/10/11
 * Time: 18:00
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALUserExpertWithDraw;

class ExpertWithDrawModel extends BaseModel {
    private $_dalUserExpertWithDraw;

    public function __construct() {
        parent::__construct();
        $this->_dalUserExpertWithDraw = new DALUserExpertWithDraw($this->_appSetting);

    }

    /**
     * 获取专家提现中的记录
     * @param $expertId
     * @return mixed
     */
    public function getWithDrawing($expertId) {
        $info = $this->_dalUserExpertWithDraw->getWithDrawing($expertId);
        if(!empty($info)){
            $info['service_fee'] = $this->ncPriceFen2Yuan($info['service_fee']);
            $info['tax_fee'] = $this->ncPriceFen2Yuan($info['tax_fee']);
            $info['withdraw_money'] = $this->ncPriceFen2Yuan($info['withdraw_money']);
            $info['withdraw_time'] = $this->friendlyDate($info['withdraw_time']);
        }
        return $info;
    }

}