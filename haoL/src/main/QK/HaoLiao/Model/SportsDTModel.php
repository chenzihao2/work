<?php

namespace QK\HaoLiao\Model;

use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Common\CommonHandler;
use QK\WSF\Settings\AppSetting;

class SportsDTModel extends BaseModel {

  private $domain_url = "http://feed.sportsdt.com/t_yingxun";
  
  public function __construct() {
    parent::__construct();
  }

  public function lists() {
    $url = "$this->domain_url/basketball/report.aspx?type=getreportlist";
    $params = array();
    $list = CommonHandler::newInstance()->httpGetRequest($url, $params);
    if(!is_array($list)) {
      $list = json_decode($list, true);
    }
    $res = array();
    foreach($list as $val) {
      $data = array(
        'GameId' => $val['GameId'],
        'StartTime' => $val['StartTime'],
        'CompetitionName' => $val['CompetitionName'],
        'HomeName' => $val['HomeName'],
        'AwayName' => $val['AwayName'],
        'PredictionDesc' => $val['PredictionDesc'],
        'LA' => $val['LA'],
        'LB' => $val['LB'],
        'A_pm' => $val['A_pm'],
        'B_pm' => $val['B_pm']
      );
      $res[] = $data;
    }
    return $res;
  }

  public function gameInfo($gid) {
    $url = "$this->domain_url/basketball/report.aspx?type=getreportinfo&gameid=$gid";
    $params = array();
    $info = CommonHandler::newInstance()->httpGetRequest($url, $params);
    if(!is_array($info)) {
      $info = json_decode($info, true);
    }
    $gameInfo = array();
    if(!empty($info) && isset($info['game'])) {
    $gameInfo = array(
      'GameId' => $info['game']['GameId'],
      'StartTime' => $info['game']['StartTime'],
      'CompetitionName' => $info['game']['CompetitionName'],
      'CompetitionNameSub' => $info['game']['CompetitionNameSub'],
      'Sub_type_name' => $info['game']['Sub_type_name'],
      'HomeName' => $info['game']['HomeName'],
      'HomeName_short' => $info['game']['HomeName_short'],
      'AwayName' => $info['game']['AwayName'],
      'AwayName_short' => $info['game']['AwayName_short'],
      'PredictionDesc' => $info['game']['PredictionDesc'],
      'LA' => $info['game']['LA'],
      'LB' => $info['game']['LB'],
      'A_pm' => $info['game']['A_pm'],
      'B_pm' => $info['game']['B_pm']
    );
    }
    $dataInfo = array();
    if(!empty($info) && isset($info['OddsData'])) {
    $dataInfo = array(
      'AH' => $info['OddsData']['AH'],
      'AH_Init' => $info['OddsData']['AH_Init'],
      'HDA' => $info['OddsData']['HDA'],
      'HDA_Init' => $info['OddsData']['HDA_Init'],
      'OU' => $info['OddsData']['OU'],
      'OU_Init' => $info['OddsData']['OU_Init']
    );
    }
    $suspendedInfo = array();
    if(!empty($info) && isset($info['Injury'])) {
    $suspendedInfo = array(
      'Name' => $info['Injury']['Name'],
      'Pos' => $info['Injury']['Pos'],
      'Part' => $info['Injury']['Part'],
      'On' => $info['Injury']['On']
    );
    }
    $latestGameInfo = array();
    $homeLatestGameInfo = array();
    $res = array(
      'game' => $gameInfo,
      'OddsData' => $dataInfo,
      'Injury' => $suspendedInfo
    );
    return $res;
  }

}
