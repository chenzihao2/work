<?php

namespace QK\HaoLiao\Model;

use QK\HaoLiao\DAL\DALBasketballMatch;
use QK\HaoLiao\Common\PinYin;
use QK\HaoLiao\Common\CommonHandler;
use QK\WSF\Settings\AppSetting;
class BasketballModel extends BaseModel {
  private $_redisModel;
  const DOMAIN_7M = 'feed.sportsdt.com/szyuanzhan/';
  private $match_list = self::DOMAIN_7M . 'basketball/?type=getschedulebydate&date='; //获取日期对应的赛程
  private $base_info = self::DOMAIN_7M . 'basketball/?type=getgameinfo&gameid=';  //获取比赛基本信息
  private $forecast_info = self::DOMAIN_7M . 'basketball/?type=getgameprediction&gameid=';  //获取比赛预测信息
  private $past_info = self::DOMAIN_7M . 'basketball/?type=getgameanalyse&gameid='; //获取比赛往级信息
  private $gamestat_info = self::DOMAIN_7M . 'basketball/?type=getgamestat&gameid='; //获取比赛往级信息
  
  private $live_list = self::DOMAIN_7M . 'basketball/?type=getlivegame'; //获取比赛即时列表
  private $live_info = self::DOMAIN_7M . 'basketball/?type=getlivedata'; //获取比赛即时比分数据
  private $game_stat = self::DOMAIN_7M . 'basketball/?type=getgamestat&gameid='; //获取比赛技术统计(仅限nba，wnba，cba)

  private $jc_match = self::DOMAIN_7M . 'basketball/?type=getschedule_jc&date='; //获取日期对应的竞彩赛程
  private $dc_match = self::DOMAIN_7M . 'basketball/?type=getschedule_dc&degree='; //获取日期对应的单场赛程

  private $default_team_icon = 'https://hl-static.haoliao188.com/soccer_team_qiuduito.png';

  public $match_status = [
    0 => '未',
    1 => '第一节',
    2 => '第一节完',
    3 => '第二节',
    4 => '第二节完',
    5 => '第三节',
    6 => '第三节完',
    7 => '第四节',
    8 => '第四节完',
    9 => '完',
    10 => '加时',
    11 => '完',
    12 => '中断',
    13 => '取消',
    14 => '延期',
    15 => '腰斩',
    16 => '待定'
  ];

  public $player_pos = [
    0 => '其他',
    1 => '前锋',
    2 => '大前锋',
    3 => '小前锋',
    4 => '小前锋，后卫',
    5 => '大前锋，中锋',
    6 => '中锋',
    7 => '中锋，前锋',
    8 => '后卫',
    9 => '得分后卫',
    10 => '前锋，后卫',
    11 => '控球后卫',
    12 => '控球后卫，前锋',
    13 => '得分后卫，前锋',
    14 => '得分后卫，控球后卫',
    15 => '大前锋，小前锋',
    16 => '控球后卫，大前锋',
    17 => '控球后卫，小前锋',
    18 => '得分后卫，大前锋',
    19 => '得分后卫，小前锋'
  ];
  
  public $stat_map = [
    'shot_rate' => '投篮%',
    'three_rate' => '3分球%',
    'penalty_shot' => '罚球%',
    'assists_asts' => '助攻',
    'steals_stls' => '抢断',
    'block_shots_blks' => '盖帽',
    'fouls' => '犯规',
  ];

  public $pos_map = [
    'F' => '前锋',
    'C' => '中锋',
    'G' => '后卫'
  ];

    private $play_method = [1 => '主队', 2 => '主队', 3 => '大小分'];
    private $lottery_result_map = [1 => 'w', 2 => 'd', 3 => 'l', 4 => 'w', 5 => 'l'];

    public $matchResultTimeScope = 86400;

  public function __construct() {
    parent::__construct();
    $this->_redisModel = new RedisModel("other");
    $this->match_dal = new DALBasketballMatch($this->_appSetting);
    $this->common = new CommonHandler();
  }

  public function getliveList() {
    $result = $this->ask($this->live_list);
    var_dump($result);
  }

  public function getliveInfo() {
    $result = $this->ask($this->live_info);
    $severTime = $result['SeverTime'];
    $liveData = $result['LiveData'];
    foreach($liveData as $macth_num => $value) {
	$value['severTime'] = $severTime;
    	$this->_redisModel->redisSetHashList('match:basketball:liveinfo', 'match:' . $macth_num, $value, 60);
    }
  }

  public function getGameStat() {
    $date1 = date('Y-m-d', strtotime('-1 day'));
    $date2 = date('Y-m-d', strtotime('+2 day'));
    $match_nums = $this->match_dal->getMatchNumByDate($date1, $date2);
    foreach($match_nums as $match_num) {
    	$match_stat = $this->ask($this->game_stat . $match_num);
	    if (isset($match_stat['error'])) {
	      continue;
	    }
	    if (isset($match_stat['H_Stat_Total']) && isset($match_stat['A_Stat_Total'])) {
    	  $this->upsertMatchStat($match_num, $match_stat['H_Stat_Total'], $match_stat['A_Stat_Total']);
	    }
	    if (isset($match_stat['H_Stat'])) {
    	  $this->upsertMatchPlayerStat($match_num, $match_stat['H_Stat'], 1);
	    }
	    if (isset($match_stat['A_Stat'])) {
    	  $this->upsertMatchPlayerStat($match_num, $match_stat['A_Stat'], 0);
	    }
    }
    return;
  }

  public function upsertMatchStat($match_num, $hostData, $guestData) {
    if (empty($hostData) && empty($guestData)) {
	    return;
    }
    $is_exist = $this->match_dal->existsMatchStat($match_num);
    
    $formatData = [];
    $formatData['time'] = $hostData[0] . ',' . $guestData[0];
    $formatData['shot_rate'] = $hostData[1] . ',' . $guestData[1];
    $formatData['three_rate'] = $hostData[2] . ',' . $guestData[2];
    $formatData['penalty_shot'] = $hostData[3] . ',' . $guestData[3];
    $formatData['offensive'] = $hostData[4] . ',' . $guestData[4];
    $formatData['defend'] = $hostData[5] . ',' . $guestData[5];
    $formatData['total'] = $hostData[6] . ',' . $guestData[6];
    $formatData['assists_asts'] = $hostData[7] . ',' . $guestData[7];
    $formatData['fouls'] = $hostData[8] . ',' . $guestData[8];
    $formatData['steals_stls'] = $hostData[9] . ',' . $guestData[9];
    $formatData['turn_over_tos'] = $hostData[10] . ',' . $guestData[10];
    $formatData['block_shots_blks'] = $hostData[11] . ',' . $guestData[11];
    $formatData['points_pts'] = $hostData[12] . ',' . $guestData[12];
    $formatData['quick_offense'] = $hostData[13] . ',' . $guestData[13];
    $formatData['dunk_shot'] = $hostData[14] . ',' . $guestData[14];
    if ($is_exist) {
	    $formatData['utime'] = date('Y-m-d H:i:s');
	    $this->match_dal->updateMatchStat($formatData, $match_num);
    } else {
    	$formatData['match_num'] = $match_num;
	    $formatData['ctime'] = $formatData['utime'] = date('Y-m-d H:i:s');
    	$res = $this->match_dal->addMatchStat($formatData);
    }
  }

