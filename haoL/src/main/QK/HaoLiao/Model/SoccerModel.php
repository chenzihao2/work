<?php

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALSoccerMatch;
use QK\HaoLiao\Common\PinYin;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\DAL\DALResource;
use QK\HaoLiao\Model\UserFollowModel;
use QK\HaoLiao\Model\BetRecordModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\WSF\Settings\AppSetting;
class SoccerModel extends BaseModel {

    private $_redisModel;
    const DOMAIN_7M = 'http://feed.sportsdt.com/szyuanzhan/';
    const PATH_7M = 'soccer/?';
    private $match_list = self::DOMAIN_7M . self::PATH_7M . 'type=getschedulebydate&date='; //获取日期对应的赛程
    private $base_info = self::DOMAIN_7M . self::PATH_7M . 'type=getgameinfo&gameid=';  //获取比赛基本信息
    private $forecast_info = self::DOMAIN_7M . self::PATH_7M . 'type=getgameprediction&gameid=';  //获取比赛预测信息
    private $past_info = self::DOMAIN_7M . self::PATH_7M . 'type=getgameanalyse&gameid='; //获取比赛往级信息
    private $goal_time = self::DOMAIN_7M . self::PATH_7M . 'type=getgamegoaltimestats&gameid='; //获取比赛球队进球时间分布
    private $get_pic = self::DOMAIN_7M . 'soccer/getpic.aspx?etype=%d&id=%d'; //获取图片
    private $yapan_match = self::DOMAIN_7M . self::PATH_7M . 'type=getahoddsinfo&gameid='; //获取亚盘信息
    private $ouzhi_match = self::DOMAIN_7M . self::PATH_7M . 'type=gethdaoddsinfo&gameid='; //获取欧指信息
    private $daxiao_match = self::DOMAIN_7M . self::PATH_7M . 'type=getouoddsinfo&gameid='; //获取大小球信息
    private $yapan_list = self::DOMAIN_7M . self::PATH_7M . 'type=getahoddslist&t=%d&pid=%d%s';
    private $jc_match = self::DOMAIN_7M . self::PATH_7M . 'type=getschedule_jc&date='; //获取日期对应的竞彩赛程
    private $jc_match_lottery = self::DOMAIN_7M . self::PATH_7M . 'type=getschedule_jc_sp&date='; //获取日期对应的竞彩比赛具体参考值
    private $jc_match_history = self::DOMAIN_7M . self::PATH_7M . 'type=getsphistory_jc&gameid='; //获取日期对应的竞彩比赛具体参考值
    private $dc_match = self::DOMAIN_7M . self::PATH_7M . 'degree=%s&type=getschedule_dc'; //获取日期对应的单场赛程
    private $dc_time = self::DOMAIN_7M . self::PATH_7M . 'type=getdegree_dc&year='; //获取单场彩期
    private $dc_match_lottery = self::DOMAIN_7M . self::PATH_7M . 'degree=%s&type=getschedule_dc_sp'; //获取单场彩期
    private $sfc_match = self::DOMAIN_7M . self::PATH_7M . 'degree=%s&type=getschedule_sfc';
    private $sfc_time = self::DOMAIN_7M . self::PATH_7M . 'type=getdegree_sfc&year=';
    private $live_match = self::DOMAIN_7M . self::PATH_7M . 'type=getlivedata'; //获取实时比分数据
    private $goal_match = self::DOMAIN_7M . self::PATH_7M . 'type=getgamegoaldata&gameid='; //获取单场技术统计和事件
    private $now_match = self::DOMAIN_7M . self::PATH_7M . 'type=getlivegame';
    private $ouzhi_log = self::DOMAIN_7M . self::PATH_7M . 'type=gethdaoddslog&gameid=%d&pid=%d';


    public $matchResultTimeScope = 86400;
    public $matchNowTimeScope = 86400;
    private $default_team_icon = 'https://hl-static.haoliao188.com/soccer_team_qiuduito.png';
    public $match_colors = [
        'color_1' => '#4d73ec',
        'color_2' => '#ed3030',
        'color_3' => '#666666',
    ];
    public $match_status = [
	      0 => '未',
	      1 => '上半场',
	      2 => '中',
	      3 => '下半场',
	      4 => '完',
	      5 => '中断',
	      6 => '取消',
	      7 => '加',
	      8 => '加时',
	      9 => '加时',
	      10 => '完场',
	      11 => '点',
	      12 => '全场结束',
	      13 => '延',
	      14 => '腰斩',
	      15 => '待定',
	      16 => '金球',
	      17 => '未开始'
    ];

    public $match_status_color = [
        0 => 'color_1',
        1 => 'color_2',
        2 => 'color_2',
        3 => 'color_2',
        4 => 'color_3',
        5 => 'color_3',
        6 => 'color_3',
        7 => 'color_2',
        10 =>'color_3',
        11 =>'color_2',
        12 =>'color_3',
        13 =>'color_3',
        14 =>'color_3',
        15 =>'color_3',
        16 =>'color_3',
        16 =>'color_3',
        17 =>'color_1'
    ];

    private $ouzhiComp = [
        17 => 'BET365',
        235 => '威廉希尔',
        716 => '伟德',
        66 => 'Bwin',
        736 => '必发',
        123 => 'Interwetten',
        253 => '金宝博',
        156 => '平博',
        254 => '利博',
        1 => '10BET',
        117 => '12BET',
        680 => '18BET',
        182 => 'SNAI',
        172 => '利记',
        250 => '澳彩',
        460 => '香港马会',
        308 => 'Crown'
    ];

    private $yapanComp = [
        3000248 => '澳彩',
        3000181 => 'BET365',
        3000021 => '威廉希尔',
        3000390 => '易胜博',
        3000048 => '伟德',
        3000379 => '明陞',
        3000343 => '金宝博',
        3000068 => '利博',
        3000368 => '利记',
        3000271 => '10BET',
        3000471 => '12BET',
        400000 => 'S2'
    ];


    private $stat_map = [
        '射门次数' => 'shot_times',
        '射正球门次数' => 'shot_goal_times',
        '射失' => 'shot_no_times',
        '角球次数' => 'corner',
        '越位次数' => 'offsied',
        '黄牌数' => 'yellow',
        '控球率' => 'control',
        '犯规次数' => 'foul',
        '进攻' => 'attack',
        '危险进攻' => 'risk_attack'
    ];

    private $stat_map_front = [
        'control' => '控球率%',
        'attack' => '进攻',
        'risk_attack' => '危险进攻',
        'shot_times' => '射门',
        'shot_goal_times' => '射正',
        'shot_no_times' => '射偏',
        'corner' => '角球',
        'offside' => '越位',
        'foul' => '犯规',
        'yellow' => '黄牌',
        'red' => '红牌',
    ];

    private $event_sen_map = [
        0 => [
            '%s一脚打门，球进啦！助攻的是%s。（%s）',
            '%s一脚打门，球进啦！（%s）'
        ],
        1 => '裁判跑向了罚球点，%s获得了一个点球，球进啦！（%s）',
        2 => '太可惜了，%s立功心切，把球打进了自家球门。（%s）',
        3 => '%s吃到了一张黄牌，要小心啊！（%s）',
        4 => '%s吃到了一张红牌，他要下场啦！（%s）',
        5 => '%s又吃一张黄牌，两黄变一红，他要下场啦！（%s）',
        8 => '%s点球失误，球打飞啦！（%s）',
        9 => '%s换下了%s（%s）'
    ];

    private $event_pic = [
        0 => 'https://hl-static.haoliao188.com/soccer_eventjinqiu.png',
        1 => 'https://hl-static.haoliao188.com/soccer_eventdianqiushezhong.png',
        2 => 'https://hl-static.haoliao188.com/soccer_eventwulongqiu.png',
        3 => 'https://hl-static.haoliao188.com/soccer_eventhuangpai.png',
        4 => 'https://hl-static.haoliao188.com/soccer_event_hongpai.png',
        5 => 'https://hl-static.haoliao188.com/soccer_eventlianghuang.png',
        8 => 'https://hl-static.haoliao188.com/soccer_eventdianqiusheshi.png',
        9 => 'https://hl-static.haoliao188.com/soccer_eventhuanren.png',
    ];

    public function matchResult($date, $condition) {
        $date2 = date('Y-m-d H:i:s', time());
        $date1 = date('Y-m-d', strtotime($date) - $this->matchResultTimeScope);
        $attents = [];
        if ($condition['user_id']) {
            $attents = $this->userAttentList($date1, $date2, $condition['user_id']);
        }
        if ($condition['league_type'] && in_array($condition['league_type'], [2, 3, 4])) {
            $match_nums = $this->getSpecialMatchNum($condition['league_type'], $date1, $date2);
            if ($match_nums) {
                //$jc_match_nums = $this->match_dal->getJcMatchNum($date1, $date2);
                //$match_nums = array_diff($match_nums, $jc_match_nums);
                $condition['match_nums'] = implode(',', $match_nums);
            } else {
                return ['data' => [], 'total' => 0];
            }
        }
        if (isset($condition['league_type'])) {
            unset($condition['league_type']);
        }
        $matchs = $this->match_dal->getMatchResultByDate($date1, $date2, $condition);
        return $this->assembleMatchResult($matchs, $attents);
    }

    public function matchNow($date, $condition) {
        $date1 = $date;
        $date1 = date('Y-m-d H:i:s', time() - 10800);
        $date2 = date('Y-m-d 12:00:00', time() + $this->matchNowTimeScope);
        $attents = [];
        if ($condition['user_id']) {
            $attents = $this->userAttentList($date1, $date2, $condition['user_id']);
        }
        if ($condition['league_type'] && in_array($condition['league_type'], [2, 3, 4])) {
            $match_nums = $this->getSpecialMatchNum($condition['league_type'], $date1, $date2);
            if ($match_nums) {
                //$jc_match_nums = $this->match_dal->getJcMatchNum($date1, $date2);
                //$match_nums = array_diff($match_nums, $jc_match_nums);
                $condition['match_nums'] = implode(',', $match_nums);
            } else {
                return ['data' => [], 'total' => 0];
            }
        }
        if (isset($condition['league_type'])) {
            unset($condition['league_type']);
        }
        $matchs = $this->match_dal->getMatchNowByDate($date1, $date2, $condition);
        $result = $this->assembleMatchResult($matchs, $attents,$condition['platform']);
        $data  = $result['data'];
        $total = $result['total'];
        foreach ($data as $k => $v) {
            if (in_array($v['status'], [13, 14, 15])) {
                $tmp_time = strtotime($v['date'] . ' ' . $v['time']);
                if ($tmp_time < time()) {
                    unset($data[$k]);
                    $total--;
                    continue;
                }
            }
        }
        $data = array_values($data);
        return ['data' => $data, 'total' => $total];
    }

    public function matchLater($date, $condition) {
        $date1 = $date;
        $date2 = date('Y-m-d', strtotime($date) + $this->matchResultTimeScope);
        $attents = [];
        if ($condition['user_id']) {
            $attents = $this->userAttentList($date1, $date2, $condition['user_id']);
        }
        if ($condition['league_type'] && in_array($condition['league_type'], [2, 3, 4])) {
            $match_nums = $this->getSpecialMatchNum($condition['league_type'], $date1, $date2);
            if ($match_nums) {
                //$jc_match_nums = $this->match_dal->getJcMatchNum($date1, $date2);
                //$match_nums = array_diff($match_nums, $jc_match_nums);
                $condition['match_nums'] = implode(',', $match_nums);
            } else {
                return ['data' => [], 'total' => 0];
            }
        }
        if (isset($condition['league_type'])) {
            unset($condition['league_type']);
        }
        $matchs = $this->match_dal->getMatchLaterByDate($date1, $date2, $condition);
        return $this->assembleMatchResult($matchs, $attents);
    }

