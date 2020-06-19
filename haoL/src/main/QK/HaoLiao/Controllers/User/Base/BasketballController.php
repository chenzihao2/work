<?php

namespace QK\HaoLiao\Controllers\User\Base;


use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\BetRecordModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\UserFollowModel;
use QK\HaoLiao\Model\BasketballModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Model\ExpertModel;
class BasketballController extends UserController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
	    $this->common = new CommonHandler();
	    $this->basketballModel = new BasketballModel();
    }

    public function matchList() {
	//tabtype	0:即时；1:赛果；2:赛程；3:关注
	$params = $this->checkApiParam([], ['tab_type' => 1, 'page' => 1, 'league_num' => '', 'user_id' => 0, 'pagesize' => 50, 'league_type' => 1]);
    $league_type = $params['league_type'];
	$userId = $params['user_id'];
	$tab_type = $params['tab_type'];
        $league_num = $params['league_num'];
	if(!is_array($league_num)) {
	  $league_num = json_decode($league_num, true);
	}

	$st = date('Y-m-d', strtotime('-1 day'));
	$et = date('Y-m-d', strtotime('+1 day'));
	if ($tab_type == 1) {
	  $condition['status'] = ['in', '(9, 11, 13, 14, 16)'];
	}else if ($tab_type == 2) {
	  $st = date('Y-m-d H:i:s', time() - 2 * 3600);
          $et = date('Y-m-d 12:00:00', strtotime('+1 day'));
	  $condition['status'] = ['not in', '(9, 11, 13, 14, 16)'];
	} else if($tab_type == 3) {
	  $st = date('Y-m-d', strtotime('+1 day'));
          $et = date('Y-m-d', strtotime('+2 day'));
	}

    if ($league_type && in_array($league_type, [2, 3])) {
        $match_nums = $this->basketballModel->getSpecialMatchNum($league_type, $st, $et);
        if ($match_nums) {
            $condition['match_nums'] = implode(',', $match_nums);
        } else {
            return ['data' => [], 'total' => 0];
        }
    }

	$val1 = ' between \'' . $st . '\' ';
        $val2 = ' and \'' . $et . '\' ';
        $condition['date'] = [$val1, $val2];
	if (!empty($league_num)) {
	  $condition['league_num'] = ['in', '(' . $league_num . ')'];
  }
  //if ($league_type == 2)    $condition['is_jc'] = 1;
  //if ($league_type == 3)    $condition['is_bd'] = 1;
	
	$fields = ['match_num', 'date', 'league_num', 'host_team', 'guest_team', 'half', 'ascore', 'bscore', 'status', 'handicap'];

	$page = $params['page'];
        $pagesize = $params['pagesize'];
	$orderby = array('date' => 'asc');
	if ($tab_type == 1) {
	  $orderby = array('date' => 'desc');
	}
	
	$result = array();
	if ($params['tab_type'] == 4) {
	  $result = $this->basketballModel->getAttentMatchList(array(), $fields, $page, $pagesize, $orderby, $userId);
	} else {
	  $result = $this->basketballModel->getMatchList($condition, $fields, $page, $pagesize, $orderby, $userId);
	}
	$this->responseJson($result);
    }

    public function attentMatch() {
        $params = $this->checkApiParam(['match_num', 'date'], ['user_id' => 0]);
        $match_num = $params['match_num'];
	      $user_id = $params['user_id'];
        $match_date = date('Y-m-d H:i:s', strtotime($params['date']));
        $args = ['user_id' => $user_id, 'match_num' => $match_num, 'match_date' => $match_date];
        $this->basketballModel->attentMatch($args);
        $this->responseJson();
    }

    public function leagueList() {
        $params = $this->checkApiParam([], ['tab_type' => 1, 'league_type' => 0, 'user_id' => 0, 'date1' => '', 'date2' => '']);
        $tab_type = $params['tab_type'];
        $league_type = $params['league_type'];
	    $user_id = $params['user_id'];
	    $date1 = $params['date1'];
	    $date2 = $params['date2'];
        $result = $this->basketballModel->leagueList($tab_type, $user_id, $league_type, $date1, $date2);
        $this->responseJson($result);
    }

    public function matchAnalyze() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->basketballModel->matchAnalyze($match_num);
        $this->responseJson($result);
    }

    public function matchFormation() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->basketballModel->matchFormation($match_num);
        $this->responseJson($result);
    }

    public function live() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->basketballModel->live($match_num);
        $this->responseJson($result);
    }

    public function matchInfo() {
        $params = $this->checkApiParam(['match_num'], ['user_id' => 0]); 
        $match_num = $params['match_num'];
        $user_id = $params['user_id'];
        $result = $this->basketballModel->matchInfo($match_num, $user_id);
        $this->responseJson($result);
    }

    public function getMatchStat() {
	      $params = $this->checkApiParam(['match_num'], []);
        $match_num = $params['match_num'];
	      $matchInfo = $this->basketballModel->matchInfo($match_num, 0);
	      $ascore = $matchInfo['ascore'];
	      $bscore = $matchInfo['bscore'];
	      if (empty($matchInfo['ascore'][0]) && empty($matchInfo['bscore'][0])) {
	        $ascore = $bscore = [];
	      }
        $result = $this->basketballModel->getMatchStat($match_num);
        $this->responseJson(['score' => ['ascore' => $ascore, 'bscore' => $bscore], 'stat' => $result]);
    }
    
    public function getPlayerStat() {
	      $params = $this->checkApiParam(['match_num'], []);
        $match_num = $params['match_num'];
	      $matchInfo = $this->basketballModel->matchInfo($match_num, 0);
	      $ascore = $matchInfo['ascore'];
        $bscore = $matchInfo['bscore'];
        if (empty($matchInfo['ascore'][0]) && empty($matchInfo['bscore'][0])) {
          $ascore = $bscore = [];
        }
        $result = $this->basketballModel->getMatchPlayerStat($match_num);
        $this->responseJson(['score' => ['ascore' => $ascore, 'bscore' => $bscore], 'stat' => $result]);
    }

    public function caseList() {
      	$params = $this->checkApiParam(['match_num'], ['user_id' => 0, 'page' => 1, 'pagesize' => 20, 'platform' => 1]);
	      $userId = $params['user_id'];
	      $page = $params['page'];
	      $pagesize = $params['pagesize'];
	      $platform = $params['platform'];
	      $start = ($page - 1) * $pagesize;
	      $match_num = $params['match_num'];
	      $resourceModel = new ResourceModel();
      	$recommendList = $resourceModel->getResourceListByMatch($start, $pagesize, $platform, $match_num, 2);

      	$result = array();
      	if(!empty($recommendList)){
          $betRecordModel = new BetRecordModel();
          $userFollowModel = new UserFollowModel();
          $expertModel=new ExpertModel();
          foreach($recommendList as $key => $val){
            $isFollowExpert = 0;
            if($userId){
              $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $val['expert_id']);
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
            'is_follow_expert' => $isFollowExpert,
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
            'title' => $val['title'],
            'is_free' => $val['is_free'],
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
            'schedule' => $resourceModel->getResourceScheduleList($val['resource_id']),
            'expert' => $expertInfo,
			'view_num' => $resourceExtraInfo['view_num']
          );
          if ($val['is_groupbuy'] == 1) {
            $resourceInfo['group'] = $resourceModel->getResourceGroupInfo($val['resource_id']);
          }
          $result[] = $resourceInfo;
        }
      }
        $this->responseJson($result);
    }

    public function matchLottery() {
        $params = $this->checkApiParam([], ['date1' => 0, 'date2' => 0, 'page' => 1, 'league_num' => 0, 'page_num' => 3, 'lottery_type' => 0, 'key_words' => '']);
        $now = date('Y-m-d H:i:s', time());
        $now_end = date('Y-m-d 23:59:59', time());
        $date1 = $params['date1'] ?: date('Y-m-d H:i:s', time());
        $date2 = $params['date2'] ?: date('Y-m-d H:i:s', time() + 86400 * 3);
        if ($date1 < $now) {
            $date1 = $now;
        }
        if ($date2 < $date1) {
            $date2 = $now_end;
        } else {
            $date2 = date('Y-m-d 23:59:59', strtotime($date2));
        }
        $match_later = $this->basketballModel->matchLottery($date1, $date2, $params);
        $this->responseJson($match_later);
    }

}