  public function upsertMatchPlayerStat($match_num, $datas, $is_host = 1) {
    $formatData = [];
    foreach($datas as $data) {
      $is_exist = $this->match_dal->existsMatchPlayerStat($match_num, $data[1]);
      
      $formatData['player_name'] = $data[0];
      $formatData['player_num'] = $data[1];
      $formatData['pos'] = $data[2];
      $formatData['on_time'] = $data[3];
      $formatData['shot_rate'] = $data[4];
      $formatData['three_rate'] = $data[5];
      $formatData['penalty_shot'] = $data[6];
      $formatData['offensive'] = $data[7];
      $formatData['defend'] = $data[8];
      $formatData['total'] = $data[9];
      $formatData['assists_asts'] = $data[10];
      $formatData['fouls'] = $data[11];
      $formatData['steals_stls'] = $data[12];
      $formatData['turn_over_tos'] = $data[13];
      $formatData['block_shots_blks'] = $data[14];
      $formatData['points_pts'] = $data[15];
      $formatData['quick_offense'] = $data[16];
      $formatData['dunk_shot'] = $data[17];
      if ($is_exist) {
	      $formatData['utime'] = date('Y-m-d H:i:s');
	      $this->match_dal->updateMatchPlayerStat($formatData, $match_num, $data[1]);
      } else {
    	  $formatData['match_num'] = $match_num;
   	    $formatData['is_host'] = $is_host;
	      $formatData['ctime'] = $formatData['utime'] = date('Y-m-d H:i:s');
      	$res = $this->match_dal->addMatchPlayerStat($formatData);
      }
    }
  }

  public function importToday() {
    $date1 = date('Y-m-d', time() - 86400);
    $date2 = date('Y-m-d', time());
    $date3 = date('Y-m-d', time() + 86400);
    foreach ([$date1, $date2, $date3] as $date) {
      $url = $this->match_list . $date;
      $result = $this->ask($url);
      $this->importMatch(isset($result['Schedule']) ? $result['Schedule'] : []);
      $this->importLeague(isset($result['Competition']) ? $result['Competition'] : []);
      $this->importTeam(isset($result['Team']) ? $result['Team'] : []);
      $this->importJcMatch($date);
      $this->importDcMatch($date);
    }
    return;
  }

  public function importMatch($datas) {
    foreach ($datas as $data) {
      if ($this->match_dal->existsMatch($data['Id'][0])) {
        continue;
      }
      $formatData = [];
      $formatData['match_num'] = $data['Id'][0];
      $formatData['league_num'] = $data['Id'][1];
      $formatData['host_team'] = $data['Id'][2];
      $formatData['guest_team'] = $data['Id'][3];
      $formatData['n'] = $data['N'];
      $formatData['rank'] = isset($data['Rank']) ? implode('-', $data['Rank']) : '';    //['host_rank:B5', 'guest_rank:B6']
      $formatData['status'] = isset($data['Status']) ? $data['Status'] : 11;  //int(如果status字段不存在按已完赛处理)
      $formatData['ascore'] = isset($data['AScore']) ? implode(',', $data['AScore']) : '';  //主队：['总得分', '第一节得分', '第二节得分', '第三节得分', '第四节得分', '加时得分']
      $formatData['bscore'] = isset($data['BScore']) ? implode(',', $data['BScore']) : '';  //客队
      $formatData['ascore_ot'] = isset($data['AScoreOT']) ? implode(',', $data['AScoreOT']) : '';   //加时分节(未知结构)
      $formatData['bscore_ot'] = isset($data['BScoreOT']) ? implode(',', $data['BScoreOT']) : '';
      $formatData['half'] = isset($data['Half']) ? implode('-', $data['Half']) : '';  //半场得分  ['主队半场得分', '客队半场得分']
      $formatData['round_name'] = $data['RoundName']; //赛程标识（B组）
      $formatData['round'] = $data['Round'];  //轮次：0轮
      $formatData['date'] = date('Y-m-d H:i:s', bcdiv($data['Date'], 1000));
      $res = $this->match_dal->addMatch($formatData);
    }
    return true;
  }

  public function importLeague($datas) {
    $pinyin = new PinYin();
    foreach ($datas as $key => $data) {
      if ($this->match_dal->existsLeague($key)) {
        continue;
      }
      $formatData = [];
      $formatData['league_num'] = $key;
      $formatData['name'] = $data['Name'];
      $formatData['short_name'] = $data['ShortName'];
      $formatData['color'] = '#' . $data['Color'];
      $formatData['type'] = 2;    //区分是篮球还是足球
      $formatData['quarter'] = $data['Quarter'];
      $formatData['initial'] = $pinyin->getFirstChar($data['ShortName']) ?: $pinyin->getFirstChar($data['Name']);
      $this->match_dal->addLeague($formatData);
    }
    return true;
  }
  
  public function importTeam($datas) {
    foreach ($datas as $key => $data) {
      if ($this->match_dal->existsTeam($key)) {
        continue;
      }
      $formatData = [];
      $formatData['team_num'] = $key;
      $formatData['name'] = $data;
      $formatData['short_name'] = '';
      $formatData['type'] = 2;
      $this->match_dal->addTeam($formatData);
    }
    return true;
  }

  public function updateBaseMatchInfo() {
    $date1 = date('Y-m-d', strtotime('-1 day'));
    $date2 = date('Y-m-d', strtotime('+1 day'));
    $match_nums = $this->match_dal->getMatchNumByDate($date1, $date2);
    foreach ($match_nums as $match_num) {
      if (!$match_num) {
        continue;
      }
      $url = $this->base_info . $match_num;
      $basedata = [];
      $basedata = $this->common->httpGetRequest($url, []);
      $basedata = json_decode($basedata, 1);
      if (isset($basedata['error']) && !empty($basedata['error'])) {
	continue;
      }
      $this->updateMatch($basedata, $match_num);
      if ($basedata['HomeTeam']) {
        $this->updateTeam($basedata['HomeTeam'], $basedata['HomeTeam']['Id']);
      }
      if ($basedata['AwayTeam']) {
        $this->updateTeam($basedata['AwayTeam'], $basedata['AwayTeam']['Id']);
      }
    }
    return ;
  }

