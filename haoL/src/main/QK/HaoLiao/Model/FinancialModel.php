<?php
/**
 * 财务数据信息处理类
 * User: YangChao
 * Date: 2018/11/19
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALFinancial;

class FinancialModel extends BaseModel {

    public function __construct(){
        parent::__construct();
    }

    /**
     * 获取财务数据列表
     * @param $startDate
     * @param $endDate
     * @param $page
     * @param $pageSize
     * @return array|bool
     */
    public function getFinancialList($startDate, $endDate, $page, $pageSize){
        $start = ($page - 1) * $pageSize;
        $dalFinancial = new DALFinancial($this->_appSetting);
        $financialList = $dalFinancial->getFinancialList($startDate, $endDate, $start, $pageSize);
        if(!empty($financialList)){
            foreach($financialList as $key => $val){
                $financialList[$key]['account_flow'] = $this->ncPriceFen2Yuan($val['account_flow']);
                $financialList[$key]['subscribe_money_total'] = $this->ncPriceFen2Yuan($val['subscribe_money_total']);
                $financialList[$key]['refund_money'] = $this->ncPriceFen2Yuan($val['refund_money']);
                $financialList[$key]['original_service_fee'] = $this->ncPriceFen2Yuan($val['original_service_fee']);
                $financialList[$key]['discount_fee'] = $this->ncPriceFen2Yuan($val['discount_fee']);
                $financialList[$key]['provider_fee'] = $this->ncPriceFen2Yuan($val['provider_fee']);
                $financialList[$key]['profit'] = $this->ncPriceFen2Yuan($val['profit']);
            }
        }
        return $financialList;
    }

}