    public function matchLottery($date1, $date2, $condition) {
       $lottery_type = $condition['lottery_type']; 
       if(in_array($lottery_type, [1, 2])) {
            $original_data = $this->match_dal->getLotteryMatch($date1, $date2, $condition);
            $count = $original_data['count'] ?: 0;
            $original_data = $original_data['data'];
            $result = [];
            foreach ($original_data as $item) {
                if ($lottery_type == 2 && $item['d'] != '-') {
                    continue;
                }
                if ($item['h'] == '-') {
                    continue;
                }
                $result[$item['match_num']]['match_num'] = $item['match_num']; 
                $result[$item['match_num']]['league_num'] = $item['league_num']; 
                $result[$item['match_num']]['date'] = substr($item['date'], 0, 16); 
                $result[$item['match_num']]['lottery_num'] = $item['lottery_num']; 
                if ($lottery_type == 2) {
                    $lottery_data = explode('-', $item['lottery_num']);
                    $result[$item['match_num']]['lottery_num'] = '北单' . sprintf('%03d', $lottery_data[1]);
                }
                $result[$item['match_num']]['league_short_name'] = $item['league_short_name']; 
                $result[$item['match_num']]['host_team_name'] = $item['host_team_name']; 
                $result[$item['match_num']]['guest_team_name'] = $item['guest_team_name']; 
                $result[$item['match_num']]['lottery_type'] = $item['lottery_type']; 
                $tmp = [];
                $tmp = [
                    'is_signle' => $item['is_signle'],
                    'h' => $item['h'],
                    'w' => $item['w'],
                    'd' => $item['d'],
                    'l' => $item['l'],
                    'lottery_id' => $item['id']
                ];
                if ($item['h'] == 0) {
                    $result[$item['match_num']]['odds'][0] = $tmp;
                } else {
                    $result[$item['match_num']]['odds'][1] = $tmp;
                }
            }
            foreach ($result as $k => $item) {
                ksort($item['odds']);
                $result[$k]['odds'] = array_values($item['odds']);
            }
            $result = array_values($result);
            return ['data' => $result, 'count' => $count];
       } else {
           $all_match = $this->match_dal->getAllMatch($date1, $date2, $condition);
           return ['data' => $all_match['data'], 'count' => $all_match['count']];
       }
    }

    public function matchAdmin($date1, $date2, $condition) {
        if (empty($date1) || empty($date2)) {
            $date1 = date('Y-m-d', time());
            $date2 = date('Y-m-d', time() + $this->matchResultTimeScope);
        }
        $matchs = $this->match_dal->getMatchLaterByDate($date1, $date2, $condition);
        return $this->assembleMatchResult($matchs, []);
    }

    public function attentMatch($data) {
        $exists = $this->match_dal->existsAttention($data['user_id'], $data['match_num']);
        if (!$exists) {
            return  $this->match_dal->addAttention($data);
        }
        return $this->match_dal->updateAttention($exists['attention'], $exists['id']);
    }

    public function userAttentList($date1, $date2, $user_id) {
        return $this->match_dal->getAttentMatchByDate($date1, $date2, $user_id);
    }

    public function attentMatchList($condition) {
        $date2 = date('Y-m-d', time() + $this->matchResultTimeScope * 2);
        $date1 = date('Y-m-d', time() - $this->matchResultTimeScope);
        $attents = $this->match_dal->getAttentMatchList($date1, $date2, $condition);
        $matchs = [];
        if (empty($attents['data'])) {
            return $attents;
        } 
        foreach ($attents['data'] as $v) {
            $match_info = $this->match_dal->getMatchInfoByNum($v);
            $matchs[] = $match_info;
        }
        $matchs['total'] = $attents['total'];
        return $this->assembleMatchResult($matchs, $attents['data']);
    }


    public function leagueList($tab_type, $user_id = 0, $league_type = 0, $date1 = '', $date2 = '') {
        switch ($tab_type) {
        case 1:
            $date1 = date('Y-m-d 00:00:00', time() - $this->matchResultTimeScope);
            $date2 = date('Y-m-d H:i:s', time());
            $condition = ['valid' => 1, 'status' => ['in', '(4, 13, 14, 15)']];
        break;
        case 2:
            $date1 = date('Y-m-d H:i:s', time() - 10800);
            $date2 = date('Y-m-d 12:00:00', time() + $this->matchNowTimeScope);
            $condition = ['valid' => 1, 'status' => ['not in' , '(4,13,15)']];
        break;
        case 3:
            $date1 = date('Y-m-d', time() + $this->matchResultTimeScope);
            $date2 = date('Y-m-d', strtotime($date1) + $this->matchResultTimeScope);
            $condition = ['valid' => 1];
        break;
        case 4:
            $date1 = date('Y-m-d', time() - $this->matchResultTimeScope);
            $date2 = date('Y-m-d', time() + $this->matchResultTimeScope * 2);
            $attent_matchs = $this->userAttentList($date1, $date2, $user_id);
            $matchs = implode(',', $attent_matchs);
            $condition = ['match_num' => $matchs];
        break;
        default :
            if (empty($date1) || empty($date2)) {
                $date1 = date('Y-m-d H:i:s', time());
                $date2 = date('Y-m-d 23:59:59', time() + $this->matchResultTimeScope * 2);
            }
            $data = $this->getLotteryLeague($date1, $date2, $league_type);
            return $data; 
        }
        $league_type_map = [
            1 => 'is_recommend',
            2 => 'is_jc',
            3 => 'is_sfc',
            4 => 'is_bd',
        ];
        if ($league_type && in_array($league_type, [2, 3, 4])) {
            $special_matchs = $this->getSpecialMatchNum($league_type, $date1, $date2);
            if (empty($special_matchs)) {
                return ['data' => [], 'match_count' => 0];
            }
        }
        if ($special_matchs && !isset($condition['match_num'])) {
            $condition['match_num'] = implode(',', $special_matchs);
        }
        $league_nums = $this->match_dal->getLeagueNumByDate($date1, $date2, $condition);
        if ($league_type == 4) {
            $jc_match_nums = $this->match_dal->getJcMatchNum($date1, $date2);
            $condition['match_num']= implode(',', array_diff(explode(',', $condition['match_num']), $jc_match_nums));
        }
        $league_match_count = $this->match_dal->getLeagueCountMatch($date1, $date2, $condition);
        $league_list = [];
        $match_count = 0;
        if ($league_nums) {
          foreach($league_nums as $v) {
            $league_info = $this->match_dal->getLeagueInfoByNum($v);
            $league_info['match_count'] = $league_match_count[$v] ?: 0;
            if (empty($league_info['match_count'])) {
                continue;
            }
            $league_info['name'] = $league_info['short_name'];
            unset($league_info['ctime'], $league_info['utime']);
            if (!$league_info['initial']) {
              continue;
            }
            if ($league_type == 1 && !$league_info[$league_type_map[$league_type]]) {
                continue;
            }
            $league_list[$league_info['initial']][] = $league_info;
            $match_count += $league_info['match_count'];
          }
        }
        ksort($league_list);
        $res = [];
        foreach ($league_list as $k => $lv) {
            $tmp['title'] = $k;
            $tmp['data'] = $lv;
            $res[] = $tmp;
        } 
        return ['data' => $res, 'match_count' => $match_count];
    }

    public function matchFormation($match_num) {
        $match_detail = $this->match_dal->getMatchDetailByNum($match_num);
        $host_formation = $guest_formation = [];
        $host_formation['formation'] = $this->addAcrossLine($match_detail['host_formation']);
        $host_formation['first'] = $this->dealFormationPlayer($match_detail['host_first_player']);
        $host_formation['back'] = $this->dealFormationPlayer($match_detail['host_back_player']);
        $guest_formation['first'] = $this->dealFormationPlayer($match_detail['guest_first_player']);
        $guest_formation['back'] = $this->dealFormationPlayer($match_detail['guest_back_player']);
        $guest_formation['formation'] = $this->addAcrossLine($match_detail['guest_formation']);
        $map = [];
        if (!empty($host_formation['formation']) && !empty($guest_formation['formation'])) {
                $map1 = $this->dealFormationMap($host_formation['formation'], $host_formation['first'], 1);
                $map2 = $this->dealFormationMap($guest_formation['formation'], $guest_formation['first'], 0);
                $map = array_merge($map1, $map2);
        }
        $result = ['host_formation' => $host_formation, 'guest_formation' => $guest_formation, 'really' => $match_detail['really'], 'map' => $map];
        return $result;
        
    }

    private function dealFormationMap($formation, $players, $host = 1) {
        $map = [];
        $formats = explode('-', $formation);
        $format_player = array_sum($formats);
        $format_player++;
        if ($format_player != count($players)) {
            return [];
        }
        $s = $h = $z = $q = [];
        foreach ($players as $v) {
            if ($v['pos'] == 0) {
                $s[] = $v;
            }
            if ($v['pos'] == 1) {
                $h[] = $v;
            }
            if ($v['pos'] == 2) {
                $z[] = $v;
            }
            if ($v['pos'] == 3) {
                $q[] = $v;
            }
        }
        $map[] = $s;
       if (count($formats) == 3) {
            $map[] = $h;
            $map[] = $z;
            $map[] = $q;
        } elseif (count($formats) == 4) {
            $all = array_merge($h, $z, $q);
            foreach ($formats as $v) {
                $tmp = [];
                for ($i = 0; $i < $v; $i++) {
                    $tmp[] = array_shift($all);
                }
                $map[] = $tmp;
            }
        } else {
            return [];
        } 
        if (!$host) {
            $map = array_reverse($map);
        }
        return $map;
    }

    public function dealFormationPlayer($players) {
        if (empty($players)) {
            return [];
        }
        if (!is_array($players)) {
            $players = explode(',', $players);
        }
        $result = [];
        foreach ($players as $player) {
            $player_info = $this->match_dal->getPlayerInfoByNum($player);
            if (empty($player_info)) {
                continue;
            }
            $result[] = $player_info;
        }
        return $result;
    }


    public function assembleMatchResult($data, $attents = [],$platform=1) {
	    $total = 0;
        if (isset($data['total']) && !empty($data['total'])) {
            $total = $data['total'];
            unset($data['total']);
        }
        if (empty($data) || (empty($total) && count($data) > 1 && $attents != 'hot')) {
          return ['data' => [], 'total' => 0];
        }
        $datas = [];
        foreach ($data as $kk => $vv) {
            if (!empty($vv)) {
                $datas[$kk] = $vv;
            } else {
                $total--;
            }
        }
        $data = $datas;
        foreach ($data as $k => $v) {
	        $time = 0;
	        $time = strtotime($data[$k]['date']);
          	$leagueInfo = $this->match_dal->getLeagueInfoByNum($v['league_num']);
          	$hostInfo = $this->match_dal->getTeamInfoByNum($v['host_team']);
          	$guestInfo = $this->match_dal->getTeamInfoByNum($v['guest_team']);
          	$data[$k]['league_short_name'] = $leagueInfo['short_name'] ?: '';
          	$data[$k]['league_color'] = $leagueInfo['color'] ?: '';
          	$data[$k]['host_team_name'] = $hostInfo['name'] ?: '';
          	$data[$k]['host_team_logo'] = $hostInfo['logo'] ?: $this->default_team_icon;
          	$data[$k]['guest_team_name'] = $guestInfo['name'] ?: '';
          	$data[$k]['guest_team_logo'] = $guestInfo['logo'] ?: $this->default_team_icon;
          	if (strpos($data[$k]['score_all'], ',')) {
          	    $data[$k]['score_all']  = str_replace(',', '-', $data[$k]['score_all']);
          	}
            if (strpos($data[$k]['red_card'], ',')) {
                $data[$k]['red_card']  = str_replace(',', '-', $data[$k]['red_card']);
            }
            if (strpos($data[$k]['yellow_card'], ',')) {
                $data[$k]['yellow_card']  = str_replace(',', '-', $data[$k]['yellow_card']);
            } 
		if (isset($v['weather'])) {
            		if (empty(trim($v['weather'])) || strlen($v['weather'] < 1)) {
            		    $data[$k]['weather'] = '';
            		} else {
            		    $data[$k]['weather'] = $v['weather'];
            		}
		}
          	$data[$k]['is_attention'] = 0;
	  	      $data[$k]['date'] = date('Y-m-d', $time);
	  	      $data[$k]['time'] = date('H:i', $time);
          	$data[$k]['match_status']['status'] = $this->match_status[$v['status']];
          	$data[$k]['match_status']['color'] = $this->match_colors[$this->match_status_color[$v['status']]];
          	if (is_array($attents) && in_array($v['match_num'], $attents)) {
          	    $data[$k]['is_attention'] = 1;
          	}
        	if (in_array($v['status'], [1, 3])) {
		        $delay = bcsub(time(), $time);
        	    if ($delay > 0) {
        	        $minute = bcdiv($delay, 60);
        	        if ($v['status'] == 3) {
        	            $minute -= 15;
        	        }
        	    } else {
        	        $minute = 0;
        	    }
        	    $data[$k]['minute'] = $minute;
        	}
		    if (isset($v['status']) && $v['status'] === 0 && count($data) > 1) {
			    $data[$k]['score_all'] = '';
		    }
            $data[$k]['host_score'] = $data[$k]['guest_score'] = '';
            if ($data[$k]['score_all']) {
                $score_tmp = explode('-', $data[$k]['score_all']);
                $data[$k]['host_score'] = $score_tmp[0];
                $data[$k]['guest_score'] = $score_tmp[1];
            }
            $data[$k]['cases'] = $this->whetherHadCase($v['match_num'],$platform);
            $corner = '';
            $is_jc = $is_bd = 0;
            $odds = [];
            $bd = $this->match_dal->getLotteryByCondition(['match_num' => $v['match_num'], 'lottery_type' => 2]);
            if ($bd) {
                $is_bd = 1;
                foreach ($bd as $b) {
                    $lottery_data = explode('-', $b['lottery_num']);

                    $odds['lottery_num'] = '北单' . sprintf('%03d', $lottery_data[1]);
                    if ($b['d'] == '-') {
                        $odds['h'] = $b['h'];
                        $odds['wdl'] = $b['w'] . '/' . $b['l'];
                    }
                }
            }
            $jc = $this->match_dal->getLotteryByCondition(['match_num' => $v['match_num'], 'lottery_type' => 1]);
            if ($jc) {
                $is_jc = 1;
                foreach ($jc as $j) {
                    $odds['lottery_num'] = $j['lottery_num'];
                    if ($j['h'] == 0) {
                        $odds['h'] = 0;
                        $odds['wdl'] = $j['w'] . '/' . $j['d'] . '/' . $j['l'];
                    }
                }
            }
            $data[$k]['is_jc'] = $is_jc;
            $data[$k]['is_bd'] = $is_bd;
            if ($is_jc || $is_bd) {
                $data[$k]['odds'] = $odds;
            }
            if ($v['note'] && strpos($v['note'], '角球数') !== false) {
                $s1 = strpos($v['note'], '(');
                !empty($s1) && $s1++;
                $e1 = strpos($v['note'], ')');
                $l1 = 0;
                $e1 > $s1 && $l1 = $e1 - $s1;
                $j1 = substr($v['note'], $s1, $l1);
                $s2 = strrpos($v['note'], '(');
                !empty($s2) && $s2++;
                $e2 = strrpos($v['note'], ')');
                $l2 = 0;
                $e2 > $s2 && $l2 = $e2 - $s2;
                $j2 = substr($v['note'], $s2, $l2);
                if (is_numeric($j1) && is_numeric($j2)) {
                        $corner = $j1 . '-' . $j2;
                }
            }
            $data[$k]['corner'] = $corner;
            if ($attents == 'hot') {
                $data[$k]['type'] = 1;
            }
        }
	if ($attents == 'hot') {
		return $data;
	}
        return ['data' => $data, 'total' => $total];
    }