  public function updateExceptionMatch() {
    $match_nums = $this->match_dal->getList(['date' => '0000-00-00 00:00:00'], ['match_num'], 1, 0);
    $match_nums = array_column($match_nums, 'match_num');

    $noleague_matches = $this->match_dal->getList(['league_num' => 0], ['match_num'], 1, 0);
    $noleague_matches = array_column($noleague_matches, 'match_num');
    
    $match_nums = array_merge($match_nums, $noleague_matches);

    foreach ($match_nums as $match_num) {
      if (!$match_num) {
        continue;
      }
      $url = $this->base_info . $match_num;
      $basedata = [];
      $basedata = $this->common->httpGetRequest($url, []);
      $basedata = json_decode($basedata, 1);
      if (isset($basedata['error']) && !empty($basedata['error'])) {
        continue;
      }
      if ($basedata['HomeTeam']) {
        $basedata['host_team'] = $basedata['HomeTeam']['Id'];
        $this->updateTeam($basedata['HomeTeam'], $basedata['HomeTeam']['Id']);
      }
      if ($basedata['AwayTeam']) {
        $basedata['guest_team'] = $basedata['AwayTeam']['Id'];
        $this->updateTeam($basedata['AwayTeam'], $basedata['AwayTeam']['Id']);
      }
      if (isset($basedata['Competition'])) {
        $basedata['league_num'] = $basedata['Competition']['Id'];
      }
      $this->updateMatch($basedata, $match_num);
    }
    return ;
  }

  public function updateMatch($basedata, $match_num) {
    if (empty($basedata)) {
	    return null;
    }
    $formatData = [];
    if (isset($basedata['AScore'])) {
    	$formatData['ascore'] = !empty($basedata['AScore']) ? implode(',', $basedata['AScore']) : '';
    }
    if (isset($basedata['BScore'])) {
    	$formatData['bscore'] = !empty($basedata['BScore']) ? implode(',', $basedata['BScore']) : '';
    }
    if (isset($basedata['AScoreOT'])) {
    	$formatData['ascore_ot'] = !empty($basedata['AScoreOT']) ? implode(',', $basedata['AScoreOT']) : '';
    }
    if (isset($basedata['BScoreOT'])) {
    	$formatData['bscore_ot'] = !empty($basedata['BScoreOT']) ? implode(',', $basedata['BScoreOT']) : '';
    }
    if (isset($basedata['Handicap'])) {
	    $formatData['handicap'] = $basedata['Handicap'];
    }
    if (isset($basedata['AOdds'])) {
	    $formatData['aodds'] = $basedata['AOdds'];
    }
    if (isset($basedata['BOdds'])) {
	    $formatData['bodds'] = $basedata['BOdds'];
    }
    if (isset($basedata['Channel'])) {
	    $formatData['channel'] = $basedata['Channel'];
    }
    if (isset($basedata['City'])) {
    	$formatData['city'] = $basedata['City'];
    }
    if (isset($basedata['RoundName'])) {
    	$formatData['round_name'] = $basedata['RoundName'];
    }
    if (isset($basedata['Round'])) {
    	$formatData['round'] = $basedata['Round'];
    }
    if (isset($basedata['Referee'])) {
    	$formatData['referee'] = $basedata['Referee'] ?: '';
    }
    if (isset($basedata['N'])) {
    	$formatData['n'] = $basedata['N'];
    }
    if (isset($basedata['Status'])) {
    	$formatData['status'] = $basedata['Status'];
    }
    if (isset($basedata['Date'])) {
    	$formatData['date'] = date('Y-m-d H:i:s', bcdiv($basedata['Date'], 1000));
    }
    if (isset($basedata['host_team'])) {
      $formatData['host_team'] = $basedata['host_team'];
    }
    if (isset($basedata['guest_team'])) {
      $formatData['guest_team'] = $basedata['guest_team'];
    }
    if (isset($basedata['league_num'])) {
      $formatData['league_num'] = $basedata['league_num'];
    }
    return $this->match_dal->updateMatch($formatData, $match_num);
  }

  public function updateTeam($data, $team_num) {
    $formatData = [];
    $formatData['name'] = $data['Name'];
    $formatData['short_name'] = $data['ShortName'];
    $formatData['rank'] = $data['Rank'];
    if (isset($data['Photo'])) {
    	$formatData['logo'] = $data['Photo'];
    }
    return $this->match_dal->updateTeam($formatData, $team_num);
  }

  public function matchForecast() {
    $date0 = $date ?: date('Y-m-d H:i:s', time());
    $date1 = date('Y-m-d H:i:s', strtotime($date0) - 3600 * 2);
    $date2 = date('Y-m-d H:i:s', strtotime($date0) + 3600 * 12);
    $match_nums = $this->match_dal->getMatchNumByDate($date1, $date2);
    foreach ($match_nums as $match_num) {
      $url = $this->forecast_info . $match_num;
      $data = $this->ask($url);
      if (!$data['Tip'] && !$data['Lineup']) {
        continue;
      }
      
      $formatData = [];
      $formatData['match_num'] = $match_num;
      $match_info = $this->match_dal->getMatchInfo($match_num);
      $host_team = $match_info['host_team'];
      $guest_team = $match_info['guest_team'];
      $formatData['hostteam_tendency'] = $data['Tip']['HomeRecentTendency'] ?: '';
      $formatData['guestteam_tendency'] = $data['Tip']['AwayRecentTendency'] ?: '';
      $formatData['hostteam_oddswl'] = $data['Tip']['HomeOddsWinLose'] ?: '';
      $formatData['guestteam_oddswl'] = $data['Tip']['AwayOddsWinLose'] ?: '';
      $formatData['confidence'] = $data['Tip']['Confidence'] ?: '';
      $formatData['hostteam_result_match'] = $data['Tip']['ResultsOfTheMatch'] ?: '';
      $formatData['content'] = $data['Tip']['Content'] ?: '';
       /**更新情报**/
      $appSetting = AppSetting::newInstance(APP_ROOT);
      $prefix_url = $appSetting->getConstantSetting("timingGetMatchInformation");
      $Information['match_num']=$match_num;
      $Information['match_type']=2;
      $Information['host_team']=$host_team;
      $Information['guest_team']=$guest_team;
      $Information['confidence']=$formatData['confidence'];
      $Information['content']=$formatData['content'];
      $this->common->httpGet($prefix_url, $Information);
      /**更新情报end**/
      if ($data['Lineup']) {
	$host_first_players = $host_back_players = $guest_first_players = $guest_back_players = [];
	foreach($data['Lineup']['HomePlayers'] as $hp) {
	  if ($hp['Selected'] == 1) {	//主力
		$host_first_players[] = $hp['Id'];
	  } else {
		$host_back_players[] = $hp['Id'];
	  }
	  $this->addPlayer($hp, $host_team);
	}
	foreach($data['Lineup']['AwayPlayers'] as $ap) {
          if ($hp['Selected'] == 1) {   //主力
                $guest_first_players[] = $ap['Id'];
          } else {
                $guest_back_players[] = $ap['Id'];
          }
	  $this->addPlayer($ap, $guest_team);
        }
      	$formatData['host_first_players'] = implode(',', $host_first_players);
      	$formatData['guest_first_players'] = implode(',', $guest_first_players);
      	$formatData['host_back_players'] = implode(',', $host_back_players);
      	$formatData['guest_back_players'] = implode(',', $guest_back_players);
      }

      if ($data['InjurePlayer']) {
        $formatData['host_injure_players'] = implode(',', array_column($data['InjurePlayer']['HomePlayers'], 'Id'));
        $formatData['guest_injure_players'] = implode(',', array_column($data['InjurePlayer']['AwayPlayers'], 'Id'));
      }

      $exists = $this->match_dal->existsMatchDetail($match_num);
      if ($exists) {
        $this->match_dal->updateMatchDetail($formatData, $match_num);
      } else {
        $this->match_dal->addMatchDetail($formatData);
      }
    }
  }

