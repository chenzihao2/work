<?php
/**
 * 财务数据处理
 * User: YangChao
 * Date: 2018/11/19
 */

namespace QK\HaoLiao\DAL;

use QK\WSF\Settings\AppSetting;

class DALFinancial extends BaseDAL {

    protected $_table = "hl_stat_financial";

    public function __construct(AppSetting $appSetting){
        parent::__construct($appSetting);
    }

    /**
     * 获取财务数据
     * @param $startDate
     * @param $endDate
     * @param $start
     * @param $size
     * @return array|bool
     */
    public function getFinancialList($startDate, $endDate, $start, $size){
        $sql = "SELECT `id`, `date`, `account_flow`, `order_total`, `buy_user_total`, `subscribe_money_total`, `subscribe_total`, `refund_money`, `refund_total`, `refund_user_total`, `original_service_fee`, `discount_fee`, `provider_fee`, `profit`, `create_time` FROM `$this->_table` WHERE 1";
        $sql .= " AND `date` >= '$startDate'";
        $sql .= " AND `date` <= '$endDate'";
        $sql .= " ORDER BY `date` DESC LIMIT $start, $size";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 新统计数据
     * @param $params
     */
    public function newData($params) {
        $this->insertData($params,$this->_table);
    }

}