    public function whetherHadCase($match_num,$platform=1) {

        return $this->match_dal->getCountMatchCases($match_num,$platform);
    }

    public function importMatch($datas) {
        $dal_smatch = new DALSoccerMatch($this->_appSetting);
        foreach ($datas as $data) {
	          $formatData = [];
	          $formatData['match_num'] = $data['Id'][0];
	          $formatData['league_num'] = $data['Id'][1];
	          $formatData['host_team'] = $data['Id'][2];
	          $formatData['guest_team'] = $data['Id'][3];
	          $formatData['n'] = $data['N'] ?: 0;
	          $formatData['rank'] = $data['Rank'][0] && $data['Rank'][1] ? $data['Rank'][0] . ',' . $data['Rank'][1] : '';
	          $formatData['score'] = $data['Score'] ? $data['Score'][0] . '-' . $data['Score'][1] : '';
	          $formatData['red_card'] = $data['RedCard'] ? $data['RedCard'][0] . '-' .  $data['RedCard'][1] : '';
	          $formatData['yellow_card'] = $data['YellowCard'] ? $data['YellowCard'][0] . '-' .  $data['YellowCard'][1] : '';
	          $formatData['half'] = $data['Half'] ?: '';
	          $formatData['note'] = $data['Note'] ?: '';
	          $formatData['score_all'] = $data['ScoreAll'] ? $data['ScoreAll'][0] . '-' . $data['ScoreAll'][1] : '';
	          $formatData['score_point'] = $data['ScorePoint'] ? $data['ScorePoint'][0] . '-' .$data['ScorePoint'][1] : '';
	          $formatData['weather'] = $data['Weather'] ?: '';
	          $formatData['handicap'] = $data['Handicap'] ?: '';
	          $formatData['date'] = date('Y-m-d H:i:s', bcdiv($data['Date'], 1000));
              if ($data['Competition']) {
                  $cdatas[$data['Competition']['Id']] = $data['Competition'];
                  $this->importLeague($cdatas);
              }
              $info = $dal_smatch->existsMatch($data['Id'][0]);
	          if ($info) {
                    if (in_array($info['status'], [0, 5, 6, 13, 14, 15])) {
	                  //$formatData['Handicap'] = $data['Handicap'] ?: '';
                      $dal_smatch->updateMatch($formatData, $data['Id'][0]);
                    }
		            continue;
	          }
	          $res = $dal_smatch->addMatch($formatData);
	      }
	      return true;
    }

    public function importLeague($datas) {
	      $dal_smatch = new DALSoccerMatch($this->_appSetting);
	      $pinyin = new PinYin();
	      foreach ($datas as $key => $data) {
	          $formatData = [];
	          $formatData['league_num'] = $key;
	          $formatData['name'] = addslashes($data['Name']);
	          $formatData['short_name'] = addslashes($data['ShortName']);
	          $formatData['color'] = '#' . $data['Color'];
	          $formatData['type'] = 1;
	          $formatData['initial'] = $pinyin->getFirstChar($data['ShortName']) ?: $pinyin->getFirstChar($data['Name']);
	          if ($dal_smatch->existsLeague($key)) {
                  $dal_smatch->updateLeague($formatData,$key);
		            continue;
	          }
	          $dal_smatch->addLeague($formatData);
	      }
	      return true;
    }

    public function importTeam($datas) {
	      $dal_smatch = new DALSoccerMatch($this->_appSetting);
	      foreach ($datas as $key => $data) {
	          if ($dal_smatch->existsTeam($key)) {
	      	      continue;
	          }
	          $formatData = [];
	          $formatData['team_num'] = $key;
	          $formatData['name'] = addslashes($data['Name']);
	          $formatData['short_name'] = addslashes($data['ShortName']);
	          $formatData['type'] = 1;
	          $dal_smatch->addTeam($formatData);
	      }
	      return true;
    }

    public function importToday() {
        $date1 = date('Y-m-d', time() - 86400);
        //$date1 = date('Y-m-d', time() - 86400 * 5);
        $date2 = date('Y-m-d', time());
        //$date2 = date('Y-m-d', time() - 86400 * 4);
        $date3 = date('Y-m-d', time() + 86400);
        //$date3 = date('Y-m-d', time() - 86400 * 3);
        $date4 = date('Y-m-d', time() + 86400 * 2);
        $date5 = date('Y-m-d', time() + 86400 * 3);
        //$date4 = date('Y-m-d', time() - 86400 * 2);
        foreach ([$date1, $date2, $date3, $date4, $date5] as $date) {
          $url = $this->match_list . $date;
          echo $url;
          $result = $this->ask($url);
          $this->importMatch($result['Schedule']);
          $this->importLeague($result['Competition']);
          $this->importTeam($result['Team']);
          $this->importJcMatch($date);
          $this->importDcMatch('sfc');
          $this->importDcMatch();
        }
        return;
    }

    public function importMatchLottery() {
        $date0 = date('Y-m-d', time() - 86400);
        $date1 = date('Y-m-d', time());
        $date2 = date('Y-m-d', time() + 86400);
        $date3 = date('Y-m-d', time() + 86400 * 2);
        foreach ([$date0, $date1, $date2, $date3] as $date) {
            $url = $this->jc_match_lottery . $date;
            $result = $this->ask($url);
            if (!$result['LotteryS']) {
                continue;
            }
            var_dump($url);
            $lotterys = $result['LotteryS'];
            foreach ($lotterys as $item) {
                $this->dealJcLottery($item);
            }
        }
        $year = date('Y', time());
        $bd_times_url = $this->dc_time . $year;
        $bd_times = $this->ask($bd_times_url);
        if ($bd_times) {
            $cur_times = $bd_times['CurDegree'];
            $bd_lottery_url = sprintf($this->dc_match_lottery, $cur_times);
            var_dump($bd_lottery_url);
            $bd_result = $this->ask($bd_lottery_url);
            if ($bd_result['LotteryS']) {
                $bd_lotterys = $bd_result['LotteryS'];
                foreach ($bd_lotterys as $item) {
                    $this->dealJcLottery($item, $cur_times);
                }
            }
        }
    }


    public function matchAnalyze($match_num) {
        $match_data = $this->match_dal->getMatchInfoByNum($match_num);
        $detail_data = $this->match_dal->getMatchDetailByNum($match_num);
        $hurts = $time_score = $rank = $meeting = $recent = $later = [];
        //进球时间分布
        $time_score = $this->getGoalTime($match_num);
        //伤停
        $hurts['host'] = $this->dealFormationPlayer($detail_data['host_hurt_player']) ?: [];
        $hurts['guest'] = $this->dealFormationPlayer($detail_data['guest_hurt_player']) ?: [];
        //排名
        $rank['host'] = $this->dealTeamRank($match_data['host_team']) ?: (object)[];
        $rank['guest'] = $this->dealTeamRank($match_data['guest_team']) ?: (object)[];
        ////历史交锋
        $meeting['normal'] = $this->dealRecentMatch($detail_data['meeting_match'], $match_data['host_team']);
        $samehgNum = $this->dealSameHostGuestMatchNum($detail_data['meeting_match'], $match_data['host_team'], $match_data['guest_team']) ?: [];
        $meeting['same_hg'] = $this->dealRecentMatch($samehgNum, $match_data['host_team']); 
        ////近期战绩
        $recent['host']['normal'] = $this->dealRecentMatch($detail_data['host_history'], $match_data['host_team']);
        $same_league_num_host = $this->dealSameLeagueMatchNum($detail_data['host_history'], $match_data['league_num']) ?: [] ;
        $recent['host']['same_league'] = $this->dealRecentMatch($same_league_num_host, $match_data['host_team']);
        $samehg_host = $this->dealSameHostGuestMatchNum($detail_data['host_history'], $match_data['host_team']) ?: [];
        $recent['host']['same_hg'] = $this->dealRecentMatch($samehg_host, $match_data['host_team']);
        $recent['guest']['normal'] = $this->dealRecentMatch($detail_data['guest_history'], $match_data['guest_team']);
        $same_league_num_guest = $this->dealSameLeagueMatchNum($detail_data['guest_history'], $match_data['league_num']) ?: [];
        $recent['guest']['same_league'] = $this->dealRecentMatch($same_league_num_host, $match_data['guest_team']);
        $samehgNum_guest = $this->dealSameHostGuestMatchNum($detail_data['guest_history'], $match_data['guest_team']) ?: [];
        $recent['guest']['same_hg'] = $this->dealRecentMatch($samehgNum_guest, $match_data['guest_team']);
        //未来赛程
        $later['host'] = $this->dealRecentMatch($detail_data['host_later'], 'later')  ?: [];
        $later['guest'] = $this->dealRecentMatch($detail_data['guest_later'], 'later')  ?: [];
        //竞彩初盘
        //暂时注释
        //$jc_indexs = $this->getJcFirstIndexs($match_num);
        $result['time_score'] = $time_score  ?: (object)[];
        $result['hurts'] = $hurts  ?: (object)[];
        $result['rank'] = $rank  ?: (object)[];
        $result['meeting'] = $meeting ?: (object)[];
        $result['recent'] = $recent  ?: (object)[];
        $result['later'] = $later  ?: (object)[];
        //$result['jc_indexs'] = $jc_indexs  ?: [];
        $result['jc_indexs'] = [];
        return $result;
    }

    private function getJcFirstIndexs($match_num) {
        $condition['match_num'] = $match_num;
        $condition['first'] = [' != ', "''"];
        $condition['lottery_type'] = 1;
        $original_data = $this->match_dal->getLotteryByCondition($condition);
        $result = [];
        foreach ($original_data as $item) {
            $tmp = [];
            $tmp['h'] = $item['h'];
            if ($tmp['h'] == '-') {
                continue;
            }
            $first = json_decode($item['first'], 1);
            $tmp['w'] = $first['w'];
            $tmp['d'] = $first['d'];
            $tmp['l'] = $first['l'];
            if ($tmp['h'] == 0) {
                $result[0] = $tmp;
            } else {
                $result[1] = $tmp;
            }
        }
        ksort($result);
        $result = array_values($result);
        return $result;
    }

    private function getGoalTime($match_nums) {
        $url = $this->goal_time . $match_nums;
        $result = $this->ask($url);
        $res['host'] = [$result['home_goaltime_25'], $result['home_goaltime_45'], $result['home_goaltime_70'], $result['home_goaltime_90']];
        $res['host_total'] = array_sum($res['host']);
        $res['guest'] = [$result['away_goaltime_25'], $result['away_goaltime_45'], $result['away_goaltime_70'], $result['away_goaltime_90']];
        $res['guest_total'] = array_sum($res['guest']);
        return $res;
    }

