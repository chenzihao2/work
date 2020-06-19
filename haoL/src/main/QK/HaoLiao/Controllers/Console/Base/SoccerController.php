<?php

namespace QK\HaoLiao\Controllers\Console\Base;


use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\SoccerModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Common\CommonHandler;

class SoccerController extends ConsoleController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
	      $this->common = new CommonHandler();
	      $this->SoccerM = new SoccerModel();
    }

    public function matchResult() {
        $params = $this->checkApiParam([], ['date' => 0, 'page' => 1, 'league_num' => 0]);
        $date = $params['date'] ?: date('Y-m-d', time());
        $condition['page'] = $params['page'] ?: 1;
        $condition['league_num'] = $params['league_num'] ?: 0;
        $match_result = $this->SoccerM->matchResult($date, $condition);
        $this->responseJson($match_result);
    }

    public function matchNow() {
        $date = date('Y-m-d', time());
        $params = $this->checkApiParam([], ['date' => $date, 'page' => 1, 'league_num' => 0]);
        $date = $params['date'];
        $match_now = $this->SoccerM->matchNow($date, $params);
        $this->responseJson($match_now);
    }

    public function matchLater() {
        $params = $this->checkApiParam([], ['date' => 0, 'page' => 1, 'league_num' => 0]);
        $date = $params['date'] ?: date('Y-m-d', time() + 86400);
        $match_later = $this->SoccerM->matchLater($date, $params);
        $this->responseJson($match_later);
    }


    public function attentMatch() {
        $user_id = 123; //todo user info 
        $params = $this->checkApiParam(['match_num', 'date']);
        $match_num = $params['match_num'];
        $match_date = $params['date'];
        $args = ['user_id' => $user_id, 'match_num' => $match_num, 'match_date' => $match_date];
        $this->SoccerM->attentMatch($args);
        $this->responseJson();
    }

    public function leagueList() {
        $params = $this->checkApiParam([], ['date' => 0]);
        $date = $params['date'] ?: date('Y-m-d H:i:s', time());
        $result = $this->SoccerM->leagueList($date);
        $this->responseJson($result);
    }

    public function matchAnalyze() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->SoccerM->matchAnalyze($match_num);
        $this->responseJson($result);
    }

    public function getMatchIndexs() {
        $params = $this->checkApiParam(['match_num']);
        $match_num = $params['match_num'];
        $result = $this->SoccerM->getMatchIndexs($match_num);
        $this->responseJson($result);
    }

    public function refreshIndexs() {
        $params = $this->checkApiParam(['match_num'], []);
        $match_num = $params['match_num'];
        $this->SoccerM->importIndexs($match_num);
        $this->responseJson();
    }
    
}
