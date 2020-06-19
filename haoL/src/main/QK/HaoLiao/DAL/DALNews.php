<?php
/**
 * 资讯sql处理
 * User: zwh
 * Date: 2019/03/22
 * Time: 10:57
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALNews extends BaseDAL {
    private $_table = 'hl_news';
    private $_category = 'hl_category';
    private $_content = 'hl_news_content';
    /**
     * 创建资讯
     * @param $params
     */
    public function createNews($params) {
        $this->insertData($params, $this->_table);
    }


    /**
     * 创建资讯内容
     * @param $params
     */
    public function createContent($params) {
        $this->insertData($params, $this->_content);
    }
    /**
     * 修改内容
     * @param $newsId
     * @param $data
     * @return int
     */
    public function updateContent($newsId, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_content` SET " . $updateString . " WHERE `nid`=$newsId";
        return $this->getDB($sql)->executeNoResult($sql);
    }


    /*
     * 查询资讯内容
     */

    public function findContent($nid){
        $sql = "SELECT * FROM `$this->_content` WHERE `nid` = $nid";
        return $this->getDB($sql)->executeRow($sql);
    }


   /**
     * 根据名称获取资讯信息
     * @param $newsId
     * @return mixed
     */
    public function getNameNewsInfo($name) {
        $sql = "SELECT nid,title FROM `$this->_table` WHERE $name";
        return $this->getDB($sql)->executeRow($sql);
    }





    /**
     * 修改资讯内容
     * @param $newsId
     * @param $data
     * @return int
     */
    public function updateNews($newsId, $data) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($data);
        $sql = "UPDATE `$this->_table` SET " . $updateString . " WHERE `nid`=$newsId";
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
        //$sql = "UPDATE `$this->_table` SET `modify_time`=$up_time,`sort`= $sortData WHERE 1=1";
        if(!empty($condition)) {
            foreach($condition as $key => $val) {
                if($key=='sort_end'){
                    $key='sort';
                }
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
     * 获取资讯内容
     * @param $newsId
     * @return mixed
     */
    public function getNewsInfo($newsId) {
        $sql = "SELECT * FROM `$this->_table` WHERE `nid` = $newsId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取资讯分类内容
     * @param $newsId
     * @return mixed
     */
    public function getNewsCateInfo($cid) {
        $sql = "SELECT * FROM `$this->_category` WHERE `id` = $cid";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 获取资讯列表
     * @param $condition
     * @param $pageSize
     * @param $orderBy
     * @return array|bool|mixed
     */
    public function getNewsList($condition = array(), $fields = array(), $page = 0, $pageSize = 20, $orderBy = array()) {
        $fieldStr = " * ,FROM_UNIXTIME(create_time,'%Y-%m-%d') as day";
        if (!empty($fields)) {
            $fieldStr = implode(',', $fields);
        }
        $sql = "SELECT $fieldStr FROM `$this->_table` WHERE  1 = 1";

        //$condition['article_source'] = 0;
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
    public function getNewsTotal($condition) {
        $sql = "SELECT count(`nid`) AS total FROM `$this->_table` WHERE 1=1";
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

    public function newsListV2($condition = array(), $fields = array(), $offset = 0, $limit = 0, $orderBy = array()) {
        return $this->select($this->_table, $condition, $fields, $offset, $limit, $orderBy);
    }

    public function getNewsByUrl($url)  {
        return $this->get($this->_table, ['url' => $url]);
    }

}