    private function dealTeamRank($team_num) {
        $team_info = $this->match_dal->getTeamInfoByNum($team_num);
        $result['name'] = $team_info['name'];
        $result['matchs'] = $team_info['matchs'];
        $result['wdl'] = $team_info['wins'] . '/' . $team_info['draws'] . '/' . $team_info['loses'];
        $result['gfg'] = $team_info['goal'] . '/' . $team_info['fumble'] . '/' . $team_info['gd'];
        $result['total_score'] = $team_info['total_score'];
        $result['win_rate'] = $team_info['rank'];

        return $result;
    } 

    private function dealRecentMatch($matchs, $host_num = 0) {
	$return = ['sentence' => (object)[], 'result' => []];
        if (empty($matchs) && $host_num != 'later') {
            return $return;
        }
	if (empty($matchs)) {
		return [];
	}
        if (is_string($matchs)) {
            $matchs = explode(',', $matchs);
        }
        foreach ($matchs as $m) {
            $info = $this->match_dal->getMatchInfoByNum($m);
            $match_info = $this->assembleMatchResult([$info]);
            $match_info = $match_info['data'][0];
            $tmp = [];
            $tmp['is_host'] = 1;
            $tmp['date'] = $match_info['date'];
            $tmp['league_short_name'] = $match_info['league_short_name'];
            $tmp['host_team_name'] = $match_info['host_team_name'];
            $tmp['guest_team_name'] = $match_info['guest_team_name'];
            $tmp['score_all'] = $match_info['score_all'];
            $tmp['half'] = $match_info['half'];
            $tmp['wdl'] = $this->dealWinLose($host_num, $match_info);
            if ($host_num != $info['host_team']) {
                $tmp['is_host'] = 0;
            }
            $tmp['pan_wdl'] = '';
            $handicap = $info['handicap'] * -1;
            $tmp['handicap'] = $handicap;
                if ($handicap) {
                    $scores = explode('-', $tmp['score_all']);
                    if ($tmp['is_host']) {
                        $big = $scores[0];
                        $small = $scores[1] + $handicap;
                    } else {
                        $big = $scores[1] + $handicap;
                        $small = $scores[0];
                    }
                    //$tmp['scores'] = $scores;
                    //$tmp['big'] = $big;
                    //$tmp['small'] = $small;
                    if ($big > $small) {
                        $tmp['pan_wdl'] = 'w';
                    } elseif ($big == $small) {
                        $tmp['pan_wdl'] = 'd';
                    } else {
                        $tmp['pan_wdl'] = 'l';
                    }
                }
            $tmps = $tmp;
            $tmps['host_team'] = $info['host_team'];
            $tmps['guest_team'] = $info['guest_team'];
            $tmps['match_num'] = $m;
	    if ($host_num == 'later') {
	        $later_time = strtotime($tmp['date']);
	        $cha = $later_time - time();
	        $tian = ceil(bcdiv($cha, 86400, 1));
	        $tmp['tian'] = $tian;
	    }
            $result[] = $tmp;
            $results[] = $tmps;
        }
        $sentence = $this->dealSentence($results, $host_num);
	if ($host_num != 'later') {
        	$return['sentence'] = $sentence  ?: (object)[];
        	$return['result'] = $result  ?: [];
	} else {
		return $result;
	}
        return $return;
    }

    private function dealSentence ($data, $host_num = 0) {
        $sentence = [];
        if ($host_num) {
            //$format = '%s 共%s场，胜%s平%s负%s  胜率%s 赢盘率%s 大球率%s';
            $format = '%s 共%s场，胜%s平%s负%s  胜率%s ';
            $hostInfo = $this->match_dal->getTeamInfoByNum($host_num);
            $host_team_name = $hostInfo['name'];
            $count = count($data);
            $w = $d = $l = $pan_w = $pan_a = 0;
            foreach ($data as $v) {
                if ($v['wdl'] == 'w') {
                    $w++;
                } elseif ($v['wdl'] == 'l') {
                    $l++;
                } else {
                    $d++;
                }
                $pan_wdl = $v['pan_wdl'];
                if ($pan_wdl) {
                    if ($pan_wdl == 'w') {
                        $pan_w += 1;
                    }
                    $pan_a += 1;
                }
            }
            $rate = bcdiv($w, $count, 2) * 100 . '%';
            $pan_rate = 0;
            if ($pan_a > 0) {
                $pan_rate = bcdiv($pan_w * 100, $pan_a, 2) . '%';
            }
            $sentence = ['team_name' => $host_team_name, 'count' => $count, 'w' => $w, 'd' => $d, 'l' => $l, 'win_rate' => $rate, 'pan_rate' => $pan_rate, 'qiu_rate' => 0,];
        }
        return $sentence;
    }

    public function dealWinLose($host_num, $data) {
        if ($data['score_all']) {
            $score1 = substr($data['score_all'], 0, 1);
            $score2 = substr($data['score_all'], 2, 1);
            if ($score1 > $score2) {
                if ($host_num == $data['host_team']) {
                    return 'w';
                } else {
                    return 'l';
                }
            } elseif ($score1 == $score2) {
                return 'd';
            } else {
                if ($host_num == $data['host_team']) {
                    return 'l';
                } else {
                    return 'w';
                }
            }
        } else {
            return 'd';
        }
    }

    private function dealSameHostGuestMatchNum($match_nums, $host_num, $guest_num = 0) {
        if (is_string($match_nums)) {
            $match_nums = explode(',', $match_nums);
        }
        $result = [];
        foreach ($match_nums as $m) {
            $info = $this->match_dal->getMatchInfoByNum($m);
            if ($guest_num) {
                if ($info['host_team'] == $host_num && $info['guest_team'] == $guest_num) {
                    $result[] = $m;
                }
            } else {
                if ($info['host_team'] == $host_num) {
                    $result[] = $m;
                }
            }
        }
        return $result;
    }


    private function dealSameLeagueMatchNum($match_nums, $league_num) {
        if (is_string($match_nums)) {
            $match_nums = explode(',', $match_nums);
        }
        foreach ($match_nums as $m) {
            $info = $this->match_dal->getMatchInfoByNum($m);
            if ($info['league_num'] == $league_num) {
                $result[] = $m;
            }
        }
        return $result;
    }

    public function getLotteryLeague ($date1, $date2, $lottery_type) {
        if (in_array($lottery_type, [2, 4])) {
            if($lottery_type == 2) {
                $lottery_type = 1;
            } elseif ($lottery_type == 4) {
                $lottery_type = 2;
            }
        } else {
            $league_num = $this->match_dal->getLeagueCountMatch($date1, $date2);
            $league_nums = array_keys($league_num);
            $result = [];
            foreach ($league_nums as $item) {
                $tmp = [];
                $tmp['league_num'] = $item;
                $league_info = $this->match_dal->getLeagueInfoByNum($item);
                $tmp['league_short_name'] = $league_info['short_name'];
                $sort[] = $league_info['initial'];
                $result[] = $tmp;
            }
            array_multisort($sort, SORT_ASC, $result);
            return $result;
        }
        $condition['lottery_type'] = $lottery_type;
        return $this->match_dal->getLotteryLeague($date1, $date2, $condition);
    }

    private function dealJcLottery($data, $degree = 0) {
        if ($degree && empty($data['HHDA'])) {
            return true;
        }
        if (empty($data['HHDA'])) {
            $data['HHDA'] = $data['HDA'];
            $data['HHDA']['H'] = '-';
        }
        if (empty($data['HDA'])) {
            $data['HDA'] = $data['HHDA'];
            $data['HDA']['H'] = '-';
        }
        foreach ([$data['HHDA'], $data['HDA']] as $item) {
            if ($item) {
                $format_data = [];
                $format_data['match_num'] = $data['matchId'];
                $format_data['date'] = $data['starttime'];
                if (empty($data['num'])) {
                    continue;
                }
                $format_data['lottery_num'] = $data['num'];
                $format_data['lottery_type'] = 1;
                if ($degree) {
                    $format_data['lottery_num'] = $degree . '-' . $data['HHDA']['num'] ?: $data['num'];
                    $format_data['lottery_type'] = 2;
                }
                $format_data['h'] = $item['H'] ?: 0;
                $exists_condition = ['match_num' => $format_data['match_num'], 'lottery_num' => $format_data['lottery_num'], 'lottery_type' => $format_data['lottery_type']];
                $exists_condition['h'] = $format_data['h'];
                $exists_info = $this->match_dal->existsLottery($exists_condition);
                $format_data['w'] = sprintf("%01.2f",$item['W']);
                $format_data['l'] = sprintf("%01.2f",$item['L']);
                if (!$degree) {
                    $format_data['d'] = sprintf("%01.2f",$item['D']);;
                    $format_data['is_signle'] = $item['single'];
                } else {
                    if(!isset($item['D'])) {
                        $format_data['d'] = '-';
                    } else {
                        $format_data['d'] = sprintf("%01.2f",$item['D']);;
                    }
                }
                $utime = date('Y-m-d H:i:s', bcdiv($item['ut'], 1000));
                $format_data['utime'] = $utime;
                if (!$exists_info) {
                    $match_info = $this->match_dal->getMatchInfoByNum($format_data['match_num']);
                    if ($match_info) {
                        $leagueInfo = $this->match_dal->getLeagueInfoByNum($match_info['league_num']);
                        $hostInfo = $this->match_dal->getTeamInfoByNum($match_info['host_team']);
                        $guestInfo = $this->match_dal->getTeamInfoByNum($match_info['guest_team']);
                        $format_data['league_short_name'] = $leagueInfo['short_name'] ?: '';
                        $format_data['league_name'] = $leagueInfo['name'] ?: '';
                        $format_data['host_team_name'] = $hostInfo['name'] ?: '';
                        $format_data['guest_team_name'] = $guestInfo['name'] ?: '';
                        $format_data['league_num'] = $match_info['league_num'];
                        $format_data['host_team'] = $match_info['host_team'];
                        $format_data['guest_team'] = $match_info['guest_team'];
                        $this->match_dal->addLottery($format_data);
                    }
                } else {
                    $this->updateFirstJc($exists_info);
                    if ($exists_info['utime'] < $utime) {
                        $this->match_dal->updateLottery($format_data, $exists_info['id']);
                    }
                }
            }
        }
        return;
    }

    private function updateFirstJc($exists_info) {
        if($exists_info['first'] || $exists_info['lottery_type'] != 1 || $exists_info['h'] == '-') {
            return true;
        }
        $match_num = $exists_info['match_num'];
        $url = $this->jc_match_history . $match_num;
        var_dump($url);
        $jc_history = $this->ask($url);
        $hda = $jc_history['HDA'];
        $hhda = $jc_history['HHDA'];
        $aim = [];
        foreach ([$hda, $hhda] as $item) {
            if ($item) {
                foreach ($item as $i) {
                    if (isset($i['H']) && ($i['H'] == $exists_info['h'])) {
                        $aim = $item;       
                        break 2;
                    }
                    if (!isset($i['H']) && ($exists_info['h'] == 0)) {
                        $aim = $item;
                        break 2;
                    }
                } 
            }
        }
        $ut = 0;
        $key = 0;
        foreach ($aim as $k => $v) {
            if ($ut == 0 || $v['ut'] < $ut) {
                $ut = $v['ut'];
                $key = $k;
            }
        }
        if ($ut > 0) {
            $aim_data = $aim[$key];
            $w = $aim_data['W'];
            $d = $aim_data['D'];
            $l = $aim_data['L'];
            $ut = date('Y-m-d H:i:s', bcdiv($aim_data['ut'], 1000));
            $wdl = ['w' => $w, 'd' => $d, 'l' => $l, 'ut' => $ut];
            $format_data['first'] = addslashes(json_encode($wdl));
            $format_data['utime'] = $exists_info['utime'];
            $this->match_dal->updateLottery($format_data, $exists_info['id']);
        }
    }

    public function gainTodayMatchNum($date = '') {
	      $date0 = $date ?: date('Y-m-d H:i:s', time());
	      $date1 = date('Y-m-d H:i:s', strtotime($date0) - 3600 * 1);
	      $date2 = date('Y-m-d H:i:s', strtotime($date0) + 3600 * 12);
	      return $this->match_dal->getMatchNumByDate($date1, $date2, ['forecast' => 1]);
    }

