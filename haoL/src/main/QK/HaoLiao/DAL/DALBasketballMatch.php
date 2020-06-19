<?php

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;

class DALBasketballMatch extends BaseDAL {
    private $match_table = "hl_basketball_match";
    private $match_detail_table = "hl_basketball_match_detail";
    private $league_table = "hl_league";
    private $team_table = "hl_match_team";
    private $player_table = "hl_basketball_player";
    private $attention_table = "hl_basketball_attention";
    private $match_stat_table = "hl_basketball_team_stat";
    private $match_player_stat_table = "hl_basketball_player_stat";
    private $match_basketball_lottery = "hl_basketball_lottery";
    private $relation_table = 'hl_resource_schedule';

	
    public function addMatch($params) {
        return $this->insertData($params, $this->match_table);
    }

    public function addLeague($params) {
        return $this->insertData($params, $this->league_table);
    }

    public function addTeam($params) {
        return $this->insertData($params, $this->team_table);
    }

    public function addMatchDetail($params) {
        return $this->insertData($params, $this->match_detail_table);
    }
    
    public function addMatchStat($params) {
        return $this->insertData($params, $this->match_stat_table);
    }
    
    public function addMatchPlayerStat($params) {
        return $this->insertData($params, $this->match_player_stat_table);
    }

    public function addPlayer($params) {
        return $this->insertData($params, $this->player_table);
    }

    public function addAttention($params) {
        return $this->insertData($params, $this->attention_table);
    } 

    public function updateAttention($attention, $id) {
        $attention += 1;
        $attention > 1 && $attention = 0;
        return $this->updateByCondition(['id' => $id], ['attention' => $attention], $this->attention_table);
    }

    public function existsMatch($match_num) {
	      return $this->get($this->match_table, ['match_num' => $match_num], ['id']);
    }

    public function existsMatchDetail($match_num) {
        return $this->get($this->match_detail_table, ['match_num' => $match_num], []);
    }

    public function existsMatchStat($match_num) {
	return $this->get($this->match_stat_table, ['match_num' => $match_num], []);
    }

    public function existsMatchPlayerStat($match_num, $player_num) {
        return $this->get($this->match_player_stat_table, ['match_num' => $match_num, 'player_num' => $player_num], []);
    }

    public function existsLeague($league_num) {
        return $this->get($this->league_table, ['league_num' => $league_num, 'type' => 2], ['id']);
    }

    public function existsTeam($team_num) {
        return $this->get($this->team_table, ['team_num' => $team_num, 'type' => 2], ['id']);
    }

    public function existsPlayer($player_num) {
	return $this->get($this->player_table, ['player_num' => $player_num], ['id']);
    }

    public function existsAttention($user_id, $match_num) {
        return $this->get($this->attention_table, ['user_id' => $user_id, 'match_num' => $match_num], ['id', 'attention']);
    }

    public function getList($conditions = [], $fields = [], $page = 1, $pagesize = 50, $orderBy = []) {
        $offset = ($page - 1) * $pagesize;
        $result = $this->select($this->match_table, $conditions, $fields, $offset, $pagesize, $orderBy);
        return $result;
    }

    public function getTotal($conditions) {
	$total = $this->counts($this->match_table, $conditions);
        return $total[0]['count'];
    }

    public function getMatchNumByDate($date1, $date2, $condition = []) {
      if ($date1 && $date2) {
	$val1 = ' between \'' . $date1 . '\' ';
	$val2 = ' and \'' . $date2 . '\' ';
        $conditions['date'] = [$val1, $val2];
      }
      $fields = ['match_num'];
      return array_column($this->select($this->match_table, $conditions, $fields, 0, false, []), 'match_num');
    }