  public function addPlayer($data, $team_num) {
    if (empty($data)) {
      return false;
    }
    $exists = $this->match_dal->existsPlayer($data['Id']);
    if ($exists) {
      return;
    }
    $format['player_num'] = $data['Id'];
    $format['name'] = $data['Name'];
    $format['shit_num'] = $data['ShitNo'];
    $format['pos'] = $data['Pos'];
    $format['team_num'] = $team_num;
    return $this->match_dal->addPlayer($format);
  }

  public function importMatchPast() {
    $date1 = date('Y-m-d', strtotime('-1 day'));
    $date2 = date('Y-m-d', strtotime('+2 day'));
    $match_nums = $this->match_dal->getMatchNumByDate($date1, $date2, ['analyze' => 0]);
	var_dump($match_nums);
    if (!$match_nums) {
      return;
    }
    foreach ($match_nums as $value) {
      $match_num = $value;
      $url = $this->past_info . $match_num;
      $data = $this->ask($url);
      if (isset($data['error'])) {
	continue;
      }
      $match_meetings = array_slice($data['Meeting'], 0, 10);
      $host_his = array_slice($data['TeamHistory']['Home'], 0, 10);
      $guest_his = array_slice($data['TeamHistory']['Away'], 0, 10);
      $host_later = array_slice($data['TeamFixture']['Home'], 0, 3);
      $guest_later = array_slice($data['TeamFixture']['Away'], 0, 3);
      $this->importMatch($host_his);
      $this->importMatch($guest_his);
      $this->importMatch($match_meetings);
      $this->importLeague($data['Competition']);
      $this->importTeam($data['Team']);
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
      $this->dealLaterMatch($formatData['host_later']);
      $this->dealLaterMatch($formatData['guest_later']);
      $exists = $this->match_dal->existsMatchDetail($match_num);
      if ($exists) {
        $this->match_dal->updateMatchDetail($formatData, $match_num);
      } else {
        $formatData['match_num'] = $match_num;
        $this->match_dal->addMatchDetail($formatData);
      }
      if (isset($data['Standings']) && isset($data['Standings']['Home']['FullTime'])) {
      	$matchInfo = $this->match_dal->getMatchInfo($match_num);
      	$host_num = $matchInfo['host_team'];
      	$guest_num = $matchInfo['guest_team'];
        $this->dealTeamInfo($data['Standings']['Home']['FullTime'], $data['Competition'], $host_num);
        $this->dealTeamInfo($data['Standings']['Away']['FullTime'], $data['Competition'], $guest_num);
      }
      $this->match_dal->updateMatch(['analyze' => 1], $match_num);
    }
    return;
  }

