<?php
/**
 * 订单管理
 * User: YangChao
 * Date: 2018/11/5
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\OrderModel;

class OrderController extends ConsoleController {

    /**
     * 订单管理列表
     */
    public function orderList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
//        $where = ['order_type' => 1, 'order_param' => 1, 'user_id' => 1, 'order_status' => 0, 'payment_method' => 1, 'buy_time_start' => '1540453346', 'buy_time_end' => '1540453446'];
        $where = json_decode($param['query'], true);
        $condition = '1 = 1';
        if (!empty($where)) {
            foreach ($where as $key => $val) {
                if($key== "buy_time_start"){
                    $condition .= " AND a.buy_time >= $val";
                }elseif ($key=="buy_time_end"){
                    $condition .= " AND a.buy_time < " . (strtotime(date('Y-m-d', $val)) + 24 * 3600);
                }elseif($val !== '') {
                    if($key=='nick_name'){
                        $condition .= " AND b.nick_name like '%$val%'";
                    }else if($key=='expert_name'){
                        $condition .= " AND c.expert_name like '%$val%'";
                    }else{
                        $condition .= " AND a.$key = $val";
                    }

                }
            }
        }
        if (empty($where['order_type'])) {
            $condition .= ' AND a.order_type <= 6';
        }
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $orderModel = new OrderModel();
        $orderList = $orderModel->getOrderList($condition, $page, $pagesize);
        $this->responseJson($orderList);
    }

    /**
     * 充值订单
     */
    public function vcBuyOrderList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
//        $where = ['order_type' => 1, 'order_param' => 1, 'user_id' => 1, 'order_status' => 0, 'payment_method' => 1, 'buy_time_start' => '1540453346', 'buy_time_end' => '1540453446'];
        $where = json_decode($param['query'], true);
        $condition = '1 = 1';
        if (!empty($where)) {
            foreach ($where as $key => $val) {
                if($key== "buy_time_start"){
                    $condition .= " AND `buy_time` >= $val";
                }elseif ($key=="buy_time_end"){
                    $condition .= " AND `buy_time` < " . (strtotime(date('Y-m-d', $val)) + 24 * 3600);
                }elseif($val !== '') {
                    $condition .= " AND $key = $val";
                }
            }
        }
        if (empty($where['order_type'])) {
            $condition .= ' AND order_type = 100';
        }
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $orderModel = new OrderModel();
        $orderList = $orderModel->getVcBuyOrderList($condition, $page, $pagesize);
        $this->responseJson($orderList);
    }
}