    public function updateBaseMatchInfo($match = 0) {
            $date1 = date('Y-m-d', time());
            $date2 = date('Y-m-d', strtotime($date1) + 86400);
            if ($match) {
                $match_nums = [$match];
            } else {
                $match_nums = $this->match_dal->getMatchNumByDate($date1, $date2, ['update' => 1]);;
            }
	        foreach ($match_nums as $match_num) {
	           if (!$match_num) {
	        	    continue;
	           }
	           $url = $this->base_info . $match_num;
	           $basedata = [];
	           $basedata = $this->common->httpGet($url, []);
	           $basedata = json_decode($basedata, 1);
		   echo $url;
	           if ($basedata['error']) {
	        	      continue;
	           }
		//var_dump($basedata);
	           $this->updateMatch($basedata, $match_num);
	           if ($basedata['HomeTeam']) {
                   if ($match) {
                        if (!$this->match_dal->existsTeam($basedata['HomeTeam']['Id'])) {
                            $this->match_dal->addTeam(['team_num' => $basedata['HomeTeam']['Id'], 'type' => 1]);
                        }
                   } 
	        	      $this->updateTeam($basedata['HomeTeam'], $basedata['HomeTeam']['Id']);
	           }
             if ($basedata['AwayTeam']) {
                   if ($match) {
                       $exists_g = $this->match_dal->existsTeam($basedata['AwayTeam']['Id']);
                        if (!$exists_g) {
                            $this->match_dal->addTeam(['team_num' => $basedata['AwayTeam']['Id'], 'type' => 1]);
                        }
                   }
                  $this->updateTeam($basedata['AwayTeam'], $basedata['AwayTeam']['Id']);
             }
	        }
	        return ;
    }


    public function updateMatch($basedata, $match_num) {
           $formatData = [];
           $formatData['valid'] = $basedata['validGame'];
           $formatData['handicap'] = $basedata['Handicap'] ?: '';
           $formatData['channel'] = addslashes($basedata['Channel']) ?: '';
           $formatData['stadium'] = addslashes($basedata['Stadium']) ?: '';
           $formatData['referee'] = addslashes($basedata['Referee']) ?: '';
           $formatData['capacity'] = addslashes($basedata['Capacity']) ?: '';
           $formatData['spectator'] = addslashes($basedata['Spectator']) ?: '';
           $formatData['earlyodds'] = addslashes($basedata['EarlyOdds']) ?: '';
           $formatData['league_num'] = addslashes($basedata['Competition']['Id']) ?: '';
           $formatData['host_team'] = $basedata['HomeTeam']['Id'] ?: '';
           $formatData['guest_team'] = $basedata['AwayTeam']['Id'] ?: '';
           $formatData['note'] = addslashes($basedata['Note']) ?: '';
           $formatData['note'] = $basedata['N'] ?: '';
           $formatData['score_point'] = $basedata['ScorePoint'] ? $basedata['ScorePoint'][0] . ',' .$basedata['ScorePoint'][1] : '';
           $formatData['weather'] = addslashes($basedata['Weather']) ?: '';
           $formatData['date'] = date('Y-m-d H:i:s', bcdiv($basedata['Date'], 1000));
           if ($formatData['date'] == '0000-00-00 00:00:00' || empty($basedata['Date'])) {
               return false;
           }
           $formatData['localtime'] = $basedata['Localtime'] ? date('Y-', time()) . $basedata['Localtime'] : $formatData['date'];
	   $this->updateMatchLive($basedata, $match_num);
        return       $this->match_dal->updateMatch($formatData, $match_num);
    }

    public function updateMatchLive($basedata, $match_num) {
            if (empty($basedata) || empty($match_num)) {
                return false;
            }
            $formatData = [];
            $formatData['status'] = $this->dealMatchStatus($basedata['Status']);
            $formatData['score'] = $basedata['Score'] ? $basedata['Score'][0] . ',' . $basedata['Score'][1] : '';
            $formatData['red_card'] = $basedata['RedCard'] ? $basedata['RedCard'][0] . ',' .  $basedata['RedCard'][1] : '';
            $formatData['yellow_card'] = $basedata['YellowCard'] ? $basedata['YellowCard'][0] . ',' .  $basedata['YellowCard'][1] : '';
            $formatData['half'] = $basedata['Half'] ?: '';
            $formatData['score_all'] = $basedata['ScoreAll'] ? $basedata['ScoreAll'][0] . ',' . $basedata['ScoreAll'][1] : '';
            $formatData['note'] = addslashes($basedata['Note']) ?: '';
            return $this->match_dal->updateMatch($formatData, $match_num);
    }



    public function updateTeam($data, $team_num) {
	  $formatData = [];
	  $formatData['name'] = addslashes($data['Name']);
	  $formatData['short_name'] = addslashes($data['ShortName']);
	  $formatData['rank'] = addslashes($data['Rank']);
	  $formatData['logo'] = addslashes($data['Photo']);
	  return $this->match_dal->updateTeam($formatData, $team_num);
    }


    public function matchForecast($match_num = 0) {
	    $match_nums = $this->match_dal->getMatchNumByDate(0, 0, ['forecast' => 1]);
	    if ($match_num) {
		$match_nums = [$match_num];
		}
	    foreach ($match_nums as $match_num) {
            $exists = $this->match_dal->existsMatchDetail($match_num);
            //if ($exists['really']) {
            //    continue;
            //}
	 	    $url = $this->forecast_info . $match_num; 
	 	    $data = $this->ask($url);
		    if (!$data['Tip'] && !$data['Lineup']) {
			    continue;
		    } 
	 	    echo $url;
		    $formatData = [];
		    $formatData['match_num'] = $match_num;
            $match_info = $this->match_dal->getMatchInfoByNum($match_num);
            $host_team = $match_info['host_team'];
            $guest_team = $match_info['guest_team'];
		    $formatData['hostteam_tendency'] = addslashes($data['Tip']['HomeRecentTendency']) ?: '';
		    $formatData['guestteam_tendency'] = addslashes($data['Tip']['AwayRecentTendency']) ?: '';
		    $formatData['hostteam_oddswl'] = addslashes($data['Tip']['HomeOddsWinLose']) ?: '';
		    $formatData['guestteam_oddswl'] = addslashes($data['Tip']['AwayOddsWinLose']) ?: '';
		    $formatData['confidence'] = addslashes($data['Tip']['Confidence']) ?: '';
		    $formatData['hostteam_result_match'] = addslashes($data['Tip']['ResultsOfTheMatch']) ?: '';
		    $formatData['content'] = addslashes($data['Tip']['Content']) ?: '';
            /**更新情报**/
            $appSetting = AppSetting::newInstance(APP_ROOT);
            $prefix_url = $appSetting->getConstantSetting("timingGetMatchInformation");
            $Information['match_num']=$match_num;
            $Information['match_type']=1;
            $Information['host_team']=$host_team;
            $Information['guest_team']=$guest_team;
            $Information['confidence']=$formatData['confidence'];
            $Information['content']=$formatData['content'];
            $this->common->httpGet($prefix_url, $Information);
            /**更新情报end**/
		    if ($data['Lineup']) {
		    	$formatData['really'] = $data['Lineup']['Forecast'];
		    	$formatData['host_formation'] = $data['Lineup']['HFormation'] ?: '';
		    	$formatData['guest_formation'] = $data['Lineup']['AFormation'] ?: '';
		    	foreach ($data['Lineup']['HomePlayers'] as $hk) {
                    $this->dealPlayer($hk, $host_team);
		    		if ($hk['Status'] == 0) {
		    			$formatData['host_back_player'] ? $formatData['host_back_player'] .= ',' . $hk['Id'] : $formatData['host_back_player'] .= $hk['Id'];
		    		} elseif($hk['Status'] == 3) {	
		    			$formatData['host_first_player'] ? $formatData['host_first_player'] .= ',' . $hk['Id'] : $formatData['host_first_player'] .= $hk['Id'];
		    		} else {
		    			$formatData['host_hurt_player'] ? $formatData['host_hurt_player'] .= ',' . $hk['Id'] : $formatData['host_hurt_player'] .= $hk['Id'];
		    		} 
		    	}
                foreach ($data['Lineup']['AwayPlayers'] as $gk) {
                   $this->dealPlayer($gk, $guest_team);
                   if ($gk['Status'] == 0) {
                     $formatData['guest_back_player'] .= $formatData['guest_back_player'] ? ',' . $gk['Id'] : $gk['Id'];
                   } elseif($gk['Status'] == 3) {
                     $formatData['guest_first_player'] .= $formatData['guest_first_player'] ? ',' . $gk['Id'] : $gk['Id'];
                   } else {
                   $formatData['guest_hurt_player'] .= $formatData['guest_hurt_player'] ? ',' . $gk['Id'] : $gk['Id'];
                   }
                }
		    }
		    if ($exists) {
                    $this->match_dal->updateMatchDetail($formatData, $match_num);
		    } else {
		    	$this->match_dal->addMatchDetail($formatData);
		    }
	    }
    }


    public function dealPlayer($data, $team_num) {
        if (empty($data)) {
            return false;
        }
        $exists = $this->match_dal->existsPlayer($data['Id']);
        $format['player_num'] = $data['Id'];
        $format['name'] = $data['Name'];
        $format['shit_num'] = $data['ShitNo'];
        $format['pos'] = $data['Pos'];
        $format['team_num'] = $team_num;
        if ($exists) {
            return $this->match_dal->updatePlayer($format, $data['Id']);
        } else {
            return $this->match_dal->addPlayer($format);
        }
    }

    public function importMatchPast($match_num = 0 ) {
        //$this->match_dal->beginTrans();
        //try {
            $match_nums = $this->match_dal->getMatchNumByDate(0,0,['analyze' => 1]);
		if ($match_num) {
			///$match_nums = [['match_num' => $match_num, 'host_team' => 2416, 'guest_team' => 8673]];
		}
            if (!$match_nums) {
                return;
            }
            foreach ($match_nums as $value) {
                $match_num = $value['match_num'];
                $host_num = $value['host_team'];
                $guest_num = $value['guest_team'];
                $url = $this->past_info . $match_num;
                $data = $this->ask($url);
                echo $url;
                if ($data['error']) {
                    continue;
                }
                $this->importLeague($data['Competition']);
                $this->importTeam($data['Team']);
                $match_meetings = array_slice($data['Meeting'], 0, 10);
                $host_his = array_slice($data['TeamHistory']['Home'], 0, 10);
                $guest_his = array_slice($data['TeamHistory']['Away'], 0, 10);
                $host_later = array_slice($data['TeamFixture']['Home'], 0, 3);
                $guest_later = array_slice($data['TeamFixture']['Away'], 0, 3);
                $this->importMatch($host_his);
                $this->importMatch($guest_his);
                $this->importMatch($match_meetings);
                $formatData = [];
                foreach ($match_meetings as $mv) {
                    $formatData['meeting_match'] .= $formatData['meeting_match'] ? ',' . $mv['Id'][0] : $mv['Id'][0];
                }
                foreach ($host_his as $hv) {
                    $formatData['host_history'] .= $formatData['host_history'] ? ',' . $hv['Id'][0] : $hv['Id'][0];
                }
                foreach ($guest_his as $gv) {
                    $formatData['guest_history'] .= $formatData['guest_history'] ? ',' . $gv['Id'][0] : $gv['Id'][0];
                }
                foreach ($host_later as $hlv) {
                    $formatData['host_later'] .= $formatData['host_later'] ? ',' . $hlv['Id'][0] : $hlv['Id'][0];
                }
                foreach ($guest_later as $glv) {
                    $formatData['guest_later'] .= $formatData['guest_later'] ? ',' . $glv['Id'][0] : $glv['Id'][0];
                }
                if (!empty($formatData)) {
                    $this->dealLaterMatch($formatData['host_later']);
                    $this->dealLaterMatch($formatData['guest_later']);
                    $exists = $this->match_dal->existsMatchDetail($match_num);
                    if ($exists) {
                        $this->match_dal->updateMatchDetail($formatData, $match_num);
                    } else {
                        $formatData['match_num'] = $match_num;
                        $this->match_dal->addMatchDetail($formatData);
                    }
                }
                if ($data['Standings']) {
                    $this->dealTeamInfo($data['Standings']['Home'], $data['Competition'], $host_num);
                    $this->dealTeamInfo($data['Standings']['Away'], $data['Competition'], $guest_num);
                }
                $this->match_dal->updateMatch(['analyze' => 1], $match_num);
            }
        //} catch (\Exception $e) {
        //    var_dump($e->getMessage());
        //    $this->match_dal->rollBack();
        //}
        //$this->match_dal->commit();
        return;
    }

    public function dealLaterMatch($match_nums) {
        if (is_string($match_nums)) {
            $match_nums = explode(',', $match_nums);
        }
        $datas = [];
        foreach ($match_nums as $match_num) {
            $data = [];
            $url = '';
            $url = $this->base_info . $match_num;
            $data = $this->ask($url);
            if ($data['error']) {
                continue;
            }
            $data['Id'] = [$match_num, $data['Competition']['Id'], $data['HomeTeam']['Id'], $data['AwayTeam']['Id']];
            $data['Rank'] = [$data['HomeTeam']['Rank'], $data['AwayTeam']['Rank']];
            $data['match_num'] = $match_num;
            $datas[] = $data; 
        }
        return $this->importMatch($datas);
    }

