<?php

namespace QK\HaoLiao\DAL;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Model\RedisModel;

class DALSoccerMatch extends BaseDAL {
    private $match_table = "hl_soccer_match";
    private $league_table = "hl_league";
    private $team_table = "hl_match_team";
    private $match_detail_table = "hl_soccer_match_detail";
    private $player_table = "hl_soccer_player";
    private $attention_table = "hl_soccer_attention";
    private $indexs_table = "hl_soccer_indexs";
    private $event_table = "hl_soccer_events";
    private $stat_table = "hl_soccer_ten_stat";
    private $relation_league_table = 'hl_resource_schedule';
    private $relation_table = 'hl_resource_schedule';
    private $lottery_table = "hl_soccer_lottery";


    private $page_num = 20;
	
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

    public function addPlayer($params) {
        return $this->insertData($params, $this->player_table);
    }

    public function addAttention($params) {
        return $this->insertData($params, $this->attention_table);
    } 

    public function addIndexs($params) {
        return $this->insertData($params, $this->indexs_table);
    }

    public function addEvent($params) {
        return $this->insertData($params, $this->event_table);
    }

    public function addStat($params) {
        return $this->insertData($params, $this->stat_table);
    }

    public function addLottery($params) {
        return $this->insertData($params, $this->lottery_table);
    }

    public function existsMatch($match_num) {
	      return $this->get($this->match_table, ['match_num' => $match_num]);
    }

    public function existsLottery($condition) {
        return $this->get($this->lottery_table, $condition);
    }

    public function existsMatchDetail($match_num) {
        return $this->get($this->match_detail_table, ['match_num' => $match_num], ['really']);
    }

    public function existsLeague($league_num) {
        return $this->get($this->league_table, ['league_num' => $league_num, 'type' => 1], ['id']);
    }

    public function existsTeam($team_num) {
        return $this->get($this->team_table, ['team_num' => $team_num, 'type' => 1], ['id']);
    }

    public function existsPlayer($player_num) {
        return $this->get($this->player_table, ['player_num' => $player_num], ['id']);
    }

    public function existsAttention($user_id, $match_num) {
        return $this->get($this->attention_table, ['user_id' => $user_id, 'match_num' => $match_num], ['id', 'attention']);
    }

    public function existsIndexs($condition) {
        return $this->get($this->indexs_table, $condition, ['id']);
    }

    public function existsEvent($match_num, $event_type, $event_team, $minute) {
        $condition = ['match_num' => $match_num, 'event_type' => $event_type, 'event_team' => $event_team, 'minute' => $minute];
        return $this->get($this->event_table, $condition, ['id']);
    }


    public function existsStat($match_num) {
        $condition = ['match_num' => $match_num];
        return $this->get($this->stat_table, $condition, ['id']);
    }

    public function getLotteryByCondition($condition) {
        return $this->select($this->lottery_table, $condition, [], 0, false, []);
    }

    public function getMatchNumByDate($date1, $date2, $condition = []) {
	    $val1 = ' between \'' . $date1 . '\' ';
	    $val2 = ' and \'' . $date2 . '\' ';
        $fields = ['match_num'];
        if ($condition['update']) {
            $now = $this->dealIndexsWithRedis('update');
            //$conditions['status'] = [' <> ', 4];
            $val1 = ' between \'' . date('Y-m-d H:i:s', time() + 3600 * $now) . '\'';
            $val2 = ' and \'' . date('Y-m-d H:i:s', time() + 3600 * $now + 3600) . '\'';
        }
        if ($condition['indexs']) {
            $now = $this->dealIndexsWithRedis('indexs');
            $conditions['status'] = [' <> ', 4];
            $date1 = date('Y-m-d H:i:s', time() + $now * 3600);
            $val1 = ' between \'' . $date1 . '\'';
            $val2 = ' and \'' . date('Y-m-d H:i:s', strtotime($date1) + 3600) . '\'';
        }
        if ($condition['analyze']) {
	    $now = $this->dealIndexsWithRedis('analyze');
            $val1 = ' between \'' . date('Y-m-d H:i:s', time() + 3600 * $now) . '\'';
            $val2 = ' and \'' . date('Y-m-d H:i:s', time() + 3600 * $now + 3600 * 1 ) . '\'';
            $conditions['date'] = [$val1, $val2];
            $conditions['status'] = [' <> ', 4];
            //$conditions['analyze'] = 0;
            $fields = ['match_num', 'host_team', 'guest_team'];
	        return $this->select($this->match_table, $conditions, $fields, 0, false, []);
        }
	if ($condition['forecast']) {
            $now = $this->dealIndexsWithRedis('forecast');
            $val1 = ' between \'' . date('Y-m-d H:i:s', time() + 3600 * $now ) . '\'';
            $val2 = ' and \'' . date('Y-m-d H:i:s', time() + 3600 * $now + 3600 ) . '\'';
	}
        $conditions['date'] = [$val1, $val2];
	    return array_column($this->select($this->match_table, $conditions, $fields, 0, false, []), 'match_num');
    }