  public function dealLaterMatch($match_nums) {
    if (is_string($match_nums)) {
      $match_nums = explode(',', $match_nums);
    }
    $datas = [];
    foreach ($match_nums as $match_num) {
      $data = [];
      $url = $this->base_info . $match_num;
      $data = $this->ask($url);
      
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
    $formatData['loses'] = $data['Total'][2];
    $formatData['avg_score'] = $data['Total'][3];
    $formatData['avg_lose_score'] = $data['Total'][4];
    $formatData['g_score'] = $data['Total'][5];
    $formatData['rank'] = $data['Total'][6];
    $formatData['win_rate'] = $data['Total'][7];
    foreach ($competition as $k => $v) {
      if ($v['ShortName'] == $data['Competiton']) {
        $formatData['league_num'] = $k;
      }
    }
    return $this->match_dal->updateTeam($formatData, $team_num);
  }

  public function importJcMatch($date) {
    $url = $this->jc_match . $date;
    $result = $this->ask($url);
    if (isset($result['LotteryS']) && $result['LotteryS']) {
      $match_nums = array_column($result['LotteryS'], 'matchId');
      $this->dealLeagueTypeByMatch($match_nums, 2);
    }
    return;
  }
 
  public function importDcMatch($date) {
    $date = date('Ymd', strtotime($date));
    $result = $this->ask($this->dc_match . $date);
    if (isset($result['LotteryS']) && $result['LotteryS']) {
    	$match_nums = array_column($result['LotteryS'], 'matchId');
    	$this->dealLeagueTypeByMatch($match_nums, 4);
    }
    return;
  }

  //竞彩，北单按比赛计
  public function dealLeagueTypeByMatch($match_nums, $league_type) {
    if ($league_type == 2) {
      $data = ['is_jc' => 1];
    } elseif ($league_type == 4) {
      $data = ['is_bd' => 1];
    }
    if (empty($match_nums)) {
      return false;
    }
    
    $condition = ['match_num' => ['in', '('.implode(',', $match_nums).')']];
    $this->match_dal->updateMatchByCondition($data, $condition);
    /*$leagues = [];
    foreach ($match_nums as $match_num) {
      $match_info = $this->match_dal->getMatchInfo($match_num);
      if (!$match_info) {
        continue;
      }
      $league_num = $match_info['league_num'];
      if (in_array($league_num, $leagues)) {
        continue;
      }
      $leagues[] = $league_num;
      $this->match_dal->updateLeague($data, $league_num);
    }*/
  }

  public function getMatchList($condition, $fields, $page, $pagesize, $orderBy, $userId = 0) {
        if ($condition['match_nums']) {
            $condition['match_num'] = ['in', '(' . $condition['match_nums'] . ')'];
            unset($condition['match_nums']);
        }
        $result = $this->match_dal->getList($condition, $fields, $page, $pagesize, $orderBy);
        $total = $this->match_dal->getTotal($condition);
        $result = $this->assembleMatchResult($result, $total, $userId);
        return $result;
  }

  public function getAttentMatchList ($condition, $fields, $page, $pagesize, $orderby, $userId) {
    $date2 = date('Y-m-d', time() + 86400 * 2);
    $date1 = date('Y-m-d', time() - 86400);
    $match_nums = $this->match_dal->getAttentMatchList($date1, $date2, $userId);
    $matches = array();
    foreach($match_nums as $match_num) {
	$matchInfo = $this->match_dal->getMatchInfo($match_num);
	$matches[] = $matchInfo;
    }
    $total = count($match_nums);
    $matches = $this->assembleMatchResult($matches, $total, $userId);
    return $matches;
  }

  public function leagueList($tab_type, $user_id, $league_type, $date1 = '', $date2 = '') {
    switch ($tab_type) {
        case 1:
	    $date1 = date('Y-m-d', strtotime('-1 day'));
	    $date2 = date('Y-m-d', strtotime('+1 day'));
            //$date1 = date('Y-m-d', time() - 86400);
            //$date2 = date('Y-m-d', time() + 86400 * 2);
            $condition = ['status' => ['in', '(9, 11, 13, 14, 16)']];
        break;
        case 2:
	    $date1 = date('Y-m-d H:i:s', time() - 2 * 3600);
            $date2 = date('Y-m-d 12:00:00', strtotime('+1 day'));
	    $condition = ['status' => ['not in', '(9, 11, 13, 14, 16)']];
            //$date1 = date('Y-m-d', time());
            //$date2 = date('Y-m-d 12:00:00', time() + 86400);
        break;
        case 3:
            $date1 = date('Y-m-d', strtotime('+1 day'));
            $date2 = date('Y-m-d', strtotime('+2 day'));
            //$date1 = date('Y-m-d', time() + 86400);
            //$date2 = date('Y-m-d', strtotime($date1) + 86400);
	    $condition = [];
        break;
        default :
            if (empty($date1) || empty($date2)) {
                $date1 = date('Y-m-d H:i:s', time());
                $date2 = date('Y-m-d 23:59:59', time() + $this->matchResultTimeScope * 2);
            }
            $data = $this->getLotteryLeague($date1, $date2, $league_type);
            return $data;
    }
    //if ($league_type == 2)    $condition['is_jc'] = 1;
    //if ($league_type == 3)    $condition['is_bd'] = 1;
    $league_type_map = [
      1 => 'is_recommend',
      2 => 'is_jc',
      3 => 'is_bd'
    ];

    if ($league_type && in_array($league_type, [2, 3])) {
        $special_matchs = $this->getSpecialMatchNum($league_type, $date1, $date2);
        if (empty($special_matchs)) {
            return ['data' => [], 'match_count' => 0];
        }
    }
    if ($special_matchs && !isset($condition['match_num'])) {
        $condition['match_num'] = implode(',', $special_matchs);
    }

    $league_nums = $this->match_dal->getLeagueNums($date1, $date2, $condition);
    $league_match_count = $this->match_dal->getLeagueCountMatch($date1, $date2, $condition);
    $league_list = [];
    $match_count = 0;
    if ($league_nums) {
      foreach($league_nums as $v) {
        $league_info = $this->match_dal->getLeagueInfo($v);
	      $league_info['name'] = $league_info['short_name'];
        $league_info['match_count'] = $league_match_count[$v];
        if (!$league_info['initial']) {
          continue;
        }
        if (!$league_info[$league_type_map[$league_type]] && $league_type == 1) {
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

  public function attentMatch($data) {
    $exists = $this->match_dal->existsAttention($data['user_id'], $data['match_num']);
    if (!$exists) {
      return  $this->match_dal->addAttention($data);
    }
    return $this->match_dal->updateAttention($exists['attention'], $exists['id']);
  }

  public function hotMatch($match_num) {
    $match_info = $this->match_dal->getMatchInfo($match_num);
    $update_data = ['is_hot' => 1];
    if ($match_info['is_hot']) {
      $update_data['is_hot'] = 0;
    }
    return $this->match_dal->updateMatch($update_data, $match_num);
  }

  public function matchFormation($match_num) {
    $match_detail = $this->match_dal->getMatchDetail($match_num);
    $host_formation = $guest_formation = [];
    $host_formation['first'] = $this->dealFormationPlayer($match_detail['host_first_players']);
    $host_formation['back'] = $this->dealFormationPlayer($match_detail['host_back_players']);
    $guest_formation['first'] = $this->dealFormationPlayer($match_detail['guest_first_players']);
    $guest_formation['back'] = $this->dealFormationPlayer($match_detail['guest_back_players']);
    $result = ['host_formation' => $host_formation, 'guest_formation' => $guest_formation, 'map' => [], 'really' => 0];
    return $result;
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
      $player_info = $this->match_dal->getPlayerInfo($player);
      if (empty($player_info)) {
        continue;
      }
      $result[] = $player_info;
    }
    return $result;
  }

  public function matchAnalyze($match_num) {
    $match_data = $this->match_dal->getMatchInfo($match_num);
    $detail_data = $this->match_dal->getMatchDetail($match_num);
    $hostInfo = $this->match_dal->getTeamInfo($match_data['host_team']);
    $host_team_name = $hostInfo['name'];
    //排名
    $rank['host'] = $this->dealTeamRank($match_data['host_team']) ?: (object)[];
    $rank['guest'] = $this->dealTeamRank($match_data['guest_team']) ?: (object)[];
    //历史交锋
    $meeting['normal'] = $this->dealRecentMatch($detail_data['meeting_match'], $match_data['host_team']);
    $samehg_meeting = $this->dealSameHostGuestMatchNum($detail_data['meeting_match'], $match_data['host_team'], $match_data['guest_team']);
    $meeting['same_hg'] = $this->dealRecentMatch($samehg_meeting, $match_data['host_team']);
    //近期战绩
    $recent['host']['normal'] = $this->dealRecentMatch($detail_data['host_history'], $match_data['host_team']);
    $same_league_num_host = $this->dealSameLeagueMatchNum($detail_data['host_history'], $match_data['league_num']);
    $recent['host']['same_league'] = $this->dealRecentMatch($same_league_num_host, $match_data['host_team']);
    $samehg_host = $this->dealSameHostGuestMatchNum($detail_data['host_history'], $match_data['host_team'], $match_data['guest_team']);
    $recent['host']['same_hg'] = $this->dealRecentMatch($samehg_host, $match_data['host_team']);

    $recent['guest']['normal'] = $this->dealRecentMatch($detail_data['guest_history'], $match_data['guest_team']);
    $same_league_num_guest = $this->dealSameLeagueMatchNum($detail_data['guest_history'], $match_data['league_num']);
    $recent['guest']['same_league'] = $this->dealRecentMatch($same_league_num_host, $match_data['guest_team']);
    $samehgNum_guest = $this->dealSameHostGuestMatchNum($detail_data['guest_history'], $match_data['host_team'], $match_data['guest_team']);
    $recent['guest']['same_hg'] = $this->dealRecentMatch($samehgNum_guest, $match_data['guest_team']);
    //未来赛程
    $later['host'] = $this->dealRecentMatch($detail_data['host_later']);
    $later['guest'] = $this->dealRecentMatch($detail_data['guest_later']);
    
    $result['rank'] = $rank;
    $result['meeting'] = $meeting;
    $result['recent'] = $recent;
    $result['later'] = $later;
    return $result;
  }

  private function dealTeamRank($team_num) {
    $team_info = $this->match_dal->getTeamInfo($team_num);
    $result['name'] = $team_info['name'];
    $result['matchs'] = $team_info['matchs'];
    $result['wdl'] = $team_info['wins'] . '/' . $team_info['loses'];
    $result['gfg'] = $team_info['avg_score'] . '/' . $team_info['avg_lose_score'] . '/' . $team_info['g_score'];
    $result['rank'] = $team_info['rank'];
    $result['win_rate'] = $team_info['win_rate'];

    return $result;
  }

  private function dealRecentMatch($matchs, $host_num = 0) {
    $res = array();
    if (empty($matchs)) {
      return ['sentence' => [], 'result' => []];
    }
    if (is_string($matchs)) {
      $matchs = explode(',', $matchs);
    }
    foreach ($matchs as $m) {
      $info = $this->match_dal->getMatchInfo($m);
      $match_info = $this->assembleMatchResult([$info], 1);
      $match_info = $match_info['data'][0];
      $tmp = [];
      $tmp['date'] = $match_info['date'];
      $tmp['league_short_name'] = $match_info['league_short_name'];
      $tmp['host_team_name'] = $match_info['host_team_name'];
      $tmp['guest_team_name'] = $match_info['guest_team_name'];
      //$tmp['score_all'] = $match_info['bscore'][0] . '-' . $match_info['ascore'][0];
      $tmp['score_all'] = $match_info['ascore'][0] . '-' . $match_info['bscore'][0];
      $tmp['half'] = $match_info['half'];
      $tmp['handicap'] = $match_info['handicap'];
      $tmp['wdl'] = $this->dealWinLose($host_num, $match_info);
      $tmp['is_host'] = 1;
      if ($host_num != $match_info['host_team']) {
        $tmp['is_host'] = 0;
      }
          $handicap = $match_info['handicap'];
          $tmp['pan_wdl'] = '';
          if ($handicap) {
              $scores = explode('-', $tmp['score_all']);
              if ($tmp['is_host']) {
                  $big = $scores[0];
                  $small = $scores[1] + $handicap;
              } else {
                  $big = $scores[1] + $handicap;
                  $small = $scores[0];
              }
              if ($big > $small) {
                  $tmp['pan_wdl'] = 'w';
              } elseif ($big == $small) {
                  $tmp['pan_wdl'] = 'd';
              } else {
                  $tmp['pan_wdl'] = 'l';
              }
          }
      if ($host_num == 'later') {
        $later_time = strtotime($tmp['date']);
        $cha = $later_time - time();
        $tian = ceil(bcdiv($cha, 86400, 1));
        $tmp['tian'] = $tian;
      }
      $result[] = $tmp;
    }
    $res = ['sentence' => $this->dealSentence($result, $host_num), 'result' => $result];
    return $res;
  }

  private function dealSentence ($data, $host_num = 0) {
    $sentence = [];
    if ($host_num) {
      $hostInfo = $this->match_dal->getTeamInfo($host_num);
      $host_team_name = $hostInfo['name'];
      $count = count($data);
      $w = $d = $l = $pan_w = $pan_a = 0;
      foreach ($data as $v) {
        if ($v['wdl'] == 'w') {
          $w++;
        } elseif ($v['wdl'] == 'l') {
          $l++;
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
      $sentence = ['team_name' => $host_team_name, 'count' => $count, 'w' => $w, 'l' => $l, 'win_rate' => $rate, 'pan_rate' => $pan_rate];
    }
    return $sentence;
  }

  public function dealWinLose($host_num, $data) {
    if ($data['host_score'] && $data['guest_score']) {
      if ($data['host_score'] > $data['guest_score']) {
        if ($host_num == $data['host_team']) {
          return 'w';
        } else {
          return 'l';
        }
      } else {
        if ($host_num == $data['host_team']) {
          return 'l';
      	} else {
          return 'w';
        }
      }
    } else {
      return '';
    }
  }

  private function dealSameHostGuestMatchNum($match_nums, $host_num, $guest_num = 0) {
    if (is_string($match_nums)) {
      $match_nums = explode(',', $match_nums);
    }
    $result = [];
    foreach ($match_nums as $m) {
      $info = $this->match_dal->getMatchInfo($m);
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
      $info = $this->match_dal->getMatchInfo($m);
      if ($info['league_num'] == $league_num) {
        $result[] = $m;
      }
    }
    return $result;
  }

  public function assembleMatchResult($data, $total = 0, $userId = 0) {
    $leagueNum_arr = array_unique(array_column($data, 'league_num'));
    $teamNum_arr = array_unique(array_merge(array_column($data, 'host_team'), array_column($data, 'guest_team')));
    $leagueCondition = [];
    if (!empty($leagueNum_arr)) {
    	$leagueCondition = ['league_num' => ['in', '(' . implode(',', $leagueNum_arr) . ')']];
    }
    $leagueList = $this->match_dal->getLeagueList($leagueCondition);
    $leagueList = array_column($leagueList, null, 'league_num');
    $teamCondition = [];
    if (!empty($teamNum_arr)) {
        $teamCondition = ['team_num' => ['in', '(' . implode(',', $teamNum_arr) . ')']];
    }
    $teamList = $this->match_dal->getTeamList($teamCondition);
    $teamList = array_column($teamList, null, 'team_num');
    foreach ($data as $k => $v) {
      $time = 0;
      $time = strtotime($data[$k]['date']);
      //$leagueInfo = $this->match_dal->getLeagueInfo($v['league_num']);
      //$hostInfo = $this->match_dal->getTeamInfo($v['host_team']);
      //$guestInfo = $this->match_dal->getTeamInfo($v['guest_team']);
      $leagueInfo = $leagueList[$v['league_num']];
      $hostInfo = $teamList[$v['host_team']];
      $guestInfo = $teamList[$v['guest_team']];
      $data[$k]['league_short_name'] = $leagueInfo['short_name'] ?: '';
      $data[$k]['league_color'] = $leagueInfo['color'] ?: '';
      $data[$k]['host_team_name'] = $hostInfo['name'] ?: '';
      $data[$k]['host_team_logo'] = $hostInfo['logo'] ?: $this->default_team_icon;
      $data[$k]['guest_team_name'] = $guestInfo['name'] ?: '';
      $data[$k]['guest_team_logo'] = $guestInfo['logo'] ?: $this->default_team_icon;
      if (!empty($v['handicap'])) {
      	$data[$k]['handicap'] = $v['handicap'] > 0 ? -1 * $v['handicap'] : abs($v['handicap']);
      }
      $data[$k]['date'] = date('Y-m-d', $time);
      $data[$k]['time'] = date('H:i', $time);
      $data[$k]['match_status']['color'] = '#4d73ec';
      $data[$k]['cases'] = $this->match_dal->getResourceNum($v['match_num']);
      $data[$k]['is_attention'] = 0;
      if (!empty($userId)) {
	      $attentionInfo = $this->match_dal->existsAttention($userId, $v['match_num']);
	      $is_attention = !empty($attentionInfo) ? $attentionInfo['attention'] : 0;
      	$data[$k]['is_attention'] = $is_attention;
      }
      
      $data[$k]['match_status']['status'] = $this->match_status[$v['status']];
      //$data[$k]['ascore'] = !empty($v['ascore']) ? array_pad(explode(',', $v['ascore']), 6, '') : ['', '', '', '', '', ''];
      //$data[$k]['bscore'] = !empty($v['bscore']) ? array_pad(explode(',', $v['bscore']), 6, '') : ['', '', '', '', '', ''];
      $data[$k]['ascore'] = (empty($v['ascore']) || in_array($v['status'], [0, 13, 14, 16])) ? ['', '', '', '', '', ''] : array_pad(explode(',', $v['ascore']), 6, '');
      $data[$k]['bscore'] = (empty($v['bscore']) || in_array($v['status'], [0, 13, 14, 16])) ? ['', '', '', '', '', ''] : array_pad(explode(',', $v['bscore']), 6, '');
      $data[$k]['score_all'] = (empty($v['ascore']) && empty($v['bscore'])) ? '' : explode(',', $v['bscore'])[0] . '-' .explode(',', $v['ascore'])[0];//由于客队显示在前，所以调换位置
      $data[$k]['host_score'] = empty($v['ascore']) ? 0 : explode(',', $v['ascore'])[0];
      $data[$k]['guest_score'] = empty($v['bscore']) ? 0 : explode(',', $v['bscore'])[0];
      $liveInfo = $this->_redisModel->redisGetHashList('match:basketball:liveinfo', 'match:' . $v['match_num'], true);
      if ($liveInfo) {
	      $data[$k]['match_status']['status'] = $this->match_status[$liveInfo['Status']];
	      $data[$k]['status'] = $liveInfo['Status'];
      	$data[$k]['ascore'] = $liveInfo['AScore'];
      	$data[$k]['bscore'] = $liveInfo['BScore'];
      	$data[$k]['score_all'] = $liveInfo['BScore'][0] . '-' . $liveInfo['AScore'][0];//由于客队显示在前，调换位置
      	$data[$k]['host_score'] = $liveInfo['AScore'][0];
      	$data[$k]['guest_score'] = $liveInfo['BScore'][0];
      	$data[$k]['minute'] = $liveInfo['Time'];
      }

        $is_jc = $is_bd = 0;
        $odds = [];
        $bd = $this->match_dal->getLotteryByCondition(['match_num' => $v['match_num'], 'lottery_type' => 2]);
        if ($bd) {
            $is_bd = 1;
            foreach ($bd as $b) {
                $lottery_data = explode('-', $b['lottery_num']);
                $odds['lottery_num'] = '北单' . sprintf('%03d', $lottery_data[1]);
                $odds['h'] = $b['h'];
                $odds['wdl'] = $b['w'] . '/' . $b['l'];
            }
        }
        $jc = $this->match_dal->getLotteryByCondition(['match_num' => $v['match_num'], 'lottery_type' => 1]);
        if ($jc) {
            $is_jc = 1;
            foreach ($jc as $j) {
                $odds['lottery_num'] = $j['lottery_num'];
                if ($j['h'] == 0) {
                    $odds['h'] = 0;
                    $odds['wdl'] = $j['w'] . '/' . $j['l'];
                }
            }
        }
        $data[$k]['is_bd'] = $is_bd;
        $data[$k]['is_jc'] = $is_jc;
        if ($is_jc || $is_bd) {
            $data[$k]['odds'] = $odds;
        }
    }
    return ['data' => $data, 'total' => $total];
  }

  public function matchInfo($match_num, $userId = 0) {
    $matchInfo = $this->match_dal->getMatchInfo($match_num);
    $res = $this->assembleMatchResult([$matchInfo], 1, $userId);
    return $res['data'][0];
  }

  public function getMatchStat($match_num) {
    $res = [];
    $result = $this->match_dal->getMatchStat($match_num);
    foreach($result as $key => $value) {
	$obj = [];
      if (in_array($key, array_keys($this->stat_map))) {
    	$obj['name'] = $this->stat_map[$key];
	$obj['data'] = explode(',', $value);
	if (in_array($key, ['shot_rate', 'three_rate', 'penalty_shot'])) {
	  $host_data = explode('-', $obj['data'][0]);
	  $guest_data = explode('-', $obj['data'][1]);
	  $host_res = empty($host_data[1]) ? 0 : ceil($host_data[0] / $host_data[1] * 100);
	  $guest_res = empty($guest_data[1]) ? 0 : ceil($guest_data[0] / $guest_data[1] * 100);
	  $obj['data'] = [$host_res, $guest_res];
	  $obj['data_extra'] = [$host_data[0] .'/'. $host_data[1], $guest_data[0] .'/'. $guest_data[1]];
	}
	$res[] = $obj;
      }
    }
    return $res;
  }
  
  public function getMatchPlayerStat($match_num) {
    $res = $this->match_dal->getMatchPlayerStat($match_num);
    if (!empty($res)) {
    	$matchInfo = $this->match_dal->getMatchInfo($match_num);
	$hostInfo = $this->match_dal->getTeamInfo($matchInfo['host_team']);
    	$host_team_name = $hostInfo['name'];
	$guestInfo = $this->match_dal->getTeamInfo($matchInfo['guest_team']);
    	$guest_team_name = $guestInfo['name'];

	$host_list = $guest_list = [];
	foreach($res as $value) {
	  if (!empty($value['pos'])) {
                $value['pos'] = $this->pos_map[$value['pos']];
          }
	  if ($value['is_host'] == 1) {
	    $host_list[] = $value;
	  } else {
	    $guest_list[] = $value;
	  }
	}
    	return array(
	  'host' => ['team_num' => $matchInfo['host_team'], 'team_name' => $host_team_name, 'list' => $host_list],
	  'guest' => ['team_num' => $matchInfo['guest_team'], 'team_name' => $guest_team_name, 'list' => $guest_list]
	);
    } else {
	return [];
    }
  }

  public function hotMatchList($userId = 0) {
    $date1 = date('Y-m-d');
    $date2 = date('Y-m-d', strtotime($date1) + 86400 * 2);
    $result = $this->match_dal->getHotMatch($date1, $date2);
    if (empty($result)) {
      return [];
    }
    $res = $this->assembleMatchResult($result, count($result), $userId);
    return $res['data'];
  }

  public function getHistory($match_num) {
    $match_data = $this->match_dal->getMatchInfo($match_num);
    $detail_data = $this->match_dal->getMatchDetail($match_num);
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

    public function getUpcomingMatch($second = 300) {
        $now = date('Y-m-d H:i:s', time());
        $keytime = date('Y-m-d H:i:s', time() + $second);
        $val1 = ' between \'' . $now . '\' ';
        $val2 = ' and \'' . $keytime . '\' ';
        $condition['date'] = [$val1, $val2];
        $condition['status'] = 0;
        $result = $this->match_dal->getMatchByCondition($condition);
        if (!$result) {
            return [];
        }
        foreach ($result as $k => $v) {
            $hostInfo = $this->match_dal->getTeamInfo($v['host_team']);
            $result[$k]['host_name'] = $hostInfo['name'] ?: '';
            $guestInfo = $this->match_dal->getTeamInfo($v['guest_team']);
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

    public function updateGamePlayerStat() {
      $match_nums = $this->match_dal->getPlayerStatMatchNums();
      foreach($match_nums as $match_num) {
        $match_stat = $this->ask($this->game_stat . $match_num);
        if (isset($match_stat['error'])) {
          continue;
        }
        //if (isset($match_stat['H_Stat_Total']) && isset($match_stat['A_Stat_Total'])) {
          //$this->upsertMatchStat($match_num, $match_stat['H_Stat_Total'], $match_stat['A_Stat_Total']);
        //}
        if (isset($match_stat['H_Stat'])) {
          $this->upsertMatchPlayerStat($match_num, $match_stat['H_Stat'], 1);
        }
        if (isset($match_stat['A_Stat'])) {
          $this->upsertMatchPlayerStat($match_num, $match_stat['A_Stat'], 0);
        }
      }
      return;
    }

    public function matchLottery($date1, $date2, $condition) {
       $lottery_type = $condition['lottery_type'];
       if(in_array($lottery_type, [1, 2])) {
            $original_data = $this->match_dal->getLotteryMatch($date1, $date2, $condition);
            $count = $original_data['count'] ?: 0;
            $original_data = $original_data['data'];
            $result = [];
            foreach ($original_data as $val) {
                $match_num = $val['match_num'];
                $match_detail = $this->match_dal->getLotteryDetail($match_num, $lottery_type);
                    foreach ($match_detail as $item) {
                        $result[$item['match_num']]['match_num'] = $match_num;
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
                            'is_single' => $item['is_single'],
                            'h' => $item['h'],
                            'w' => $item['w'],
                            'l' => $item['l'],
                            'lottery_id' => $item['id'],
                            'play_method' => $item['type'],
                            'play_method_text' => $this->play_method[$item['type']],
                        ];
                        $result[$item['match_num']]['odds'][$item['type']] = $tmp;
                }
                foreach ($result as $k => $item) {
                    ksort($item['odds']);
                    $result[$k]['odds'] = array_values($item['odds']);
                }
                $result = array_values($result);
            }
            return ['data' => $result, 'count' => $count];
       } else {
           //$all_match = $this->match_dal->getAllMatch($date1, $date2, $condition);
           //return ['data' => $all_match['data'], 'count' => $all_match['count']];
       }
    }

    public function dealLotteryChange($old_data) {
        $lottery_id = $old_data['lottery_id'];
        $condition['id'] = $lottery_id;
        $lottery_type = $old_data['lottery_type'];
        if (empty($condition['id'])) {
            return false;
        }
        $new_data = [];
        if (in_array($lottery_type, [1, 2])) {
            $new_data = $this->match_dal->existsLottery($condition);
        }
        //else {
        //    $new_data = [];
        //    $indexs_info = $this->match_dal->getIndesxById($lottery_id);
        //    if (!$indexs_info) {
        //        return false;
        //    }
        //    $match_num = $indexs_info['match_num'];
        //    $comp_num = $indexs_info['comp_num'];
        //    $indexs_type = $indexs_info['indexs_type'];
        //    $indexs = $this->match_dal->getIndexsByComp($match_num, $indexs_type, $comp_num,  2);
        //    $new_data['h'] = $indexs['center_indexs'];
        //    $new_data['w'] = $indexs['left_indexs'];
        //    $new_data['l'] = $indexs['right_indexs'];
        //}
        $tmp = [];
        $tmp['h'] = [$old_data['h'], $new_data['h']];
        $tmp['w'] = [$old_data['w'], $new_data['w']];
        //if ($lottery_type == 1) {
        //    $tmp['d'] = [$old_data['d'], $new_data['d']];
        //}
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

    public function getLotteryLeague ($date1, $date2, $lottery_type) {
        if (in_array($lottery_type, [2, 4])) {
            if($lottery_type == 2) {
                $lottery_type = 1;
            } elseif ($lottery_type == 4) {
                $lottery_type = 2;
            }
        }
        //else {
        //    $league_num = $this->match_dal->getLeagueCountMatch($date1, $date2);
        //    $league_nums = array_keys($league_num);
        //    $result = [];
        //    foreach ($league_nums as $item) {
        //        $tmp = [];
        //        $tmp['league_num'] = $item;
        //        $league_info = $this->match_dal->getLeagueInfoByNum($item);
        //        $tmp['league_short_name'] = $league_info['short_name'];
        //        $sort[] = $league_info['initial'];
        //        $result[] = $tmp;
        //    }
        //    array_multisort($sort, SORT_ASC, $result);
        //    return $result;
        //}
        $condition['lottery_type'] = $lottery_type;
        return $this->match_dal->getLotteryLeague($date1, $date2, $condition);
    }

    public function ask($url) {
      $data = $this->common->httpGetRequest($url, []);
      $data = json_decode($data, 1);
      return $data;
    }

    public function getLotteryInfo($match_num) 
    {
      $lotterys = $this->match_dal->getLotteryByCondition(['match_num' => $match_num, 'is_first' => 0, 'lottery_type' => 1]);

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
        /*
        foreach ($lotterys as $item) {
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
        */
        $lottery_ids = array_column($lotterys, 'id');
        array_multisort($lottery_ids, SORT_DESC, $lotterys);
        $lotterys_group = self::array_group_by($lotterys, 'type'); //1:胜平负参考值,2:让球胜平负参考值,3:大小分参考值

        $scheduleInfo = $this->match_dal->getLastSchedule($match_num, 2); //2为篮球
        $lottery_result = $this->lottery_result_map[$scheduleInfo['lottery_result']]; //推荐赔率的结果:1,主胜，2,平，3,客胜，4,主半胜，5,客半胜
        $result['odds'] = [];
        foreach ($lotterys_group as $key => $item) {
            $result['odds'][$key] = [
                'type' => $item[0]['type'],
                'lottery_id' => $item[0]['id'],
                'lottery_type' => $item[0]['lottery_type'],
                'lottery_num' => $item[0]['lottery_num'],
                'lottery_result' => $lottery_result,
                'is_signle' => $item[0]['is_signle'],
                'play_method' => strval($item[0]['type']),
                'h' => $item[0]['h'],
                'w' => $item[0]['w'],
                'd' => '',
                'l' => $item[0]['l'],
            ];
        }

        ksort($result['odds']);
        $result['odds'] = array_values($result['odds']);
        $result['lottery_type'] = in_array(1, array_column($result['odds'], 'lottery_type')) ? 1 : 2;
        if (in_array(3, array_column($result['odds'], 'lottery_type')) || in_array(4, array_column($result['odds'], 'lottery_type'))) {
            $result['lottery_type'] = 3;
        }
        $result['lottery_num'] = $result['odds'][0]['lottery_num'];
        return $result;
    }                                                                                               

    public function getSpecialMatchNum($league_type, $date1, $date2) {
        $lottery_type = 1;
        if ($league_type == 3) {
            $lottery_type = 2;
        }
        $condition['lottery_type'] = $lottery_type;
        $match_data = $this->match_dal->getLotteryMatch($date1, $date2, $condition, 0);
        $match_nums = $match_data['data'];
        $match_nums = array_column($match_nums, 'match_num');
        return $match_nums;
    }

    /**
     * 根据$key给二维数组分组
     * 
     * @param  [type] $arr [二维数组]
     * @param  [type] $key [键名]
     * @return [type]      [新的二维数组]
     */
    public static function array_group_by($arr, $key)
    {
        $grouped = array();
        foreach ($arr as $value) {
            $grouped[$value[$key]][] = $value;
        }
        if (func_num_args() > 2) {
            $args = func_get_args();
            foreach ($grouped as $key => $value) {
                $parms = array_merge($value, array_slice($args, 2, func_num_args()));
                $grouped[$key] = call_user_func_array('array_group_by', $parms);
            }
        }
        return $grouped;
    }

    public function findLotteryById($id) {
        return $this->match_dal->findLotteryById($id);
    }
}
