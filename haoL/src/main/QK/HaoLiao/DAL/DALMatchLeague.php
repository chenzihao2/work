<?php
/**
 * 比赛联赛信息数据处理类
 * User: WangHui
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALMatchLeague extends BaseDAL {
    //private $_table = "hl_match_league";
    private $_table = "hl_league";

    /**
     * 新建一个联赛
     * @param $params
     */
    public function newMatchLeague($params) {
        $this->insertData($params, $this->_table);
    }

    /**
     * 获取联赛列表数据
     * @param $matchType
     * @param null $leagueStatus
     * @return array|bool
     */
    public function getLeagueList($matchType, $leagueStatus = null) {
        //$sql = "SELECT `league_id`, `match_type`,`initial`, `league_name`, `league_status`, 'crawler_name' FROM `$this->_table` WHERE `match_type` = $matchType";
        $sql = "SELECT `league_num` as league_id, `type` as match_type,`initial`, `name` as league_name, `short_name` as crawler_name  FROM `$this->_table` WHERE `type` = $matchType";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取联赛列表数据(后台)
     * @param $query
     * @param $page
     * @param $size
     * @return array|bool
     */
    public function getLeagueListByQuery($query, $page, $size) {
        $start = ($page - 1) * $size;
        $sql = "SELECT `league_num` as league_id, `type` as match_type,`initial`, `short_name` as league_name, `short_name` as crawler_name  FROM `$this->_table` WHERE 1";
        if (!empty($query)) {
            foreach ($query as $key => $val) {
                if ($key == "name" && !empty($val)) {
                    $sql .= " AND `name` LIKE '%$val%'";
                } elseif (!empty($val)) {
                    $sql .= " AND $key = $val";
                }
            }
        }
        $sql .= " limit {$start},{$size}";
        return $this->getDB($sql)->executeRows($sql);
    }

    /**
     * 获取联赛列表数据数(后台)
     * @param $query
     * @return int
     */
    public function getLeagueCountByQuery($query): int {
        $sql = "SELECT COUNT(*) FROM `$this->_table` WHERE 1 ";

        if (!empty($query)) {
            foreach ($query as $key => $val) {
                if ($key == "name" && !empty($val)) {
                    $sql .= " AND `name` LIKE '%$val%'";
                } elseif (!empty($val)) {
                    $sql .= " AND $key = $val";
                }
            }
        }
        return $this->getDB($sql)->executeValue($sql);
    }

    /**
     * 更新联赛信息
     * @param $id
     * @param $params
     * @return int
     */
    public function updateMatchLeague($id, $params) {
        $updateString = StringHandler::newInstance()->getDBUpdateString($params);
        $sql = 'UPDATE `' . $this->_table . '` SET ' . $updateString . 'WHERE `league_id`=' . $id;
        return $this->getDB($sql)->executeNoResult($sql);
    }

    /**
     * 获取联赛详情数据
     * @param $leagueId
     * @return array|bool
     */
    public function getLeagueInfoById($leagueId) {
        $sql = "SELECT `league_id`, `match_type`, `league_name`, `league_status` FROM `$this->_table` WHERE `league_id` = $leagueId";
        return $this->getDB($sql)->executeRow($sql);
    }

    /**
     * 通过联赛名称和足球、篮球类型获取联赛详情数据(数据抓取使用，其他请单写)
     * @param $name
     * @param $type
     * @return mixed
     */
    public function getLeagueInfo($name, $type) {
        $sql = "SELECT `league_id`, `match_type`, `league_name`, `league_status` FROM `$this->_table` WHERE `crawler_name` = '$name' AND `match_type`=$type";
        return $this->getDB($sql)->executeRow($sql);
    }

}
