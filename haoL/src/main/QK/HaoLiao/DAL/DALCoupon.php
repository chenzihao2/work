<?php
/**
 * 优惠券sql处理
 * User: zyj
 * Date: 2019/11/16
 * Time: 10:57
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALCoupon extends BaseDAL {
    private $_table = 'hl_coupon_backstage';
    private $_coupon_package = 'hl_coupon_package';
    private $_coupon_user = 'hl_coupon_user';
    /**
     * 后台创建优惠券
     * @param $params
     */
    public function createCoupon($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 统计用户优惠券得数量
     * @param $id
     * @param $data
     * @return int
     */
    public function getCouponUserCount($condition) {
        $sql = "SELECT count(`id`) AS total FROM `$this->_coupon_user` WHERE $condition";
        return $this->getDB($sql)->executeValue($sql);
    }
    /**
     * 修改优惠券内容
     * @param $newsId
     * @param $data
     * @return int
     */
    public function updateCoupon($id, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `id`=$id";
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 条件修改置顶
     */
    public function updateSort($condition, $sort) {
        $sortData = $sort * 10000000000;
        if(!$sort) {
            $sortData = $sort;
        }
        $up_time = time();
        $sql = "UPDATE `$this->_table` SET `modify_time`=$up_time,`sort`= `create_time`+$sortData WHERE 1=1";
        if(!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    $sql .= " AND `$key` $val[0] '$val[1]'";
                } else {
                    $sql .= " AND `$key` = '$val'";
                }
            }
        }
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取后台优惠券详细信息
     * @param $newsId
     * @return mixed
     */
    public function getNewsInfo($id) {
        $sql = "SELECT * FROM `$this->_table` WHERE `id` = $id";
        return $this->getDB($sql)->executeRow($sql);
    }


    /**
     * 修改礼包内容
     * @param $id
     * @param $data
     * @return int
     */
    public function updatePackageV2($id, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_coupon_package` SET " . $updateString . " WHERE `id`=$id";
        return $this->getDB($sql)->executeNoResult($sql);
    }
    /**
     * 获取后台礼包详细信息
     * @param $newsId
     * @return mixed
     */
    public function getPackageInfo($id) {
        $sql = "SELECT * FROM `$this->_coupon_package` WHERE `id` = $id";
        return $this->getDB($sql)->executeRow($sql);
    }


    /**
     * 获取后台优惠券列表
     * @param $condition
     * @param $pageSize
     * @param $orderBy
     * @return array|bool|mixed
     */
    public function getCouponList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array()) {
        $fieldStr = " * ";
        if (!empty($fields)) {
            $fieldStr = implode(',', $fields);
        }
        $sql = "SELECT $fieldStr FROM `$this->_table` WHERE  1 = 1";

        if (!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    $sql .= " AND `$key` $val[0] '$val[1]'";
                } else {
                    $sql .= " AND `$key` = '$val'";
                }
            }
        }

        if (!empty($orderBy)) {
            $ordersArr = array();
            foreach($orderBy as $orderKey => $orderVal) {
                $ordersArr[] = "`$orderKey` $orderVal";
            }
            $orderStr = implode(',', $ordersArr);
            $sql .= " ORDER BY $orderStr";
        }

        if ($pageSize) {
            $sql .= " limit $pageSize";
        }
        if (!empty($page)) {
            $offset = ($page - 1) * $pageSize;
            $sql .= " offset $offset";
        }
		
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取后台优惠券总量
     * @param $where
     * @return mixed
     */
    public function getCouponTotal($condition) {
        $sql = "SELECT count(`id`) AS total FROM `$this->_table` WHERE 1=1";
        if (!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    if($key=='create_time'){
                        foreach($val as $v){
                            $sql .= " AND `$key` $v[0] '$v[1]'";
                        }

                    }else{
                        $sql .= " AND `$key` $val[0] '$val[1]'";
                    }

                } else {
                    $sql .= " AND `$key` = '$val'";
                }
            }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    public function existsUserCoupon($condition) {
        return $this->get($this->_coupon_user, $condition);
    }

    public function getPackageByCondition($condition) {
        return $this->get($this->_coupon_package, $condition);
    }

    public function getCouponByCondition($condition) {
        return $this->get($this->_table, $condition);
    }

    public function getPackageList($condition) {
        return $this->select($this->_coupon_package, $condition, [], 0, false, ['user_type' => 'desc', 'start_time' => 'asc']);
    }

    public function getCoupons($condition) {
        return $this->select($this->_table, $condition, [], 0, false, []);
    }

    public function getUserCoupons($condition) {
        return $this->select($this->_coupon_user, $condition, [], 0, false, ['end_time' => 'asc', 'type' => 'asc', 'discount' => 'desc']);
    }

    public function addUserCoupon($params) {
        return $this->insertData($params, $this->_coupon_user);
    }

    public function updateUserCoupon($condition, $data) {
        return $this->updateByCondition($condition, $data, $this->_coupon_user);
    }

    public function updatePackage($condition, $data) {
        return $this->updateByCondition($condition, $data, $this->_coupon_package);
    }

    public function updateCoupons($condition, $data) {
        return $this->updateByCondition($condition, $data, $this->_table);
    }

    public function amountAddById($id) {
        $sql = "UPDATE $this->_table SET amount = amount + 1 WHERE id = $id";
        return $this->getDB($sql)->executeValue($sql);
    }

}
