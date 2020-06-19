<?php
/**
 * 订单表
 * User: WangHui
 * Date: 2018/10/12
 * Time: 下午2:33
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALOrder extends BaseDAL {
    private $_table = 'hl_order';

    /**
     * 创建一个新订单
     * @param $orderData
     * @return bool
     */
    public function unifiedOrder($orderData){
        $res = $this->insertData($orderData, $this->_table);
        if($res){
            return $this->getInsertId();
        }
        return false;
    }

    /**
     * 修改订单信息
     * @param $orderNum
     * @param $data
     * @return int
     */
    public function updateOrder($orderNum, $data){
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET $updateString WHERE `order_num` = $orderNum";
        return $this->getDB($sql)->executeNoResult($sql);
    }


    /**
     * 获取订单总数（后台）
     * @param $where
     * @return mixed
     */
    public function getOrderTotal($where){
        $fields = 'COUNT(`order_id`) AS total';

        $sql = "SELECT " . $fields . ' FROM ' . $this->_table . ' WHERE ' . $where;

        $total = $this->getDB($sql)->executeRow($sql);
        return $total['total'];
    }

    /**
     * 获取订单列表（后台）
     * @param $where
     * @param $start
     * @param $size
     * @return array|bool
     */
    public function getOrderList($where, $start, $size){
        $fields = 'order_id, order_num, channel_order_num, order_amount, discount_amount, pay_amount, wechat_id, order_type, payment_method, trade_type, payment_channel, user_id, expert_id, dist_id, order_param, order_status, buy_time,platform,channel';
        $orderBy = "buy_time DESC";

        $sql = 'SELECT ' . $fields . ' FROM ' . $this->_table . ' WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . $start . ', ' . $size;

        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 新增获取订单总数（后台）
     * @param $where
     * @return mixed
     */
    public function getOrderTotalNew($where){
        $fields = 'COUNT(a.order_id) AS total';

        $sql = "SELECT " . $fields . ' FROM ' . $this->_table . ' as a left join hl_user as b on a.user_id=b.user_id left join hl_user_expert as c on a.expert_id=c.expert_id WHERE ' . $where;

        $total = $this->getDB($sql)->executeRow($sql);
        return $total['total'];
    }
    /*
     * 新增后台订单列表
     */
    public function getOrderListNew($where, $start, $size){
        $fields = 'a.order_id, a.order_num, a.channel_order_num, a.order_amount, a.discount_amount, a.pay_amount,a.wechat_id, a.order_type, a.payment_method, a.trade_type, a.payment_channel, a.user_id, a.expert_id, a.dist_id, a.order_param, a.order_status, a.buy_time,a.platform,a.channel';
        $orderBy = "a.buy_time DESC";

        $sql = 'SELECT ' . $fields . ' FROM ' . $this->_table . ' as a left join hl_user as b on a.user_id=b.user_id left join hl_user_expert as c on a.expert_id=c.expert_id WHERE ' . $where . ' ORDER BY ' . $orderBy . ' LIMIT ' . $start . ', ' . $size;

        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取用户成功订单列表
     * @param $userId
     * @param $orderType
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getUserOrderList($userId, $orderType, $page, $size){
        $start = ($page - 1) * $size;
        $sql = "select `order_id`, `order_num` from `$this->_table` where `user_id` = '$userId' and  (`order_status` = 1 OR `order_status` = 3) and `order_type`='$orderType' order by `buy_time` desc limit $start,$size";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取专家成功订单列表
     * @param $expertId
     * @param $orderType
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getExpertOrderList($expertId, $orderType, $page, $size){
        $start = ($page - 1) * $size;
        $sql = "select `hl_order`.`order_id`, `hl_order`.`order_num` from `$this->_table` ";
        //不中退款订单需联表查询
        if($orderType == 2){
            $sql .= "left join `hl_order_resource_extra` on `hl_order`.`order_id` = `hl_order_resource_extra`.`order_id` and `hl_order_resource_extra`.`resource_type`=2 ";
        }
        $sql .= " where `expert_id` = '$expertId' and (`order_status` = 1 OR `order_status` = 3)";

        if($orderType != 0){
            $sql .= " and `order_type`='$orderType' ";
        }
        $sql .= " ORDER BY `hl_order`.`buy_time` DESC limit $start,$size";
        return $this->getDB($sql)->executeRows($sql);
    }


    /**
     * 获取料成功订单列表
     * @param $resourceId
     * @param $start
     * @param $pagesize
     * @return array|bool
     */
    public function getOrderNumsByResourceId($resourceId, $start, $pagesize){
        $sql = "SELECT `order_id`, `order_num` FROM `$this->_table` WHERE `order_param` = '$resourceId' AND `order_type`= 2  AND  (`order_status` = 1 OR `order_status` = 3) ORDER BY `notify_time` DESC LIMIT $start, $pagesize";
//        $sql .= "left join `hl_order_resource_extra` on `hl_order`.`order_id` = `hl_order_resource_extra`.`order_id` and `hl_order_resource_extra`.`resource_type`=2 ";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取料成功订单总条数
     * @param $resourceId
     * @return array|bool
     */
    public function getSoldNumByResourceId($resourceId){
        $sql = "SELECT COUNT(`order_id`) FROM `$this->_table` WHERE `order_param` = '$resourceId' AND `order_type`= 2  AND  (`order_status` = 1 OR `order_status` = 3)";
        return (int)$this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取订单售出总金额
     * @param $resourceId
     * @return int
     */
    public function getSoldMoneyByResourceId($resourceId){
        $sql = "SELECT SUM(`order_amount`) FROM `$this->_table` WHERE `order_param` = '$resourceId' AND `order_type`= 2  AND `order_status` = 1";
        return (int)$this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取订单主要信息
     * @param $orderNum
     * @return mixed
     */
    public function getOrderInfo($orderNum){
        $sql = "SELECT `order_id`, `order_num`, `channel_order_num`, `channel_user_id`, `channel_product_id`, `order_amount` , `discount_amount` , `pay_amount` , `order_type` , `payment_method` , `trade_type` , `payment_channel`, `appid` , `wechat_id` , `user_id`, `order_source` , `expert_id`, `dist_id`, `order_param` , `order_status` , `buy_time` , `notify_time`, `refund_time`, `group_id`, `coupon_id` FROM `$this->_table` WHERE `order_num` = '$orderNum' ";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 检查用户是否购买过此料
     * @param $userId
     * @param $resourceId
     * @return mixed
     */
    public function getResourceOrderByUserId($userId, $resourceId){
        $sql = "SELECT `order_id`, `order_num`, `channel_order_num`, `channel_user_id`, `order_amount` , `discount_amount` , `pay_amount` , `order_type` , `payment_method` , `trade_type` , `payment_channel` , `user_id` , `expert_id` , `dist_id`, `order_param` , `order_status` , `buy_time` , `notify_time`, `refund_time`  FROM `$this->_table` WHERE `user_id` = $userId AND `order_param` = $resourceId AND ( `order_status` = 1 OR `order_status` = 3 ) LIMIT 1";
        return $this->getDB($sql)->executeRow($sql);
    }


    /**
     * 获取购买过某个料的订单（退款定时程序,分销商红单分成用）
     * @param $resourceId
     * @return array|bool
     */
    public function getResourceOrder($resourceId) {
        $sql = "SELECT `order_id`, `order_num`,`user_id`,`expert_id`, `dist_id`, `channel_order_num`, `pay_amount` ,`payment_method`, `payment_channel` FROM `$this->_table` WHERE `order_type` = 2 AND `order_param` = $resourceId AND `pay_amount` > 0 AND `order_status` = 1";
        return $this->getDB($sql)->executeRows($sql);
    }
	
	/*
     * 检查是否已购买 文章/视频/情报
     */
    public function getOrderByUserId($userId, $resourceId,$orderType){
        $sql = "SELECT `order_id`, `order_num`, `channel_order_num`, `channel_user_id`, `order_amount` , `discount_amount` , `pay_amount` , `order_type` , `payment_method` , `trade_type` , `payment_channel` , `user_id` , `expert_id` , `dist_id`, `order_param` , `order_status` , `buy_time` , `notify_time`, `refund_time`  FROM `$this->_table` WHERE `user_id` = $userId AND `order_param` = $resourceId AND `order_type`=$orderType AND  `order_status` = 1 LIMIT 1";

        return $this->getDB($sql)->executeRow($sql);
    }
	
	
	
    /**
     * 获取购买过某个料的订单
     * @param $resourceId
     * @return array|bool
     */
    public function getResourceGroupOrder($resourceId, $groupId = 0) {
        $sql = "SELECT `order_id`, `order_num`,`user_id`,`expert_id`, `dist_id`, `channel_order_num`, `pay_amount` ,`payment_method`, `payment_channel` FROM `$this->_table` WHERE `order_type` = 2 AND `order_param` = $resourceId AND `pay_amount` > 0 AND `order_status` = 1";
        if ($groupId != 0) {
            $sql .= ' AND group_id = ' . $groupId;
        } else {
            $sql .= ' AND group_id <> 0';
        }
        return $this->getDB($sql)->executeRows($sql);
    }
    /**
     * 获取时间区间内订单集合
     * @param $startTime
     * @param $endTime
     * @param $orderType
     * @return array|bool
     */
    public function getOrderListByTime($startTime, $endTime, $orderType = 0){
        $sql = "SELECT `order_id`, `order_num`, `channel_order_num`, `channel_user_id`, `order_amount` , `discount_amount` , `pay_amount` , `order_type` , `payment_method` , `trade_type` , `payment_channel` , `user_id` , `expert_id` , `dist_id`, `order_param` , `order_status` , `buy_time` , `notify_time`, `refund_time` FROM `$this->_table` WHERE 1";
        if($orderType){
            $sql .= " AND `order_type`=$orderType";
        }
        $sql .= " AND `order_status`=1 AND `notify_time`>=$startTime AND `notify_time`<$endTime";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取统计数据（后台）
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public function getStatData($startTime, $endTime) {
        $sql = "select count(*) as `order_count`,COUNT(DISTINCT `user_id` ) AS `buyer_count`,SUM(`pay_amount` ) AS `amount` from `$this->_table` WHERE `notify_time`>=$startTime AND `notify_time`<=$endTime AND `order_status` IN (1,3,4)";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取订阅统计数据（后台）
     * @param $startTime
     * @param $endTime
     * @return mixed
     */
    public function getSubscribeStatData($startTime, $endTime) {
        $sql = "select count(*) as `count`,COUNT(DISTINCT `user_id` ) AS `buyer_count`,SUM(`pay_amount` ) AS `amount` from `$this->_table` WHERE `notify_time`>=$startTime AND `notify_time`<=$endTime AND `order_type`=1 AND `order_status`=1";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取料的所有成功订单金额
     * @param $resourceId
     * @return mixed
     */
    public function getResourceAmount($resourceId) {
        $sql = "select SUM(`pay_amount` ) from `$this->_table` WHERE `order_param` = $resourceId AND `order_type`=2 AND `order_status` = 1";
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 获取用户的所有成功订单金额
     * @param $resourceId
     * @return mixed
     */
    public function getUserAmount($user_id) {
        $sql = "select SUM(`pay_amount` ) from `$this->_table` WHERE `user_id` = $user_id AND `order_status` = 1 AND order_type <= 6";
        return $this->getDB($sql)->executeValue($sql) ?: 0;
    }

    public function getOrderByCondition($condition = array(), $fields = array()) {
      return $this->get($this->_table, $condition, $fields);
    }

    //获取订单列表
    public function getOrderListV2($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()){
      return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }
}
