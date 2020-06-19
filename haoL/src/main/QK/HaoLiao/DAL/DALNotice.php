<?php
/**
 * 资讯sql处理
 * User: zwh
 * Date: 2019/03/22
 * Time: 10:57
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALNotice extends BaseDAL {
    private $_table = 'hl_wechat_notice';
    /**
     * 创建通知
     * @param $params
     */
    public function createNotice($params) {
        $this->insertData($params, $this->_table);
    }



    /**
     * 修改通知
     * @param $newsId
     * @param $data
     * @return int
     */
    public function updateNotice($Id, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `id`=$Id";
        return $this->getDB($sql)->executeNoResult($sql);
    }


    /*
     * 通知详情
     */

    public function findNotice($nid){
        $sql = "SELECT * FROM `$this->_table` WHERE `id` = $nid";
        return $this->getDB($sql)->executeRow($sql);
    }


    /**
     * 获取通知列表
     * @param $condition
     * @param $pageSize
     * @param $orderBy
     * @return array|bool|mixed
     */
    public function getNoticeList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array()) {
        $fieldStr = " * ";
        if (!empty($fields)) {
            $fieldStr = implode(',', $fields);
        }
        $sql = "SELECT $fieldStr FROM `$this->_table` WHERE  1 = 1";

        if (!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                   
                    if($key=='ctime'){
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

        if (!empty($orderBy)) {
            $ordersArr = array();
            foreach($orderBy as $orderKey => $orderVal) {
                if(!is_string($orderKey)){
                    $ordersArr[] = " $orderVal";
                }else{
                    $ordersArr[] = "`$orderKey` $orderVal";
                }
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
     * 获取总量
     * @param $where
     * @return mixed
     */
    public function getNoticeTotal($condition) {
        $sql = "SELECT count(`id`) AS total FROM `$this->_table` WHERE 1=1";
        if (!empty($condition)) {
            foreach($condition as $key => $val) {
                if (is_array($val)) {
                    if($key=='ctime'){
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


}