    public function dealTeamInfo($data, $competition, $team_num) {
        $formatData = [];
        $formatData['matchs'] = $data['Total'][0];
        $formatData['wins'] = $data['Total'][1];
        $formatData['loses'] = $data['Total'][3];
        $formatData['draws'] = $data['Total'][2];
        $formatData['goal'] = $data['Total'][4];
        $formatData['fumble'] = $data['Total'][5];
        $formatData['gd'] = $data['Total'][6];
        $formatData['total_score'] = $data['Total'][7];
        $formatData['rank'] = $data['Total'][8];
        $formatData['win_rate'] = $data['Total'][9];
        foreach ($competition as $k => $v) {
            if ($v['ShortName'] == $data['Competiton']) {
                $$formatData['league_num'] = $k;
            }
        }
        return $this->match_dal->updateTeam($formatData, $team_num);
    }

    //type 1 赛事 2 球队 3 球员
    public function getPic($type, $num) {
        $url = sprintf($this->get_pic, $type, $num);
        echo $url;
        $result = $this->ask($url);
        var_dump($result);die;
    }

    public function matchIndexs($match_num, $indexs_type, $comp_num = 0) {
        if ($comp_num) {
            return $this->compMatchIndexs($match_num, $indexs_type, $comp_num);
        }
        $model_comp = [];
        if ($indexs_type == 1) {
            $model_comp = $this->ouzhiComp;
        } else {
            $model_comp = $this->yapanComp;
        }
        $data = [];
        $need_comp = array_keys($model_comp);
	foreach ($need_comp as $nv) {
		$data[$nv] = [];
	}
        $need_comp = implode(',', $need_comp);
        if ($indexs_type == 1) {
            $result = $this->match_dal->getIndexsInfoByNum($match_num, $indexs_type, $need_comp);

            foreach ($result as $v) {
                $data[$v['comp_num']]['lottery_id'] = $v['id'];
                $data[$v['comp_num']]['comp_num'] = $v['comp_num'];
                $data[$v['comp_num']]['comp_name'] = $this->ouzhiComp[$v['comp_num']];
                $tmp[$v['comp_num']][] = [$v['left_indexs'], $v['center_indexs'], $v['right_indexs']];
                $data[$v['comp_num']]['first'] = end($tmp[$v['comp_num']]);
                $data[$v['comp_num']]['now'] = $tmp[$v['comp_num']][0];
            }
        } else {
		$data = [];
            $comp_nums = $this->match_dal->getIndexsCompByNum($match_num, $indexs_type, $need_comp);
            $need_comp = array_keys($this->yapanComp);
            foreach ($need_comp as $nv) {
                $data[$nv] = [];
            }
            foreach ($comp_nums as $v) {
                if (in_array($v['comp_num'], $need_comp)) {
                    $data[$v['comp_num']]['comp_num'] = $v['comp_num'];
                    $data[$v['comp_num']]['comp_name'] = $this->yapanComp[$v['comp_num']];
                    $data[$v['comp_num']]['first'] = $this->match_dal->getIndexsByComp($match_num, $indexs_type, $v['comp_num'], 1);
                    $data[$v['comp_num']]['lottery_id'] = 0;
                    if ($data[$v['comp_num']]['first']) {
                        unset($data[$v['comp_num']]['first']['id']);
                        $data[$v['comp_num']]['first'] = array_values($data[$v['comp_num']]['first']);
                    }
                    $data[$v['comp_num']]['now'] = $this->match_dal->getIndexsByComp($match_num, $indexs_type, $v['comp_num'], 2);
                    if ($data[$v['comp_num']]['now']) {
                        $data[$v['comp_num']]['lottery_id'] = $data[$v['comp_num']]['now']['id'];
                        unset($data[$v['comp_num']]['now']['id']);
                        $data[$v['comp_num']]['now'] = array_values($data[$v['comp_num']]['now']);
                    }
                }
            }
        }
        foreach ($data as $dk => $dv) {
            if (empty($dv)) {
                unset($data[$dk]);
            }
        }
        $data = array_values($data);
        return $data;
    }

    public function compMatchIndexs($match_num, $indexs_type, $comp_num) {
        $comp_info = $this->match_dal->getIndexsCompByNum($match_num, $indexs_type);
        $model_comp = [];
        if ($indexs_type == 1) {
            $model_comp = $this->ouzhiComp;
        } else {
            $model_comp = $this->yapanComp;
        }
        $need_comp = array_keys($model_comp);
        $comp = [];
        foreach ($need_comp as $nv) {
            $comp[$nv] = [];
        }
        foreach ($comp_info as $v) {
            if (in_array($v['comp_num'], $need_comp)) {
                $v['comp_name'] = $model_comp[$v['comp_num']];
                $comp[$v['comp_num']] = $v;
            }
        }   
        foreach ($comp as $ck => $cv) {
            if (empty($cv)) {
                unset($comp[$ck]);
            }
        }
        $comp = array_values($comp);
        $indexs_data = [];
        if ($indexs_type == 1) {
            $indexs_info = $this->match_dal->getIndexsInfoByNum($match_num, $indexs_type, $comp_num);
            foreach ($indexs_info as $iv) {
                $tmp = [];
                $tmp[] = $iv['left_indexs'];
                $tmp[] = $iv['center_indexs'];
                $tmp[] = $iv['right_indexs'];
                $tmp[] = $iv['indexs_date'];
                $indexs_data[] = $tmp;
            }
        } else {
            $indexs_info = $this->match_dal->getIndexsByComp($match_num, $indexs_type, $comp_num);
            foreach ($indexs_info as $iv) {
                $tmp = [];
                $tmp[] = $iv['left_indexs'];
                $tmp[] = $iv['center_indexs'];
                $tmp[] = $iv['right_indexs'];
                $tmp[] = $iv['indexs_date'];
                $indexs_data[] = $tmp;
            }
        }
        return ['comp' => $comp, 'indexs' => $indexs_data];
    }

    public function getMatchIndexs($match_num) {
        $result = ['yapan' => [], 'daxiao' => []];
        $yapan = $this->matchIndexs($match_num, 2);
        $yapans = $rangqius = [];
        $daxiao = $this->matchIndexs($match_num, 3);
        $yapans = $this->dealIndexsForLottery($yapan);
        $daxiaos = $this->dealIndexsForLottery($daxiao);
        $result['yapan'] = $yapans; 
        $result['daxiao'] = $daxiaos; 
        return $result;
    }

    private function dealIndexsForLottery($data) {
        $return = [];
        foreach ($data as $item) {
            $tmp = [];
            $tmp['comp_num'] = $item['comp_num'];
            $tmp['comp_name'] = $item['comp_name'];
            $tmp['h'] = $item['now'][1] * -1;
            $tmp['w'] = $item['now'][0];
            $tmp['l'] = $item['now'][2];
            $tmp['lottery_type'] = 0;
            $tmp['lottery_id'] = $item['lottery_id'];
            $return[] = $tmp;
        }
        return $return;
    }

    public function importIndexs($match_num = 0) {
	    if ($match_num) {
	    	$match_nums = [$match_num];
        } else {
            $match_nums = $this->match_dal->getMatchNumByDate(0, 0, ['indexs' => 1]);
        }
        if (!$match_nums) {
            return ;
        }
        foreach ($match_nums as $match_num) {
            $this->importDaXiao($match_num);
            $this->importYaPan($match_num);
            $this->importOuZhi($match_num);
        }
        return ;
    }

    public function importDaXiao($match_num) {
        $url = $this->daxiao_match . $match_num;
        //echo $url;
        $result = $this->ask($url);
        if (!$result['OddsDatas']) {
            return [];
        }
        foreach ($result['OddsDatas'] as $v) {
            $tmp = [];
            $tmp['match_num'] = $match_num;
            $tmp['comp_num'] = $v['CId'];
            $tmp['comp_name'] = $v['Name'];
            $tmp['is_run'] = $v['IsRun'];
            $tmp['indexs_type'] = 3;
            foreach ($v['Logs'] as $item) {
                $tmp['indexs_date'] = date('Y-m-d H:i:s', bcdiv($item['Date'], 1000));
                $condition = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'], 'indexs_type' =>3, 'indexs_date' => $tmp['indexs_date']];
                $exists = $this->match_dal->existsIndexs($condition);
                if ($exists) {
                    continue;
                }
                $tmp['left_indexs'] = ++$item['Data'][0];
                $tmp['center_indexs'] = $item['Data'][2];
                $tmp['right_indexs'] = ++$item['Data'][1];
                $this->match_dal->addIndexs($tmp);
            }
        }
        return;
    }

    public function importYaPan($match_num) {
        $url = $this->yapan_match . $match_num;
        //echo $url;
        $result = $this->ask($url);
        if (!$result['OddsDatas']) {
            return [];
        }
        foreach ($result['OddsDatas'] as $v) {
            $tmp = [];
            $tmp['match_num'] = $match_num;
            $tmp['comp_num'] = $v['CId'];
            $tmp['comp_name'] = $v['Name'];
            $tmp['is_run'] = $v['IsRun'];
            $tmp['indexs_type'] = 2;
            foreach ($v['Logs'] as $item) {
                $tmp['indexs_date'] = date('Y-m-d H:i:s', bcdiv($item['Date'], 1000));
                $condition = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'], 'indexs_type' =>2, 'indexs_date' => $tmp['indexs_date']];
                $exists = $this->match_dal->existsIndexs($condition);
                if ($exists) {
                    continue;
                }
                $tmp['left_indexs'] = ++$item['Data'][0];
                $tmp['center_indexs'] = $item['Data'][2];
                $tmp['right_indexs'] = ++$item['Data'][1];
                $this->match_dal->addIndexs($tmp);
            }
        }
        return;
    }


    public function importOuZhi($match_num) {
        $this->ouZhiLog($match_num);
	return;
        $url = $this->ouzhi_match . $match_num;
        echo $url;
        $result = $this->ask($url);
        if (!$result['Datas']) {
            return [];
        }
        foreach ($result['Datas'] as $v) {
            $tmp = [];
            $tmp['match_num'] = $match_num;
            $tmp['comp_num'] = $v['Cid'];
            $tmp['comp_name'] = $this->ouzhiComp[$v['Cid']] ?: $v['Name'];
            $tmp['indexs_date'] = date('Y-m-d H:i:s', bcdiv($v['Date'], 1000));
            $condition = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'],'indexs_type' => 1, 'indexs_date' => $tmp['indexs_date']];
            $exists = $this->match_dal->existsIndexs($condition);
            if ($exists) {
                continue;
            }
            $tmp['is_first'] = 2;
            $tmp['left_indexs'] = $v['Data'][0];
            $tmp['center_indexs'] = $v['Data'][1];
            $tmp['right_indexs'] = $v['Data'][2];
            $tmp['indexs_type'] = 1;
	    if ($v['Data'][0] == $v['Data'][7] && $v['Data'][1] == $v['Data'][8] && $v['Data'][2] == $v['Data'][9]) {
            	$tmp['is_first'] = 1;
            	$this->match_dal->addIndexs($tmp);
		continue;
	    }
            $this->match_dal->addIndexs($tmp);
            $conditions = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'],'indexs_type' => 1, 'is_first' => 1];
            $exists_chu = $this->match_dal->existsIndexs($conditions);
            if ($exists_chu) {
                continue;
            }
            $tmp['is_first'] = 1;
            $tmp['left_indexs'] = $v['Data'][7];
            $tmp['center_indexs'] = $v['Data'][8];
            $tmp['right_indexs'] = $v['Data'][9];
            $condition = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'],'indexs_type' => 1, 'indexs_date' => $tmp['indexs_date'], 'is_first' => 1];
            $exists = $this->match_dal->existsIndexs($condition);
            if ($exists) {
                continue;
            }
            $this->match_dal->addIndexs($tmp);
	    //die;
        }
        return;
    }
    
    public function ouZhiLog($match_num) {
        $comp_nums = array_keys($this->ouzhiComp);
        foreach ($comp_nums as $v) {
            $url = sprintf($this->ouzhi_log, $match_num, $v);
            $data = $this->ask($url);
            if ($data['Logs']) {
                //echo $url;
                foreach ($data['Logs'] as $k => $item) {
                    $tmp['indexs_date'] = date('Y-m-d H:i:s', bcdiv($item['Date'], 1000));
                    $tmp['match_num'] = $match_num;
                    $tmp['comp_num'] = $v;
                    $tmp['comp_name'] = $this->ouzhiComp[$v];
                    $condition = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'],'indexs_type' => 1, 'indexs_date' => $tmp['indexs_date']];
                    $exists = $this->match_dal->existsIndexs($condition);
                     
                     if ($exists) {
                         continue;
                     }
                    $tmp['is_first'] = 2;
                    //$conditions = ['match_num' => $match_num, 'comp_num' => $tmp['comp_num'],'indexs_type' => 1];
                    //$exists_chu = $this->match_dal->existsIndexs($conditions);
		//    $info = $this->match_dal->getIndexsInfoByNum($match_num, 1, $tmp['comp_num']);
		//
		//    if ($info) {
		//	$end = end($info);
		//	if ($end['indexs_date'] > $tmp['indexs_date']) {
		//		$this->match_dal->updateIndexs(['is_first' => 2], ['id' => $info['id']]);
		//		$tmp['is_first'] = 1;
		//	}
		//    } else {
		//		$tmp['is_first'] = 1;
		//	}
                    $tmp['left_indexs'] = $item['Data'][0];
                    $tmp['center_indexs'] = $item['Data'][1];
                    $tmp['right_indexs'] = $item['Data'][2];
                    $tmp['indexs_type'] = 1;
                    $this->match_dal->addIndexs($tmp);
                }
            }
        }
    }

