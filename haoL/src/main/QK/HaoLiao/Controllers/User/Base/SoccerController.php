<?php

namespace QK\HaoLiao\Controllers\User\Base;


use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\SoccerModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\CommonHandler;

class SoccerController extends UserController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
	      $this->common = new CommonHandler();
	      $this->SoccerM = new SoccerModel();
    }

    public function matchResult() {
        $params = $this->checkApiParam([], ['date' => 0, 'page' => 1, 'league_num' => 0, 'user_id' => 0, 'page_num' => 20, 'league_type' => 0]);
        $date = $params['date'] ?: date('Y-m-d', time());
        $condition['page'] = $params['page'] ?: 1;
        $condition['league_num'] = $params['league_num'] ?: 0;
        $condition['user_id'] = $params['user_id'];
        $condition['page_num'] = $params['page_num'];
        $condition['league_type'] = $params['league_type'];
        $match_result = $this->SoccerM->matchResult($date, $condition);
        $this->responseJson($match_result);
    }

    public function matchNow() {
        $date = date('Y-m-d', time());
        $params = $this->checkApiParam([], ['date' => $date, 'page' => 1, 'league_num' => 0, 'user_id' => 0, 'page_num' => 20, 'league_type' => 0,'platform'=>1]);
        $date = $params['date'];
        $match_now = $this->SoccerM->matchNow($date, $params);
        $this->responseJson($match_now);
    }

    public function matchLater() {
        $params = $this->checkApiParam([], ['date' => 0, 'page' => 1, 'league_num' => 0, 'user_id' => 0, 'page_num' => 20, 'league_type' => 0]);
        $date = $params['date'] ?: date('Y-m-d', time() + 86400);
        $match_later = $this->SoccerM->matchLater($date, $params);
        $this->responseJson($match_later);
    }

    public function attentionList() {
        $user_id = $this->getCurrentUserId();
        $params = $this->checkApiParam([], ['page' => 1, 'league_num' => 0]);
        $params['user_id'] = $user_id;
        $result = $this->SoccerM->attentMatchList($params); 
        $this->responseJson($result);
    }


    public function attentMatch() {
        $user_id = $this->getCurrentUserId(); 
        $params = $this->checkApiParam(['match_num', 'date']);
        $match_num = $params['match_num'];
        $match_date = date('Y-m-d H:i:s', strtotime($params['date']));
        $args = ['user_id' => $user_id, 'match_num' => $match_num, 'match_date' => $match_date];
        $this->SoccerM->attentMatch($args);
        $this->responseJson();
    }

    public function leagueList() {
        $params = $this->checkApiParam([], ['date1' => '', 'date2' => '', 'tab_type' => 1, 'league_type' => 0]);
        $tab_type = $params['tab_type'];
        $league_type = $params['league_type'];
        $user_id = 0;
        if ($tab_type == 4) {
            $user_id = $this->getCurrentUserId();
        }
        $date1 = $params['date1'];
        $date2 = $params['date2'];
        if ($date1 && $date2) {
            $now = date('Y-m-d H:i:s', time());
            $now_end = date('Y-m-d 23:59:59', time());
            if ($date1 < $now) {
                $date1 = $now;
            }
            if ($date2 < $date1) {
                $date2 = $now_end;
            } else {
                $date2 = date('Y-m-d 23:59:59', strtotime($date2));
            }
        }
        $result = $this->SoccerM->leagueList($tab_type, $user_id, $league_type, $date1, $date2);
        $this->responseJson($result);
    }

    public function matchAnalyze() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->SoccerM->matchAnalyze($match_num);
        $this->responseJson($result);
    }

    public function matchFormation() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->SoccerM->matchFormation($match_num);
        $this->responseJson($result);
    }

    public function matchIndexs() {
        $params = $this->checkApiParam(['match_num'], ['indexs_type' => 1, 'comp_num' => 0]);
        $match_num = $params['match_num'];
        $indexs_type = $params['indexs_type'];
        $comp_num = $params['comp_num'];
        $result = $this->SoccerM->matchIndexs($match_num, $indexs_type, $comp_num);
        $this->responseJson($result);
    }

    public function live() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->SoccerM->live($match_num);
        $this->responseJson($result);
    }

    public function matchInfo() {
        $params = $this->checkApiParam(['match_num'], ['user_id' => 0]); 
        $match_num = $params['match_num'];
        $user_id = $params['user_id'];
        $result = $this->SoccerM->nowInfo($match_num, $user_id);
        $this->responseJson($result);
    }

    public function caseList() {
        $params = $this->checkApiParam(['match_num'], ['user_id' => 0, 'page' => 1, 'page_num' => 20, 'platform' => 1]);
        $result = $this->SoccerM->caseList($params);
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
        $match_later = $this->SoccerM->matchLottery($date1, $date2, $params);
        $this->responseJson($match_later);
    }

    public function refreshIndexs() {
        $params = $this->checkApiParam(['match_num'], []);
        $match_num = $params['match_num'];
        $this->SoccerM->importIndexs($match_num);
        $this->responseJson();
    }

  
    public function getTest() {
	    $common = new CommonHandler();
	    $SoccerM = new SoccerModel();
	    //$SoccerM->importMatchPast();
	    $SoccerM->getPic(3, 134149);
	    //$SoccerM->importToday();
	    //$SoccerM->updateBaseMatchInfo();
	    //$SoccerM->importOuZhi(3941170);
	    //$SoccerM->importYaPan(3934737);
	    //$SoccerM->importIndexs();
	    //$SoccerM->importJcMatch('2019-08-15');
	    //$SoccerM->importDcMatch('sfc');
	    //$SoccerM->importDcMatch();
	    //$SoccerM->importLive();
	    //$SoccerM->updateBaseMatchInfo();
	    die;
	    //$url = 'feed.sportsdt.com/t_yingxun/soccer/testing.aspx?type=getgameinfo&gameid=3845361';
	    //$url = 'feed.sportsdt.com/t_yingxun/soccer/testing.aspx?type=getrevocatorygame';
	    //$res = $common->httpGet($url, []);
	    //$res = json_decode($res, 1);
	    //var_dump($res);
	    //die;
	    //for ($i = 1562601600; $i <= 1564416000; $i+= 86400) {
	    //$date = date('Y-m-d', $i);
	    //$url = 'feed.sportsdt.com/t_yingxun/soccer/testing.aspx?type=getschedulebydate&date=' . $date;
	    //echo $url;
	    //sleep(1);
	    //$res = $common->httpGet($url, []);
	    //$res = json_decode($res, 1);
	    //$SoccerM = new SoccerModel();
	    //$SoccerM->importMatch($res['Schedule']);
	    //$SoccerM->importLeague($res['Competition']);
	    //$SoccerM->importTeam($res['Team']);
	    //}
    }

    public function importToday() {
        return $this->SoccerM->importToday();
	}


    public function updateTodayBaseMatchInfo() {
	    return $this->SoccerM->updateBaseMatchInfo();
    }
}