    public function getLeagueNumByDate($date1, $date2, $condition = []) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $condition['date'] = [$val1, $val2];
        if ($condition['match_num']) {
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

    public function getMatchResultByDate($date1, $date2, $condition) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $conditions = ['date' => [$val1, $val2], 'valid' => 1, 'status' => [' in', ' (4, 13, 15, 14)']];
        $fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'score_point', 'score_all', 'status', 'note', 'red_card', 'yellow_card'];
        if ($condition['league_num']) {
            $conditions['league_num'] = ['in', '(' . $condition['league_num'] . ')'];
        }
        if ($condition['match_nums']) {
            $conditions['match_num'] = ['in', '(' . $condition['match_nums'] . ')'];
        }
	    if ($condition['page_num']) {
	    	$this->page_num = $condition['page_num'];
	    }
        $offset = ($condition['page'] - 1) * $this->page_num;
        $limit = $offset . ',' . $this->page_num;
        $result = $this->select($this->match_table, $conditions, $fields, false, $limit, ['date' => 'desc' ,'match_num' => 'desc']);
        $total = $this->counts($this->match_table, $conditions);
        $result['total'] = $total[0]['count'];
        return $result;
    }

    public function getMatchNowByDate($date1, $date2, $condition) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $conditions = ['date' => [$val1, $val2], 'valid' => 1, 'status' => [' <> ' , 4]];
        $fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'score_point', 'score_all', 'status', 'note', 'red_card', 'yellow_card'];
        if ($condition['page_num']) {
                $this->page_num = $condition['page_num'];
        }
        $offset = ($condition['page'] - 1) * $this->page_num; 
        $limit = $offset . ',' . $this->page_num;
        if ($condition['league_num']) {
            $conditions['league_num'] = ['in', '(' . $condition['league_num'] . ')'];
        }
        if ($condition['match_nums']) {
            $conditions['match_num'] = ['in', '(' . $condition['match_nums'] . ')'];
        }
        $result = $this->select($this->match_table, $conditions, $fields, false, $limit, ['date' => 'asc', 'match_num' => 'asc']);
        if ($condition['league_num'] && empty($result)) {
                unset($condition['league_num']);
                return $this->getMatchNowByDate($date1, $date2, $condition);
        }
        $total = $this->counts($this->match_table, $conditions);
        $result['total'] = $total[0]['count'];
        return $result;
    }

    public function getHotMatch($date1, $date2) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $conditions = ['date' => [$val1, $val2], 'valid' => 1, 'status' => [' not in ' , '(4, 13)']];
        $fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'score_point', 'score_all', 'status', 'is_hot', 'note'];
        $conditions['is_hot'] = 1;
        $order_by = ['date' => 'asc'];
        return $this->select($this->match_table, $conditions,$fields, 0, false, $order_by);
    }

    public function getMatchLaterByDate($date1, $date2, $condition) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $conditions = ['date' => [$val1, $val2], 'valid' => 1];
        if (isset($condition['has_information']) && $condition['has_information'] != -1) {
          $conditions['has_information'] = $condition['has_information'];
        }
        $fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'score_point', 'score_all', 'status', 'is_hot', 'note', 'has_information', 'red_card', 'yellow_card'];
        if ($condition['page_num']) {
                $this->page_num = $condition['page_num'];
        }
        if ($condition['league_num']) {
            $conditions['league_num'] = ['in', '(' . $condition['league_num'] . ')'];
        }
        if ($condition['match_nums']) {
            $conditions['match_num'] = ['in', '(' . $condition['match_nums'] . ')'];
        }
        if ($condition['status']) {
            $conditions['status'] = $condition['status'];
        }
        if ($condition['page'] === null) {
            $result = $this->select($this->match_table, $conditions, $fields, 0, false, ['date' => 'asc']);
        } else {
            $offset = ($condition['page'] - 1) * $this->page_num;
            $limit = $offset . ',' . $this->page_num;
            $result = $this->select($this->match_table, $conditions, $fields, false, $limit, ['date' => 'asc', 'match_num' => 'asc']);
        }
        if (empty($result)) {
            return [];
        }
        $total = $this->counts($this->match_table, $conditions);
        $result['total'] = $total[0]['count'];
        return $result;
    }

    public function getMatchByCondition($condition) {
        return $this->select($this->match_table, $condition, [], 0, false, []);
    }

    public function getAttentMatchList($date1, $date2, $condition) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $conditions = ['match_date' => [$val1, $val2], 'user_id' => $condition['user_id'], 'attention' => 1];
        $offset = ($condition['page'] - 1) * $this->page_num;
        $limit = $offset . ',' . $this->page_num;
        if ($condition['league_num']) {
            $sql = "select match_num from " . $this->attention_table . " a left join " . $this->match_table . " m on a.match_num = m.match_num where a.user_id = " . $condition['user_id'] . " and a.match_date " . $val1 . $val2 . ' and m.league_num in (' . $condition['league_num'] . ') and a.attention = 1 order by a.match_date desc limit ' . $offset . ' ' . $this->page_num;
            $sql_count = "select count(*) as count from " . $this->attention_table . " a left join " . $this->match_table . " m on a.match_num = m.match_num where a.user_id = " . $condition['user_id'] . " and a.match_date " . $val1 . $val2 . ' and m.league_num in (' . $condition['league_num'] . ') and a.attention = 1';
            $data = $this->getDB($sql)->executeRows($sql);
            $total = $this->getDB($sql_count)->executeRow($sql_count);
            $total = $total[0]['count'];
        } else {
            $data = array_column($this->select($this->attention_table, $conditions, ['match_num'], false, $limit, ['match_date' => 'asc','match_num' => 'asc']), 'match_num');
            $total = $this->counts($this->attention_table, $conditions);
            $total = $total[0]['count'];
        }
        return ['data' => $data, 'total' => $total];
    }


    public function getAttentMatchByDate($date1, $date2, $user_id) {
        $val1 = ' between \'' . $date1 . '\' ';
        $val2 = ' and \'' . $date2 . '\' ';
        $condition['attention'] = 1;
        $condition['match_date'] = [$val1, $val2];
        $condition['user_id'] = $user_id;
        return array_column($this->select($this->attention_table, $condition, ['match_num'], 0, false, ['match_date' => 'asc']), 'match_num');
    }

    public function getAttentStatus($user_id, $match_num) {
        $this->get($this->attention_table, ['match_num' => $match_num, $user_id => $user_id], ['attention']);
    }

    public function getMatchAttentUser($match_num) {
        $condition = ['match_num' => $match_num, 'attention' => 1];
        return array_column($this->select($this->attention_table, $condition, ['user_id'], 0, false, ['match_date' => 'desc']), 'user_id');
    }

    public function getMatchDetailByNum($match_num) {
        $result = $this->select($this->match_detail_table, ['match_num' => $match_num], [], 0, false);
        return $result[0];
    }

    public function getMatchInfoByNum($match_num) {
        $result = $this->select($this->match_table, ['match_num' => $match_num], [], 0, false);
	    if ($result) {
            	return $result[0];
	    } else {
	    	return [];
	    }
    }

    public function getLeagueInfoByNum($league_num) {
        return $this->get($this->league_table, ['league_num' => $league_num, 'type' => 1]);
    }

    public function getTeamInfoByNum($team_num) {
        return $this->get($this->team_table, ['team_num' => $team_num, 'type' => 1]);
    }

    public function getPlayerInfoByNum($player_num) {
        $fields = ['player_num', 'team_num', 'name', 'shit_num', 'pos', 'photo'];
        return $this->get($this->player_table, ['player_num' => $player_num], $fields);
    }

    public function getIndexsInfoByNum($match_num, $indexs_type, $comp_num = 0) {
        $condition = ['match_num' => $match_num, 'indexs_type' => $indexs_type];
        if ($comp_num) {
            $condition['comp_num'] = ['in', '(' . $comp_num . ')'];
        }
        $result = $this->select($this->indexs_table, $condition, [], 0, false, ['indexs_date' => 'desc']);
        return $result;
    }

    public function getIndexsCompByNum($match_num, $indexs_type) {
        $result = $this->select($this->indexs_table, ['match_num' => $match_num, 'indexs_type' => $indexs_type], ['distinct comp_num', 'comp_name'], 0, false);
        return $result;
    }

    public function getIndesxById($id) {
        return $this->get($this->indexs_table, ['id' => $id]);
    }

    public function getIndexsByComp($match_num, $indexs_type, $comp_num, $is_first = 0) {
        $order = [];
        $limit = false;
        if ($is_first == 1) {
            $order['indexs_date'] = 'asc';
            $limit = 1;
        } elseif ($is_first == 2) {
            $order['indexs_date'] = 'desc';
            $limit = 1;
        } else {
            $order['indexs_date'] = 'desc';
        }
        $condition = ['match_num' => $match_num, 'indexs_type' => $indexs_type, 'comp_num' => $comp_num];
        $center_indexs = 'center_indexs';
        if ($indexs_type == 2) {
            $center_indexs = 'center_indexs * -1 as center_indexs';
        }
        $fields = ['left_indexs', $center_indexs, 'right_indexs', 'indexs_date', 'id'];
        $result = $this->select($this->indexs_table, $condition, $fields, 0, $limit, $order);
        if ($result) {
            if ($is_first) {
                return  $result[0];
            } else {
                return $result;
            }
        } else {
            return [];
        }
    }

    public function getLiveStatByNum($match_num) {
        $result = $this->select($this->stat_table, ['match_num' => $match_num], [], 0, false);
        return $result[0];
    }

    public function getLiveEventByNum($match_num) {
        $result = $this->select($this->event_table, ['match_num' => $match_num], [], 0, false, ['minute' => 'desc']);
        return $result;
    }

    public function getCountMatchCases($match_num,$platform=1) {

        $sql = "SELECT count(re.id) AS count FROM `hl_resource_schedule` re LEFT JOIN `hl_resource` r ON re.`resource_id` = r.`resource_id`  WHERE re.schedule_id = $match_num AND re.`type` =1 AND r.resource_status = 1";
        switch($platform){
            case 1:
                $sql .= " AND r.bd_display = 1";
                break;
            case 2:
                $sql .= " AND r.wx_display = 1";
                break;
        }
        $count = $this->getDB($sql)->executeValue($sql);
        return $count;
    }

    public function getLotteryMatch($date1, $date2, $condition, $limit = 1) {
        $page = $condition['page'];
        $page_num = $condition['page_num'] * 2;
        $start = ($page - 1) * $page_num;
        $lottery_type = $condition['lottery_type'];
        $sql_count = "select count(distinct match_num) as count";
        $sql = "select *";
        $sql .= " from " . $this->lottery_table . " where `date` between '$date1' and '$date2' and lottery_type = $lottery_type";
        $sql_count .= " from " . $this->lottery_table . " where `date` between '$date1' and '$date2' and lottery_type = $lottery_type";
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
            $sql .= " and match_num not in (SELECT DISTINCT match_num FROM $this->lottery_table WHERE `date` BETWEEN '$date1' AND '$date2' AND  lottery_type = 1)";
            $sql_count .= " and match_num not in (SELECT DISTINCT match_num FROM $this->lottery_table WHERE `date` BETWEEN '$date1' AND '$date2' AND  lottery_type = 1)";
        }
        if ($limit) {
            $sql .= " order by `date` asc, `lottery_num` asc limit $start, $page_num";
        }
        $sql_count .= " order by `date` asc";
        $data = $this->getDB($sql)->executeRows($sql);
        $count = $this->getDB($sql_count)->executeValue($sql_count);
        return ['data' => $data, 'count' => $count];
    }

    public function getJcMatchNum($date1, $date2) {
        $sql = "SELECT DISTINCT match_num FROM $this->lottery_table WHERE `date` BETWEEN '$date1' AND '$date2' AND  lottery_type = 1";
        $data = $this->getDB($sql)->executeRows($sql);
        $datas = array_column($data, 'match_num');
        return $datas;
    }

    public function getAllMatch($date1, $date2, $condition) {
        $page = $condition['page'];
        $page_num = $condition['page_num'];
        $start = ($page - 1) * $page_num;
        $fields_a  = ', m.date, l.name as league_name, l.short_name as league_short_name, t.name as host_team_name, l.league_num ';
        $fields_b =  ', t.name as guest_team_name ';
        $sql_c = "select m.match_num %s from $this->match_table m left join $this->team_table t on m.%s_team = t.team_num left join hl_league l on m.league_num = l.league_num where m.date between '$date1' and '$date2' and l.type = 1 and t.type = 1";
        if ($condition['league_num']) {
            $sql_c .= ' and m.league_num = ' . $condition['league_num'];
        }
        $sql_a = sprintf($sql_c, $fields_a, 'host');
        $sql_b = sprintf($sql_c, $fields_b, 'guest');
        $sql_d = "from ($sql_a) a left join ($sql_b) b on a.match_num = b.match_num";
        if ($condition['key_words']) {
            $key = $condition['key_words'];
            $like = " where (a.league_name like '%$key%' or a.host_team_name like '%$key%' or b.guest_team_name like '%$key%' or a.league_short_name like '%$key%')";
            $sql_d .= $like;
        }
        $sql_d .= " order by a.date asc";
        $sql = "select a.match_num, a.date, a.league_name, a.league_short_name, a.league_num, a.host_team_name, b.guest_team_name " . $sql_d;
        $sql .= " limit $start, $page_num";
        $sql_count = "select count(a.match_num) as count " . $sql_d;
        //var_dump($sql);die;
        $data = $this->getDB($sql)->executeRows($sql);
        $count = $this->getDB($sql_count)->executeValue($sql_count);
        return ['data' => $data, 'count' => $count];
        
    }

    public function getLotteryLeague($date1, $date2, $condition) {
        $lottery_type = $condition['lottery_type'] ?: 1;
        $sql = "SELECT l.league_num,l.league_short_name FROM $this->lottery_table l LEFT JOIN $this->league_table le ON l.league_num = le.league_num WHERE l.date BETWEEN '$date1' AND '$date2' and lottery_type = $lottery_type  GROUP BY l.league_num ORDER BY le.`initial` asc";
        $set_sql = "SET sql_mode='STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'";
        $this->getDB($set_sql)->executeRows($set_sql);
        $data = $this->getDB($sql)->executeRows($sql);
        return $data;
    }

    public function updateMatch($data, $match_num) {
	      return $this->updateByCondition(['match_num' => $match_num], $data, $this->match_table);
    }

    public function updatePlayer($data, $player_num) {
          return $this->updateByCondition(['player_num' => $player_num], $data, $this->player_table);
    }

    public function updateLeague($data, $league_num) {
          return $this->updateByCondition(['league_num' => $league_num, 'type' => 1], $data, $this->league_table);
    }

    public function updateTeam($data, $team_num) {
        return $this->updateByCondition(['team_num' => $team_num, 'type' => 1], $data, $this->team_table);
    }

    public function updateMatchDetail($data, $match_num) {
        return $this->updateByCondition(['match_num' => $match_num], $data, $this->match_detail_table);
    }

    public function updateAttention($attention, $id) {
        $attention += 1;
        $attention > 1 && $attention = 0;
        return $this->updateByCondition(['id' => $id], ['attention' => $attention], $this->attention_table);
    }

    public function updateStat($data, $match_num) {
        return $this->updateByCondition(['match_num' => $match_num], $data, $this->stat_table);
    }

    public function updateIndexs($data, $condition) {
        return $this->updateByCondition($condition, $data, $this->indexs_table);
    }

    public function updateEvent($data, $condition) {
        return $this->updateByCondition($condition, $data, $this->event_table);
    }


     public function dealIndexsWithRedis($prefix = '') {
        $key = $prefix . 'soccer_indexs_deal';
        $redisModel = new RedisModel('match');
        $now = $redisModel->redisIncr($key, 3600);
        $limit = 72;
        $start = 1;
        if ($prefix == 'update') {
                $limit = 24;
                $start = -12;
        }
        if ($prefix == 'indexs' || $prefix == 'analyze') {
                $limit = 24;
                $start = -3;
        }
        if ($now > $limit) {
                $redisModel->redisSet($key, $start, 3600);
        }
        $now = $redisModel->redisGet($key);
        var_dump($now);
        return $now;
    }


    /*兼容老数据*/
    public function getOldLeague() {
        $sql_league = "SELECT  m.* FROM `hl_match_league` m LEFT JOIN `hl_resource_schedule` r ON m.`league_id` = r.`league_id` LEFT JOIN `hl_resource` hr ON r.`resource_id` = hr.resource_id  WHERE m.match_type = 1 AND hr.resource_status = 1  GROUP BY m.`league_id`";
        $data = $this->getDB($sql_league)->executeRows($sql_league);
        return $data;
    }

    public function sameLeague($condition = ['name' => '', 'short_name' => ''], $type = 1) {
        foreach ($condition as $k => $v) {
            if ($v) {
                $res = $this->get($this->league_table, [$k => $v, 'type' => $type], ['id', 'league_num', 'old_id']); 
                if ($res) {
                    return $res;
                }
            }
        }
        return [];
    }

    public function updateRelationLeague($data, $league_id) {
        return $this->updateByCondition(['league_id' => $league_id], $data, $this->relation_league_table);
    }

    public function updateLottery($data, $id) {
        return $this->updateByCondition(['id' => $id], $data, $this->lottery_table);
    }

    public function findLotteryById($id) {
      return $this->get($this->lottery_table, ['id' => $id]);
    }

}