    public function getSpecialMatchNum($league_type, $date1, $date2) {
        if ($league_type == 3) {
            $redis_model = new RedisModel('match');
            $sfc_rkey = 'soccer_sfc';
            $data = $redis_model->redisGet($sfc_rkey);
            if (!$data || $data == 'null') {
                $data = $this->importDcMatch('sfc', 1);
                $redis_model->redisSet($sfc_rkey, json_encode($data), 3600 * 5);
            } else {
                $data = json_decode($data, 1);
            }
            if ($data) {
                foreach ($data as $v) {
                    if ($v['starttime'] >= $date1 && $v['starttime'] <= $date2) {
                       $match_nums[] = $v['matchId'];
                    }
                }
            }
            return $match_nums;
        }
        $lottery_type = 1;
        if ($league_type == 4) {
            $lottery_type = 2;
        }
        $condition['lottery_type'] = $lottery_type;
        $match_data = $this->match_dal->getLotteryMatch($date1, $date2, $condition, 0);
        $match_nums = $match_data['data'];
        $match_nums = array_column($match_nums, 'match_num');
        return $match_nums;
    }

    /*public function getSpecialMatchNum($league_type, $date1, $date2) {
        //var_dump($date1);var_dump($date2);
        $dates = [];
        $dates[] = date('Y-m-d', time() - 86400);
        $dates[] = date('Y-m-d', time());
        $dates[] = date('Y-m-d', time() + 86400);
        $match_nums = [];
        $data = [];
        $results = [];
        $redis_model = new RedisModel('match');
        if ($league_type == 2) {
            $jc_rkey = 'soccer_jingcai';
            $results = $redis_model->redisGet($jc_rkey);
            if ($results &&  $results != 'null') {
                $results  = json_decode($results, 1);
                //var_dump($results);
            } else {
                $results = [];
                for($i = 1; $i < 3; $i++) {
                        foreach ($dates as $v) {
                            $url = $this->jc_match . $v;
                          //var_dump($url);
                            $result = $this->ask($url);
                            if ($result['LotteryS']) {
                                //var_dump($result['LotteryS']);
                                $results = array_merge($results, $result['LotteryS']);
                            }
                        }
                }
                $redis_model->redisSet($jc_rkey, json_encode($results), 3600 * 5);
            }
            foreach ($results as $rv) {
                if ($rv['starttime'] >= $date1 && $rv['starttime'] <= $date2) {
                    $match_nums[] = $rv['matchId'];
                }
            }
        } elseif ($league_type == 3) {
            $sfc_rkey = 'soccer_sfc';
            $data = $redis_model->redisGet($sfc_rkey);
            if (!$data || $data == 'null') {
                $data = $this->importDcMatch('sfc', 1);
                $redis_model->redisSet($sfc_rkey, json_encode($data), 3600 * 5);
            } else {
                $data = json_decode($data, 1);
            }
        } elseif ($league_type == 4) {
            $dc_rkey = 'soccer_danchang';
            $data = $redis_model->redisGet($dc_rkey);
            if (!$data || $data == 'null') {
                $data = $this->importDcMatch('dc', 1);
                $redis_model->redisSet($dc_rkey, json_encode($data), 3600 * 5);
            } else {
                $data = json_decode($data, 1);
            }
        }
        if ($data) {
            foreach ($data as $v) {
                if ($v['starttime'] >= $date1 && $v['starttime'] <= $date2) {
                       $match_nums[] = $v['matchId'];
                }
            }
        }
        //var_dump($match_nums);die;
        return $match_nums;
    }
     */
    
    public function importJcMatch($date) {
        $url = $this->jc_match . $date;
        $result = $this->ask($url);
        if ($result['LotteryS']) {
            $match_nums = array_column($result['LotteryS'], 'matchId');
            $this->dealLeagueTypeByMatch($match_nums, 2);
        }
        return;
    }

    
    public function importDcMatch($type = 'dc', $data = 0) {
        $base_url1 = $this->dc_time;
        $base_url2 = $this->dc_match;
        $league_type = 4;
        if ($type == 'sfc') {
            $base_url1 = $this->sfc_time;
            $base_url2 = $this->sfc_match;
            $league_type = 3;
        }

        $year = date('Y', time());
        $dc_time_url = $base_url1 . $year;
        $times = $this->ask($dc_time_url);
        //var_dump($times);
        if (!$times['DegreeLst']) {
            return false;
        }
        $dc_times = [$times['CurDegree']];
        if ($type == 'sfc') {
            $dc_times = [$times['CurDegree'] -1, $times['CurDegree'], $times['CurDegree'] + 1];
        }
        if ($data) {
                $datas = [];
        }
        foreach ($dc_times as $dc_time) {
            $url = sprintf($base_url2, $dc_time);
        //      echo $url;
            $result = $this->ask($url);
            if (!$result['LotteryS']) {
                continue;
            }
            if ($data) {
                $datas = array_merge($datas, $result['LotteryS']);
                continue;
            }
            $match_nums = array_column($result['LotteryS'], 'matchId');
            $this->dealLeagueTypeByMatch($match_nums, $league_type);
        }
        if ($data) {
            return $datas;
        }
        return true;
    }

    public function dealLeagueTypeByMatch($match_nums, $league_type) {
        if ($league_type == 2) {
            $data = ['is_jc' => 1];
        } elseif ($league_type == 3) {
            $data = ['is_sfc' => 1];
        } elseif ($league_type == 4) {
            $data = ['is_bd' => 1];
        } 
        if (empty($match_nums)) {
            return false;
        }
        $leagues = [];
        foreach ($match_nums as $match_num) {
            $match_info = $this->match_dal->getMatchInfoByNum($match_num);
            if (!$match_info) {
                continue;
            }
            $league_num = $match_info['league_num'];
            if (in_array($league_num, $leagues)) {
                continue;
            }
            $leagues[] = $league_num;
            $this->match_dal->updateLeague($data, $league_num);
        }
    }

    public function __construct() {
        parent::__construct();
        $this->_redisModel = new RedisModel("match");
	      $this->match_dal = new DALSoccerMatch($this->_appSetting);
	      $this->common = new CommonHandler();
    }

    public function nowInfo($match_num, $user_id = 0) {
        $match_info = $this->match_dal->getMatchInfoByNum($match_num);
        if (empty($match_info)) {
            return [];
        }
        $attents = [];
        if ($user_id) {
            $attent = $this->match_dal->existsAttention($user_id, $match_num);
            if ($attent['attention']) {
                $attents = [$match_num];
            }
        }
        $match = $this->assembleMatchResult([$match_info], $attents);
        $match = $match['data'][0];
        $match['score'] = str_replace(',', '-', $match['score']);
        $score_tmp = explode('-', $match['score']);
        $match['host_score_90'] = $score_tmp[0];
        $match['guest_score_90'] = $score_tmp[1];
        unset($match['localtime']);
        return $match;
    }

    public function live($match_num) {
        $stat = $this->match_dal->getLiveStatByNum($match_num);
        $stat_data = [];
        $orders = array_keys($this->stat_map_front);
        foreach ($stat as $k => $v) {
            if (in_array($k, $orders)) {
                $key = array_search($k, $orders);
                $data = [];
                if ($v) {
                    if (strpos($v, '%')) {
                        $v = str_replace('%', '', $v);
                    }
                    $data = explode(',', $v);
                }
                if (!empty($data)) {
                	$stat_data[$key] = ['name' => $this->stat_map_front[$k], 'data' => $data];
                }
            }
        }
        ksort($stat_data);
        $events = $this->match_dal->getLiveEventByNum($match_num);
        $event_data = [];
        if (!empty($events)) {
            foreach ($events as $v) {
                $tmp = $this->dealEvent($v);
                if ($tmp) {
                    $event_data[] = $tmp;
                }
            } 
        }
        $stat_data = array_values($stat_data);
        return ['stat' => $stat_data, 'event' => $event_data];
    }

    private function dealEvent($event) {
        $sentence = '';
        if (in_array($event['event_type'], [0, 2, 3, 4, 5, 8])) {
            if (empty($event['player_name'])) {
                return false;
            }
        }
        if ($event['event_type'] == 9) {
            if (empty($event['player_name']) || empty($event['ass_player_name'])) {
                return false;
            }
        }
        $team_info = $this->match_dal->getTeamInfoByNum($event['event_team_num']);
        if (empty($team_info) || empty($team_info['name'])) {
            return false;
        }
        $sen_model = $this->event_sen_map[$event['event_type']]; 
        if ($event['event_type'] == 0) {
            if (empty($event['ass_player_name'])) {
                $sen_model = $this->event_sen_map[$event['event_type']][1];
                $sentence = sprintf($sen_model, $event['player_name'], $team_info['name']);
            } else {
                $sen_model = $this->event_sen_map[$event['event_type']][0];
                $sentence = sprintf($sen_model, $event['player_name'], $event['ass_player_name'], $team_info['name']);
            }
        }
        if (in_array($event['event_type'], [2, 3, 4, 5, 8])) {
            $sentence = sprintf($sen_model, $event['player_name'], $team_info['name']);
        }
        if ($event['event_type'] == 1) {
            $sentence = sprintf($sen_model, $team_info['name'], $team_info['name']);
        }
        if ($event['event_type'] == 9) {
            $sentence = sprintf($sen_model, $event['player_name'], $event['ass_player_name'], $team_info['name']);
        }
        $icon = $this->event_pic[$event['event_type']];
        return ['icon' => $icon, 'minute' => $event['minute'], 'sentence' => $sentence, 'score' => $event['score']];
    }

    public function importLive() {
        $live_url = $this->live_match;
        $live_result = $this->ask($this->live_match);
        if (!$live_result['LiveData']) {
            return false;
        }
        foreach ($live_result['LiveData'] as $match_num => $liv) {
            $match_info = $this->match_dal->getMatchInfoByNum($match_num);
            var_dump($match_num);
            if (empty($match_info)) {
                $this->match_dal->addMatch(['match_num' => $match_num]);
                $match_info = $this->match_dal->getMatchInfoByNum($match_num);
            }
	//$this->updateBaseMatchInfo($match_num);
            if ($match_info['status'] == 4 || empty($match_info)) {
                continue;
            }
            $this->updateMatchLive($liv, $match_num);
            $this->importLiveDetail($match_info);
        } 
        return;
    }

    private function importLiveDetail($match_info) {
        $match_num = $match_info['match_num'];
        $detail_url = $this->goal_match . $match_num;
        $detail_result = $this->ask($detail_url);
        //var_dump($detail_result);
        if ($detail_result['Goal_new']) {
            foreach($detail_result['Goal_new'] as $item) {
                $this->dealMatchEvent($item, $match_info);
            }
        }
        if ($detail_result['Stat']) {
            $this->dealMatchStat($detail_result['Stat'], $match_info);
        }
        if ($detail_result['Substitutes']) {
            foreach ($detail_result['Substitutes'] as $item) {
                $this->dealMatchEvent($item, $match_info, 'sub');
            } 
        }
    }

    private function dealMatchEvent($basedata, $match_info, $action = '') {
        $match_num = $match_info['match_num'];
        if ($action == 'sub') {
            $basedata['Event'] = 9;
            $basedata['Pid'] = $basedata['Upid'];
            $basedata['Pname'] = $basedata['Upname'];
            $basedata['AssistedPid'] = $basedata['Downid'];
            $basedata['AssistedPname'] = $basedata['Downname'];
        }
        $host_num = $match_info['host_team'];
        $guest_num = $match_info['guest_team'];
        $formatData = [];
        $formatData['match_num'] = $match_num;
        $formatData['minute'] = $basedata['Minute'] ?: 0;
        $formatData['event_team'] = $basedata['Type']; 
        $formatData['event_team_num'] = $basedata['Type'] == 1 ? $host_num : $guest_num; 
        $formatData['event_type'] = $basedata['Event']; 
        $formatData['score'] = $basedata['Score'] ?: ''; 
        $formatData['player_num'] = $basedata['Pid'] ?: 0; 
        $formatData['player_name'] = addslashes($basedata['Pname']) ?: ''; 
        $formatData['player_name_s'] = addslashes($basedata['PSname']) ?: ''; 
        $formatData['ass_player_num'] = addslashes($basedata['AssistedPid']) ?: 0; 
        $formatData['ass_player_name'] = addslashes($basedata['AssistedPname']) ?: ''; 
        $formatData['outtime'] = $basedata['outtime'] ?: 0; 
        $exists = $this->match_dal->existsEvent($match_num, $basedata['Event'], $formatData['event_team'], $formatData['minute']);
        if (!$exists) {
        	$this->match_dal->addEvent($formatData);
	} else {
		//$condition = ['match_num' => $match_num, 'event_team_num' => $formatData['event_team_num'], 'minute' => $formatData['minute']];
        $condition = ['id' => $exists['id']];
		$this->match_dal->updateEvent($formatData, $condition);
	}
    }