    public function getLeagueNums($date1, $date2, $condition = []) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $condition['date'] = [$val1, $val2];
        if (isset($condition['match_num']) && $condition['match_num']) {
            $condition['match_num'] = ['in', '(' . $condition['match_num'] . ')'];
        }
        return array_column($this->select($this->match_table, $condition, ['distinct league_num'], 0, false, []), 'league_num');
    }

    public function getLeagueCountMatch($date1, $date2, $condition = '') {
        $and = '';
        if ($condition) {
            foreach($condition as $k => $v) {
                if ($k == 'match_num') {
                    $and .= " and match_num in ($v)";
                } elseif(is_array($v)) {
                    $and .= " and $k $v[0] $v[1]";
                } else {
                    $and .= " and $k = $v";
                }
            }
        }
        $sql = "select count(*) as match_count, league_num from " . $this->match_table . " where date between '" . $date1 . "' and '" . $date2 . "' $and group by league_num";
        $result =  $this->getDB($sql)->executeRows($sql);
        foreach ($result as $v) {
          $results[$v['league_num']] = $v['match_count'] ?: 0;
        }
        return $results;
    }
    
    public function getHotMatch($date1, $date2) {
      $val1 = ' between \'' . $date1 . '\' ';
      $val2 = ' and \'' . $date2 . '\' ';
      $conditions = ['date' => [$val1, $val2], 'status' => [' not in ' , '(9, 11, 14)']];
      $fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'ascore', 'bscore', 'status', 'is_hot'];
      $conditions['is_hot'] = 1;
      $order_by = ['date' => 'asc'];
      return $this->select($this->match_table, $conditions,$fields, 0, false, $order_by);
    }

    public function getAttentMatchList($date1, $date2, $user_id = 0) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $condition['attention'] = 1;
        $condition['match_date'] = [$val1, $val2];
        $condition['user_id'] = $user_id;
        return array_column($this->select($this->attention_table, $condition, ['match_num'], 0, false, ['match_date' => 'asc']), 'match_num');
    }

    public function getResourceNum($match_num) {
        $count = $this->counts($this->relation_table, ['schedule_id' => $match_num, 'type' => 2]);
        return $count[0]['count'];
    }

    public function getLeagueList($conditions = [], $fields = [], $page = 1, $pagesize = 0, $orderBy = []) {
        $offset = ($page - 1) * $pagesize;
        $result = $this->select($this->league_table, $conditions, $fields, $offset, $pagesize, $orderBy);
        return $result;
    }

    public function getTeamList($conditions = [], $fields = [], $page = 1, $pagesize = 0, $orderBy = []) {
        $offset = ($page - 1) * $pagesize;
        $result = $this->select($this->team_table, $conditions, $fields, $offset, $pagesize, $orderBy);
        return $result;
    }

    public function getMatchDetail($match_num) {
        $result = $this->select($this->match_detail_table, ['match_num' => $match_num], [], 0, false);
        return $result[0];
    }

    public function getMatchStat($match_num) {
	    return $this->get($this->match_stat_table, ['match_num' => $match_num]);
    }
    
    public function getMatchPlayerStat($match_num) {
	    return $this->select($this->match_player_stat_table, ['match_num' => $match_num], [], 0, false);
    }

    public function getMatchInfo($match_num) {
        $result = $this->select($this->match_table, ['match_num' => $match_num], [], 0, false);
        return $result[0];
    }

    public function getLeagueInfo($league_num) {
        return $this->get($this->league_table, ['league_num' => $league_num, 'type' => 2]);
    }

    public function getTeamInfo($team_num) {
        return $this->get($this->team_table, ['team_num' => $team_num, 'type' => 2]);
    }

    public function getPlayerInfo($player_num) {
        $fields = ['player_num', 'team_num', 'name', 'shit_num', 'pos', 'photo', 'ctime', 'utime'];
        return $this->get($this->player_table, ['player_num' => $player_num], $fields);
    }

    public function getMatchByCondition($condition) {
        return $this->select($this->match_table, $condition, [], 0, false, []);
    }

    public function getMatchAttentUser($match_num) {
        $condition = ['match_num' => $match_num, 'attention' => 1];
        return array_column($this->select($this->attention_table, $condition, ['user_id'], 0, false, ['match_date' => 'desc']), 'user_id');
    }

    public function updateMatch($data, $match_num) {
	      return $this->updateByCondition(['match_num' => $match_num], $data, $this->match_table);
    }

    public function updateMatchByCondition($data, $condition = []) {
        return $this->updateByCondition($condition, $data, $this->match_table);
    }

    public function updateTeam($data, $team_num) {
        return $this->updateByCondition(['team_num' => $team_num, 'type' => 2], $data, $this->team_table);
    }

    public function updateMatchDetail($data, $match_num) {
        return $this->updateByCondition(['match_num' => $match_num], $data, $this->match_detail_table);
    }

    public function updateLeague($data, $league_num) {
        return $this->updateByCondition(['league_num' => $league_num, 'type' => 2], $data, $this->league_table);
    }

    public function updateMatchStat($data, $match_num) {
	      return $this->updateByCondition(['match_num' => $match_num], $data, $this->match_stat_table);
    }

    public function updateMatchPlayerStat($data, $match_num, $player_num) {
	      return $this->updateByCondition(['match_num' => $match_num, 'player_num' => $player_num], $data, $this->match_player_stat_table);
    }

    public function getPlayerStatMatchNums() {
        return array_column($this->select($this->match_player_stat_table, [], ['match_num'], 0, false, []), 'match_num');
    }

    public function existsLottery($condition) {
        return $this->get($this->match_basketball_lottery, $condition);
    }

    public function addLottery($params) {
        return $this->insertData($params, $this->match_basketball_lottery);
    }

    public function updateLottery($data, $id) {
        return $this->updateByCondition(['id' => $id], $data, $this->match_basketball_lottery);
    }


    public function getLotteryMatch($date1, $date2, $condition, $limit = 1) {
        $page = $condition['page'];
        $page_num = $condition['page_num'];
        $start = ($page - 1) * $page_num;
        $lottery_type = $condition['lottery_type'];
        $sql_count = "select count(distinct match_num) as count";
        $sql = "select distinct match_num, `date`";
        $sql .= " from " . $this->match_basketball_lottery . " where `date` between '$date1' and '$date2' and lottery_type = $lottery_type and is_first = 0";
        $sql_count .= " from " . $this->match_basketball_lottery . " where `date` between '$date1' and '$date2' and lottery_type = $lottery_type and is_first = 0";
        if ($condition['league_num']) {
            $sql .= ' and league_num = ' . $condition['league_num'];
            $sql_count .= ' and league_num = ' . $condition['league_num'];
        }
        if ($condition['key_words']) {
            $key = $condition['key_words'];
            $sql .= " and (league_name like '%$key%' or league_short_name like '%$key%' or host_team_name like '%$key%' or guest_team_name like '%$key%')";
            $sql_count .= " and (league_name like '%$key%' or league_short_name like '%$key%' or host_team_name like '%$key%' or guest_team_name like '%$key%')";
        }
        if ($lottery_type == 2) {
            $sql .= " and match_num not in (SELECT DISTINCT match_num FROM $this->match_basketball_lottery WHERE `date` BETWEEN '$date1' AND '$date2' AND  lottery_type = 1 and is_first = 0)";
            $sql_count .= " and match_num not in (SELECT DISTINCT match_num FROM $this->match_basketball_lottery WHERE `date` BETWEEN '$date1' AND '$date2' AND  lottery_type = 1 and is_first = 0)";
        }
        //$sql .= " order by `date` asc, `lottery_num` asc, `type` asc limit $start, $page_num";
        if ($limit) {
            $sql .= " order by `date` asc limit $start, $page_num";
        }
        $sql_count .= " order by `date` asc";
        $data = $this->getDB($sql)->executeRows($sql);
        $count = $this->getDB($sql_count)->executeValue($sql_count);
        return ['data' => $data, 'count' => $count];
    }

    public function getLotteryDetail($match_num, $lottery_type) {
        $sql = "select * from " . $this->match_basketball_lottery . " where match_num = " . $match_num;
        $sql .= " and is_first = 0 ";
        $sql .= " and lottery_type = " . $lottery_type;
        $sql .= " order by type asc";
        return $this->getDB($sql)->executeRows($sql);
    }

    public function getLotteryLeague($date1, $date2, $condition) {
        $lottery_type = $condition['lottery_type'] ?: 1;
        $sql = "SELECT l.league_num,l.league_short_name FROM $this->match_basketball_lottery l LEFT JOIN $this->league_table le ON l.league_num = le.league_num WHERE l.date BETWEEN '$date1' AND '$date2' and lottery_type = $lottery_type  GROUP BY l.league_num ORDER BY le.`initial` asc";
        $set_sql = "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'";
        $this->getDB($set_sql)->executeRows($set_sql);
        $data = $this->getDB($sql)->executeRows($sql);
        return $data;
    }

    public function getLotteryByCondition($condition) {
        return $this->select($this->match_basketball_lottery, $condition, [], 0, false, []);
    }

    public function findLotteryById($id) {
        return $this->get($this->match_basketball_lottery, ['id' => $id]);
    }

    /**
     * 获取指定赛程id的料内容关联赛信息
     * 
     * @param int $match_num 赛程ID
     * @param int $type      赛事类型：1足球,2篮球
     * @return array
     */
    public function getLastSchedule($match_num = 0, $type = 2) 
    {
        $match_num = intval($match_num);
        $type = intval($type);
        $query = "SELECT MAX(id) id,schedule_id,type,lottery_result FROM `hl_resource_schedule` WHERE `schedule_id`='$match_num' AND `type`='$type'";
        $sql_mode = "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'";
        $this->getDB($sql_mode)->executeRows($sql_mode);
        return $this->getDB($query)->executeRows($query);
    }
}