    private function dealMatchStat($basedata, $match_info) {
        if (empty($basedata)) {
            return false;
        }
        $match_num = $match_info['match_num'];
        $formatData = [];
        foreach ($basedata as $item) {
            if ($this->stat_map[$item['Name']]) {
                $formatData[$this->stat_map[$item['Name']]] = $item['Home'] . ',' . $item['Away'];
            }
        }
        $formatData['red'] = $match_info['red_card'];
        $exists = $this->match_dal->existsStat($match_num);
        if ($exists) {
            $this->match_dal->updateStat($formatData, $match_num);
        } else {
            $formatData['match_num'] = $match_num;
            $this->match_dal->addStat($formatData);
        }
        return true;
    }

    private function dealMatchStatus($status) {
        $ready = [0, 17];
        $more_time = [7, 8, 9];
        $over = [4, 10, 12];
        if (in_array($status, $ready)) {
            return 0;
        }
        if (in_array($status, $more_time)) {
            return 7;
        }
        if (in_array($status, $over)) {
            return 4;
        }
        if (empty($status)) {
            return 99;
        }
        return $status;
    }

    public function dealLotteryChange($old_data) {
        $lottery_id = $old_data['lottery_id'];
        $condition['id'] = $lottery_id;
        $lottery_type = $old_data['lottery_type'];
        if (empty($condition['id'])) {
            return false;
        }
        if (in_array($lottery_type, [1, 2])) {
            $new_data = $this->match_dal->existsLottery($condition);
        } else {
            $new_data = [];
            $indexs_info = $this->match_dal->getIndesxById($lottery_id);
            if (!$indexs_info) {
                return false;
            }
            $match_num = $indexs_info['match_num'];
            $comp_num = $indexs_info['comp_num'];
            $indexs_type = $indexs_info['indexs_type'];
            $indexs = $this->match_dal->getIndexsByComp($match_num, $indexs_type, $comp_num,  2);
            $new_data['h'] = $indexs['center_indexs'] * -1;
            $new_data['w'] = $indexs['left_indexs'];
            $new_data['l'] = $indexs['right_indexs'];
        }
        $tmp = [];
        $tmp['h'] = [$old_data['h'], $new_data['h']];
        $tmp['w'] = [$old_data['w'], $new_data['w']];
        if ($lottery_type == 1) {
            $tmp['d'] = [$old_data['d'], $new_data['d']];
        }
        $tmp['l'] = [$old_data['l'], $new_data['l']];
        $changed = [];
        foreach ($tmp as $k => $item) {
            if ($item[0] != $item[1]) {
                $changed[$k] = $item;
            }
        }
        if (count($changed) > 0) {
            return $changed;
        } else {
            return false;
        }
    }

    public function addAcrossLine($str) {
        $strs = '';
        for($i = 0;$i < strlen($str); $i++) {
            $strs .= $strs ? '-' . $str[$i] : $str[$i];
        }
        return $strs;
    }


    public function ask($url) {
        $data = $this->common->httpGet($url, []);
        $data = json_decode($data, 1);	
	      return $data;
    }

    public function caseList($params) {
        $match_num = $params['match_num'];
        $user_id = $params['user_id'];
        $page = $params['page'];
        $page_num = $params['page_num'];
        $is_new = 1;
        $platform = $params['platform'];
        $dalResource = new DALResource($this->_appSetting);
        $start = ($page - 1) * $page_num;
        $resourceIdList = $dalResource->getRecommendListV2($start, $page_num, $platform, $is_new, $match_num, 1);
        $betRecordModel = new BetRecordModel();
        $userFollowModel = new UserFollowModel();
        $resourceModel = new ResourceModel();
        $expertModel=new ExpertModel();
        $result = [];
        foreach ($resourceIdList as $key => $val) {
            if ($user_id) {
                $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($user_id, $val['expert_id']);
            }
            $info=$expertModel->getExpertInfo($val['expert_id']);
            $lately_red=$info['lately_red'];//近几中几
            $max_red_num=$info['max_red_num'];//连红
            $expertInfo = array(
                'expert_id' => $val['expert_id'],
                'expert_name' => $val['expert_name'],
                'real_name' => $val['real_name'],
                'headimgurl' => $val['headimgurl'],
                'phone' => $val['phone'],
                'platform' => $val['platform'],
                'tag' => empty($val['tag']) ? [] :explode(',', $val['tag']),
                'push_resource_time' => $val['push_resource_time'],
                'identity_desc' => $val['identity_desc'],
                'is_follow_expert' => $isFollowExpert ?: 0,
                'max_bet_record' => $is_new ? $val['max_bet_record_v2'] : $val['max_bet_record'],
                'create_time' => $val['create_time'],
                'combat_gains_ten' => $betRecordModel->nearTenScore($val['expert_id'], $platform),
                'lately_red'=>$lately_red,
                'max_red_num'=>$max_red_num,

            );
            if($expertInfo['max_bet_record']<60){
                $expertInfo['max_bet_record']='--';
            }
            $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
            $resourceInfo = array(
                'resource_id' => $val['resource_id'],
                'is_free' => $val['is_free'],
                'title' => $val['title'],
                'resource_type' => $val['resource_type'],
                'is_groupbuy' => $val['is_groupbuy'],
                'is_limited' => $val['is_limited'],
                'is_schedule_over' => $val['is_schedule_over'],
                'price' => $resourceModel->ncPriceFen2Yuan($val['price']),
                'price_int' => $resourceModel->ncPriceFen2YuanInt($val['price']),
                'release_time_friendly' => $resourceModel->friendlyDate($val['release_time']),
                'create_time' => $val['create_time'],
                'stat_time' => $val['create_time'],
                'limited_time_friendly' => $resourceModel->friendlyDate($val['limited_time'], 'full'),
                'create_time_friendly' => $resourceModel->friendlyDate($val['create_time']),
                'bet_status' => $resourceExtraInfo['bet_status'],
                'sold_num' => $resourceExtraInfo['sold_num'] + $resourceExtraInfo['cron_sold_num'],
                'thresh_num' => $resourceExtraInfo['thresh_num'],
                //'schedule' => $resourceModel->getResourceScheduleList($val['resource_id']),
                'expert' => $expertInfo,
                'view_num' => $resourceExtraInfo['view_num']
            );
            if ($val['is_groupbuy'] == 1) {
                  $resourceInfo['group'] = $resourceModel->getResourceGroupInfo($val['resource_id']);
            }
            $result[] = $resourceInfo;
        }
        return $result;
    }

    public function getHistory($match_num) {
        $match_data = $this->match_dal->getMatchInfoByNum($match_num);
        $detail_data = $this->match_dal->getMatchDetailByNum($match_num);
        $host_meet = $this->dealRecentMatch($detail_data['meeting_match'], $match_data['host_team']);
        $guest_meet = $this->dealRecentMatch($detail_data['meeting_match'], $match_data['guest_team']);
        if ((array)$host_meet['sentence']) {
                $history_count = $host_meet['sentence']['count'] ?: 0;
                $host_win_rate = $host_meet['sentence']['win_rate'] ?: '';
        }
        if ((array)$guest_meet['sentence']) {
                $guest_win_rate = $guest_meet['sentence']['win_rate'] ?: '';
        }
        return ['history_count' => $history_count, 'host_win_rate' => $host_win_rate, 'guest_win_rate' => $guest_win_rate];
    }

    public function hotMatch($match_num) {
        $match_info = $this->match_dal->getMatchInfoByNum($match_num);
        $update_data = ['is_hot' => 1];
        if ($match_info['is_hot']) {
            $update_data['is_hot'] = 0;
        }
        return $this->match_dal->updateMatch($update_data, $match_num);
    }

    public function hotMatchList() {
        $date1 = date('Y-m-d');
        $date2 = date('Y-m-d', strtotime($date1) + $this->matchResultTimeScope * 2);
        $result = $this->match_dal->getHotMatch($date1, $date2);
	if (empty($result)) {
		return [];
	}
        return $this->assembleMatchResult($result, 'hot');
    }

    public function nowMatchList() {
          $result = $this->ask($this->now_match);
          $this->importMatch($result['Schedule']);
          $this->importLeague($result['Competition']);
          $this->importTeam($result['Team']);
    }

    public function getUpcomingMatch($second = 300) {
        $now = date('Y-m-d H:i:s', time());
        $keytime = date('Y-m-d H:i:s', time() + $second);
        $val1 = ' between \'' . $now . '\' ';
        $val2 = ' and \'' . $keytime . '\' ';
        $condition['date'] = [$val1, $val2];
        $condition['valid'] = 1;
        $condition['status'] = 0;
        $result = $this->match_dal->getMatchByCondition($condition);
        if (!$result) {
            return [];
        }
        foreach ($result as $k => $v) {
            $hostInfo = $this->match_dal->getTeamInfoByNum($v['host_team']);
            $result[$k]['host_name'] = $hostInfo['name'] ?: '';
            $guestInfo = $this->match_dal->getTeamInfoByNum($v['guest_team']);    
            $result[$k]['guest_name'] = $guestInfo['name'] ?: '';
        }
        return $result;
    }

    public function getMatchAttentUser($match_num) {
        $user_ids = $this->match_dal->getMatchAttentUser($match_num);
        if ($user_ids) {
            return array_unique($user_ids);
        } else {
            return false;
        }
    }

    public function getLotteryInfo($match_num) {
      $lotterys = $this->match_dal->getLotteryByCondition(['match_num' => $match_num]);
      
      $result = [];
      if (empty($lotterys)) {
        return $result;
      }
      $result['match_num'] = $lotterys[0]['match_num'];
      $result['league_num'] = $lotterys[0]['league_num'];
      $result['date'] = $lotterys[0]['date'];
      $result['league_short_name'] = $lotterys[0]['league_short_name'];
      $result['host_team_name'] = $lotterys[0]['host_team_name'];
      $result['guest_team_name'] = $lotterys[0]['guest_team_name'];
      $result['is_signle'] = $lotterys[0]['is_signle'];
      $result['odds'] = [];

      $only_jc = 0;
      $lottery_type_arr = array_column($lotterys, 'lottery_type');
      if (in_array(1, $lottery_type_arr) && in_array(2, $lottery_type_arr)) {
        $only_jc = 1;
      }
      foreach ($lotterys as $item) {
        /*if ($item['lottery_type'] == 2 && $item['d'] != '-') {
          continue;
      }*/
        if ($item['h'] == '-') {
          continue;
        }
        if ($only_jc && $item['lottery_type'] == 2) {
          continue;
        }
        
        $tmp = [
          'lottery_type' => $item['lottery_type'],
          'lottery_num' => $item['lottery_num'],
          'is_signle' => $item['is_signle'],
          'h' => $item['h'],
          'w' => $item['w'],
          'd' => !empty($item['d']) ? $item['d'] : '-',
          'l' => $item['l'],
          'lottery_id' => $item['id']
        ];
        if ($item['lottery_type'] == 1) {
            if ($item['h'] == 0) {
                $result['odds'][0] = $tmp;
            } else {
                $result['odds'][1] = $tmp;
            }
        } elseif ($item['lottery_type'] == 2) {
            if ($item['d'] == '-') {
                $result['odds'][0] = $tmp;
            } else {
                $result['odds'][1] = $tmp;
            }
        }
      }
      ksort($result['odds']);
      $result['odds'] = array_values($result['odds']);
      $result['lottery_type'] = in_array(1, array_column($result['odds'], 'lottery_type')) ? 1 : 2;
      if (in_array(3, array_column($result['odds'], 'lottery_type')) || in_array(4, array_column($result['odds'], 'lottery_type'))) {
          $result['lottery_type'] = 3;
    }
      $result['lottery_num'] = $result['odds'][0]['lottery_num'];
      return $result;
      //return $this->match_dal->existsLottery(['match_num' => $match_num])
    }

    public function findLotteryById($id) {
      return $this->match_dal->findLotteryById($id);
    }
}
