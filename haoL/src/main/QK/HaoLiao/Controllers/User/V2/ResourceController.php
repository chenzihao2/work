<?php
/**
 * 料相关接口
 * User: YangChao
 * Date: 2018/10/22
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Controllers\User\Base\ResourceController as Resource;
use QK\HaoLiao\Model\BetRecordModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertSubscribeModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\UserFollowModel;
use QK\HaoLiao\Model\UserSubscribeModel;
use QK\HaoLiao\Model\SoccerModel;
use QK\HaoLiao\Model\BasketballModel;
use QK\HaoLiao\Model\RedisModel;
class ResourceController extends Resource {

  private $lottery_result_map = [
    1 => 'w',
    2 => 'd',
    3 => 'l',
    4 => 'w',
    5 => 'l'
  ];

    /**
     * 首页料推荐列表
     */
    public function recommendList(){
        $param = $this->checkApiParam([], ['user_id' => 0, 'page' => 1, 'pagesize' => 5, 'order_by' => 1, 'platform' => 2]);
        $userId = intval($param['user_id']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);

        if($userId){
            $this->checkToken();
        }

        $resourceModel = new ResourceModel();
        $recommendList = $resourceModel->getRecommendResourceList($page, $pagesize);

        if(!empty($recommendList)){

            $expertExtraModel = new ExpertExtraModel();
            $betRecordModel = new BetRecordModel();
            $expertModel = new ExpertModel();
            $userFollowModel = new UserFollowModel();
            //$userSubscribeModel = new UserSubscribeModel();
            foreach($recommendList as $key => $val){
                if(in_array($val['is_schedule_over'],[2,3])){
                    $recommendList[$key]['is_schedule_over']=0;
                }
                $isFollowExpert = $isSubscribeExpert = 0;
                $expertId = $val['expert_id'];
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);
				$resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
				 //通过比赛判定红黑单
				  $resourceScheduleList = $resourceModel->getResourceScheduleList($val['resource_id']);
				  $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
				  if(empty($resourceScheduleList)){
						$bet_status=$resourceExtraInfo['bet_status'];
				   }
				  //如果有手动判的 已手动判的为准
				  if ($resourceExtraInfo['bet_status']) {
						  $bet_status=$resourceExtraInfo['bet_status'];
				  }
				  $recommendList[$key]['bet_status']=$bet_status;
				  
                if($userId){
                    //检查用户是否关注专家
                    $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
                    //检查用户是否订阅
                    //$isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
                }

                $expertInfo['is_follow_expert'] = $isFollowExpert;
                $expertInfo['is_subscribe_expert'] = $isSubscribeExpert;
                $expertInfo['max_bet_record'] = intval($expertExtraInfo['max_bet_record']);
                $expertInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId, $platform);
                $recommendList[$key]['expert'] = $expertInfo;
                $resourceModel->addCronSoldNum($recommendList[$key]);
            }
        }

        $this->responseJson($recommendList);
    }

    public function recommendListV2(){
      $param = $this->checkApiParam([], ['user_id' => 0, 'offset' => 0, 'pagesize' => 5, 'order_by' => 1, 'platform' => 2, 'is_free' => 0, 'is_new' => 0, 'match_type' => 0]);
      $userId = intval($param['user_id']);
      if($userId){
        $this->checkToken();
      }

      //$page = intval($param['page']);
      $is_free = intval($param['is_free']);
      $is_new = intval($param['is_new']);
      $start = intval($param['offset']);
      $pagesize = intval($param['pagesize']);
      $platform = intval($param['platform']);
      $match_type = $param['match_type'];

      $resourceModel = new ResourceModel();
      $recommendList = $resourceModel->getRecommendListV2($start, $pagesize, $platform, $is_new, 0, 1, $is_free, $match_type);

	  //$RedisModel=new RedisModel("resource");
      //$redisKey = RESOURCE_VIEW;
	  
      $result = array();
      if(!empty($recommendList)){
        $betRecordModel = new BetRecordModel();
        $userFollowModel = new UserFollowModel();
        $expertModel = new ExpertModel();
        $expertExtraModel = new ExpertExtraModel();
        foreach($recommendList as $key => $val){
          $isFollowExpert = $isSubscribeExpert = 0;
		  //$viewNum = $RedisModel->redisGetHashList($redisKey, $val['resource_id']);//浏览量
          if($userId){
            $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $val['expert_id']);
          }
         $info=$expertModel->getExpertInfo($val['expert_id']);//近几中几
          $extra_info = $expertExtraModel->getExpertExtraInfo($val['expert_id']);
          $recent_red = $recent_record = '';
          if ($extra_info['recent_red'] >= 3) {
              $recent_red = $extra_info['recent_red'];
          }
          if ($extra_info['recent_record']) {
              $tmp_recent_record = json_decode($extra_info['recent_record'], 1);
              if ($tmp_recent_record[1]) {
                  if ($tmp_recent_record[1][2] >= 80) {
                      $recent_record = '近' . $tmp_recent_record[1][1] . '中' . $tmp_recent_record[1][0];
                  }
              }
          }
         $lately_red=$info['lately_red'];//近几中几
         $max_red_num=$extra_info['max_red_num'];//连红
       // $max_red_num=$expertModel->maxRedNum($val['expert_id'],$platform);//连红
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
             'recent_record'=>$recent_record,
             'recent_red'=>$recent_red,

          );
            if($expertInfo['max_bet_record']<60){
                $expertInfo['max_bet_record']='--';
            }
          $resourceExtraInfo = $resourceModel->getResourceExtraInfo($val['resource_id']);
          //通过比赛判定红黑单
          $resourceScheduleList = $resourceModel->getResourceScheduleList($val['resource_id']);

          $resourceInfo = array(
            'resource_id' => $val['resource_id'],
            'title' => $val['title'],
            'resource_type' => $val['resource_type'],
            'is_groupbuy' => $val['is_groupbuy'],
            'is_limited' => $val['is_limited'],
            'is_schedule_over' => $val['is_schedule_over'],
            'price' => $resourceModel->ncPriceFen2Yuan($val['price']),
            'price_int' => $resourceModel->ncPriceFen2YuanInt($val['price']),
            'release_time_friendly' => $resourceModel->friendlyDate($val['release_time']),
           // 'create_time' => $val['create_time'],
            'create_time' => $val['r_create_time'],
            'stat_time' => $val['create_time'],
            'limited_time_friendly' => $resourceModel->friendlyDate($val['limited_time'], 'full'),
            'create_time_friendly' => $resourceModel->friendlyDate($val['r_create_time']),
            'sold_num' => $resourceExtraInfo['sold_num'] + $resourceExtraInfo['cron_sold_num'],
            'thresh_num' => $resourceExtraInfo['thresh_num'],
            'bet_status' => $resourceExtraInfo['bet_status'],
            'schedule' => $resourceScheduleList,
            'expert' => $expertInfo,
			//'view_num'=>$viewNum?$viewNum:0
			'view_num'=>$resourceExtraInfo['view_num']
          );

          if ($is_free) {
              $surfaces = $resourceModel->getResourceStaticList($val['resource_id']);
              $surface = $surfaces[0]['url'] ?: '';
              $resourceInfo['surface'] = $surface;
          }
          if ($val['is_groupbuy'] == 1) {
            $resourceInfo['group'] = $resourceModel->getResourceGroupInfo($val['resource_id']);
          }
          $result[] = $resourceInfo;
          //$resourceModel->addCronSoldNum($resourceInfo);
        }
      }
      $this->responseJson($result);
    }

    public function freeResourcesCount() {
      $param = $this->checkApiParam([], ['platform' => 1]);

      $platform = intval($param['platform']);
      $resourceModel = new ResourceModel();
      $count = $resourceModel->getFreeResourcesCount($platform);
      $this->responseJson(['count' => $count]);
    }

    /**
     * 首页N条新方案
     */
    public function newRecommendList(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], ['start_time' => time(), 'is_new' => 0, 'platform' => 2, 'is_free' => 1]);
        $is_new = intval($param['is_new']);
        $userId = intval($param['user_id']);
        $startTime = intval($param['start_time']);
        $platform = intval($param['platform']);
        $is_free = intval($param['is_free']);

        $resourceModel = new ResourceModel();
        $newRecommendList = $resourceModel->getNewRecommendResourceList($startTime, $platform, $is_free);
        if(!empty($newRecommendList)){
            $expertExtraModel = new ExpertExtraModel();
            $betRecordModel = new BetRecordModel();
            $expertModel = new ExpertModel();
            $userFollowModel = new UserFollowModel();
            $userSubscribeModel = new UserSubscribeModel();
            foreach($newRecommendList as $key => $val){
                $isFollowExpert = $isSubscribeExpert = 0;
                $expertId = $val['expert_id'];
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

                if($userId){
                    //检查用户是否关注专家
                    $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
                    //检查用户是否订阅
                    //$isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
                }

                $expertInfo['is_follow_expert'] = $isFollowExpert;
                $expertInfo['is_subscribe_expert'] = $isSubscribeExpert;
                $expertInfo['max_bet_record'] = $is_new ? intval($expertExtraInfo['max_bet_record_v2']) : intval($expertExtraInfo['max_bet_record']);
                $expertInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId);
                $newRecommendList[$key]['expert'] = $expertInfo;
                if ($val['is_groupbuy'] == 1) {
                  $newRecommendList[$key]['group'] = $resourceModel->getResourceGroupInfo($val['resource_id']);
                }
                $resourceModel->addCronSoldNum($newRecommendList[$key]);
            }
        }
        $this->responseJson($newRecommendList);
    }

    /**
     * 获取料详情
     */
    public function resourceInfo(){
      $param = $this->checkApiParam(['resource_id'], ['user_id' => 0, 'is_new' => 0]);
      $isNew = intval($param['is_new']);    //命中率需求更改，兼容新旧版本

        $userId = intval($param['user_id']);
        $resourceId = intval($param['resource_id']);

        if($userId){
            $this->checkToken();
        }

        $resourceModel = new ResourceModel();
        //获取料详情
        $resourceInfo = $resourceModel->getResourceDetailedInfo($resourceId, $userId);
        $resourceModel->addCronSoldNum($resourceInfo);

        //增加浏览量
        $resourceModel->setResourceViewToRedis($resourceId);

        if(empty($resourceInfo) || $resourceInfo['resource_status'] != 1){
            //料信息不存在
            $this->responseJsonError(2001);
        }

        $expertId = $resourceInfo['expert_id'];

        //获取专家信息
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($expertId);
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

          $recent_red = $recent_record = '';
          if ($expertExtraInfo['recent_red'] >= 3) {
              $recent_red = $expertExtraInfo['recent_red'];
          }
          if ($expertExtraInfo['recent_record']) {
              $tmp_recent_record = json_decode($expertExtraInfo['recent_record'], 1);
              if ($tmp_recent_record[1]) {
                  if ($tmp_recent_record[1][2] >= 80) {
                      $recent_record = '近' . $tmp_recent_record[1][1] . '中' . $tmp_recent_record[1][0];
                  }
              }
          }

        $expertInfo['is_follow_expert'] = 0;
        $expertInfo['profit_rate'] = intval($expertExtraInfo['profit_rate']);
        $expertInfo['profit_all'] = intval($expertExtraInfo['profit_all']);
        $expertInfo['max_red_num'] = intval($expertExtraInfo['max_red_num']);
        $expertInfo['max_bet_record'] = intval($expertExtraInfo['max_bet_record']);
        $expertInfo['recent_red'] = $recent_red;
        $expertInfo['recent_record'] = $recent_record;
        if($isNew != 0) {
          $expertInfo['max_bet_record'] = intval($expertExtraInfo['max_bet_record_v2']);
        }
        unset($expertInfo['phone']);
        unset($expertInfo['idcard_number']);
        unset($expertInfo['real_name']);

        //是否购买  1:已购买  0:未购买
        $isBuy = 0;

        //已判定红黑单，直接查看
        //判断是否全部判完单
        $is_over_bet = 1;
        $schedule_list = [];
        if ($resourceInfo['detail']) {
          foreach ($resourceInfo['detail'] as $v) {
            $schedule_list = $v['schedule'];
            if (!$schedule_list) {
                $is_over_bet = 0;
            }
            $all_bet_status = array_column($v['schedule'], 'bet_status');
            if (!empty($v['schedule']) && in_array(0, $all_bet_status) && $resourceInfo['bet_status'] == 0) {
              $is_over_bet = 0;
            }
          }
        }

        if (!empty($schedule_list) && $resourceInfo['bet_status'] == 0) {
            $resourceInfo['bet_status'] = $resourceModel->getBetStatus($schedule_list);    //判定整个方案的红黑状态
        }

        if ($is_over_bet || $resourceInfo['price'] == 0 || $resourceInfo['is_free'] == 1  || $resourceInfo['bet_status'] > 0){
            $isBuy = 1;
        }

        $userIsSubscribe = 0;
        if($userId){
            //检测用户是否购买过此料
          $orderModel = new OrderModel();
            $userIsBuyResource = $orderModel->checkUserBuyV2($userId, $resourceId);
            //$userIsBuyResource = $orderModel->checkUserIsBuyResource($userId, $resourceId);
            if($userIsBuyResource){
                $isBuy = 1;
            }
            //检测是否订阅此专家
            $userSubscribeModel = new UserSubscribeModel();
            $userIsSubscribe = $userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
            if($userIsSubscribe){
                $isBuy = 1;
            }

            // 检测是否专家查看自己的料
            //if($userId == $resourceInfo['expert_id']){
            if($userId == $expertInfo['user_id']){
                $isBuy = 1;
                $userIsSubscribe = 1;
            }
            $userFollowModel = new UserFollowModel();
            //检查用户是否关注专家
            $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
            $expertInfo['is_follow_expert'] = $isFollowExpert;
        }

        //获取专家30日订阅价格
        $expertSubscribeModel = new ExpertSubscribeModel();
        $expertSubscribe = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30);

        // 是否需要删除料内容
        $clearContent = 0;

        // 如果是合买料
        if ($resourceInfo['is_groupbuy'] === 1) {
            $groupInfo = $resourceModel->getResourceGroupInfo($resourceId);
            // 购买人数不应显示超过合买成功人数
            $resourceInfo['sold_num'] = min($resourceInfo['sold_num'], $groupInfo['num']);
            $groupInfo['need_num'] = $groupInfo['num'] - $resourceInfo['sold_num'];
            // 整理对用户显示的料界面
            if (!$isBuy && $groupInfo['status'] == 0) {
                // 未购买，团购中
                $show_status = 1;
                $clearContent = 1;
            } elseif ($isBuy && $groupInfo['status'] == 0) {
                // 已购买，团购中
                $show_status = 2;
                $clearContent = 1;
            } elseif (($isBuy && $groupInfo['status'] == 1) || $resourceInfo['bet_status'] != 0) {
                // 已购买，合买成功
                // 判定红黑
                $show_status = 4;

            } else {
                // 未购买，合买失败
                // 已购买，合买失败
                // 未购买，合买成功
                $show_status = 3;
                $clearContent = 1;
            }

            $groupInfo['show_status'] = $show_status;

            $resourceInfo['group'] = $groupInfo;

        }
        $isLimited = 0;
        $limitedTime = '';
        if($resourceInfo['is_limited'] && $resourceInfo['limited_time'] > time()){
            $isLimited = 1;
            $clearContent = 1;
            $limitedTime = date("m月d日 H:i", $resourceInfo['limited_time']);
        }
        
        
        if(!$isBuy || $clearContent){
            //未购买，处理数据，干掉内容和附件
            foreach($resourceInfo['detail'] as $key => $val){
                    $resourceInfo['detail'][$key]['static'] = [];
                    $resourceInfo['detail'][$key]['content'] = '';
                    $resourceInfo['detail'][$key]['new_content'] = [];
                    $resourceInfo['detail'][$key]['is_new_style'] = 0;
                    if ($resourceInfo['create_time'] > 1573613708) {
                        $resourceInfo['detail'][$key]['is_new_style'] = 1;
                    }
                }
        } else {
            foreach($resourceInfo['detail'] as $key => $val){
                    $resourceInfo['detail'][$key]['is_new_style'] = 0;
                    if ($resourceInfo['is_expert']) {
                        $app_texts = $this->dealContentForAppExpert($resourceInfo['detail'][$key]['content']);
                    } else {
                        $app_texts = $this->dealContentForApp($resourceInfo['detail'][$key]['content']);
                    }
                    $resourceInfo['detail'][$key]['new_content'] = $app_texts;
                    $content_static = $this->dealContent2OldForApp($app_texts);
                    $resourceInfo['detail'][$key]['content'] = $content_static['content'];
                    if (empty($resourceInfo['detail'][$key]['static'])) {
                        $resourceInfo['detail'][$key]['static'] = $content_static['static'];
                    }
                    if ($resourceInfo['create_time'] > 1573613708) {
                        $resourceInfo['detail'][$key]['is_new_style'] = 1;
                    }
            }
        }
        $data = [];
        $data['is_buy'] = $isBuy;
        $data['is_subscribe'] = intval($userIsSubscribe);
        $data['subscribe_price'] = $expertSubscribe['subscribe_price'];
        $data['is_limited'] = $isLimited;
        $data['limited_time'] = $limitedTime;
        $data['expert'] = $expertInfo;
        $data['resource'] = $resourceInfo;
        $soccer_model = new SoccerModel();
	      $basketballModel = new BasketballModel();
        $match_infos = [];
        if ($data['resource']['detail']) {
            foreach ($data['resource']['detail'] as $v) {
                if (!$v['schedule']) {
                     continue;
                }
                foreach ($v['schedule'] as $sv) {
                    if (!$sv['match_num']) {
                        continue;
                    }

                    $match_num = $sv['match_num'];
                    $match_info = [];
                    $match_info = $soccer_model->nowInfo($match_num, $user_id);
                    $history = $soccer_model->getHistory($match_num);
                    $match_info = array_merge($match_info, $history);
                    $match_info['match_type'] = 1;
                    
                    //未购买,获取对应的实时赔率
                    $lotteryInfo = $soccer_model->getLotteryInfo($match_num);
                    $match_info['is_signle'] = 0;
                    $match_info['lottery_tag'] = '';
                    $match_info['odds'] = [];
                    if (!empty($lotteryInfo)) {
                      $match_info['is_signle'] = $lotteryInfo['is_signle'];
                      if ($lotteryInfo['lottery_type'] == 1) {
                        $match_info['lottery_tag'] = '竞彩' . sprintf('%03d', substr($lotteryInfo['lottery_num'], -3));
                      } else {
                        $match_info['lottery_tag'] = '北单' . sprintf('%03d', explode('-', $lotteryInfo['lottery_num'])[1]);
                      }
                        if ($resourceInfo['create_time'] > 1573613708) {
                            if ($match_info['is_jc'] || $match_info['is_bd']) {
                                if (in_array($sv['lottery_type'], [1 , 2])) {
                                    $match_info['odds'] = $lotteryInfo['odds'];
                                }
                            }
                        }
                    }
                    if($isBuy){
                      //获取专家推荐的赔率
                      $lotteryInfo = $resourceModel->getResourceScheduleInfo(['resource_id' => $resourceId, 'schedule_id' => $match_num, 'type' => 1]);
                      $recommendInfo = explode(',', $lotteryInfo['recommend']);
                      $lotteryInfo['main_recommend'] = $recommendInfo[0];
                      $lotteryInfo['extra_recommend'] = isset($recommendInfo[1]) ? $recommendInfo[1] : '';
                      $lotteryInfo['lottery_result'] = $this->lottery_result_map[$lotteryInfo['lottery_result']];
                      $lotteryInfo['d'] = empty($lotteryInfo['d']) ? '-' : $lotteryInfo['d'];
                        if ($resourceInfo['create_time'] > 1573613708) {
                            if ($match_info['is_jc'] || $match_info['is_bd']) {
                                if (in_array($sv['lottery_type'], [1 , 2])) {
                                    $match_info['odds'] = [$lotteryInfo];
                                }
                            }
                        }                                                                                                                                                                         
                    }                                                                                                                                             

                    if ($sv['match_type'] == 2) { //篮球
                        $match_info = [];
                        $match_info = $basketballModel->matchInfo($match_num, $user_id);
                        $history = $basketballModel->getHistory($match_num);
                        $match_info = array_merge($match_info, $history);
                        $match_info['match_type'] = 2;                            
        
                        //未购买,获取对应的实时赔率
                        $lotteryInfo = $basketballModel->getLotteryInfo($match_num);
                        $match_info['is_signle'] = 0;
                        $match_info['lottery_tag'] = '';
                        $match_info['odds'] = [];
                        if (!empty($lotteryInfo)) {
                            $match_info['is_signle'] = $lotteryInfo['is_signle'];
                            if ($lotteryInfo['lottery_type'] == 1) {
                                $match_info['lottery_tag'] = trim($lotteryInfo['lottery_num']);
                            } else {
                                $match_info['lottery_tag'] = '北单' . sprintf('%03d', explode('-', $lotteryInfo['lottery_num'])[1]);
                            }

                            if ($resourceInfo['create_time'] > 1573613708) {
                                if ($match_info['is_jc'] || $match_info['is_bd']) {
                                    if (in_array($sv['lottery_type'], [1 , 2])) {
                                        $match_info['odds'] = $lotteryInfo['odds'];
                                    }
                                }
                            }                                                            
                        }

                        if($isBuy) {
                            $lotteryInfo = $resourceModel->getResourceScheduleInfo(['resource_id' => $resourceId, 'schedule_id' => $match_num, 'type' => 2]);
                            $recommendInfo = explode(',', $lotteryInfo['recommend']);
                            $lotteryInfo['main_recommend'] = $recommendInfo[0];
                            $lotteryInfo['extra_recommend'] = isset($recommendInfo[1]) ? $recommendInfo[1] : '';
                            $lotteryInfo['lottery_result'] = $this->lottery_result_map[$lotteryInfo['lottery_result']];
                            $lotteryInfo['play_method'] = $lotteryInfo['d']; //篮球时此字段代表玩法:1让分,2让分胜负,3大小分
                            $lotteryInfo['d'] = ''; 
                            if ($resourceInfo['create_time'] > 1573613708) {
                                if ($match_info['is_jc'] || $match_info['is_bd']) {
                                    if (in_array($sv['lottery_type'], [1 , 2])) {
                                        $match_info['odds'] = [$lotteryInfo];
                                    }
                                }
                            }
                        }
		            }
                    $match_infos[] = $match_info;
                }
            }
        }
        $data['match_info'] = $match_infos;
        $this->responseJson($data);
    }

    /**
     * 获取专家未完赛列表
     */
    public function expertUnfinishedResourceList() {
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0]);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);

        if($userId){
            $this->checkToken();
        }

        $resourceModel = new ResourceModel();
        //获取料列表
        $resourceList = $resourceModel->getResourceListByExpertId2($expertId, 1, 0);
        foreach ($resourceList as &$resource) {
            $resourceModel->addCronSoldNum($resource);
        }

        $this->responseJson($resourceList);
    }

    /**
     * 获取专家完赛列表
     */
    public function expertFinishedResourceList() {
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0, 'page' => 1, 'pagesize' => 10]);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);

        if($userId){
            $this->checkToken();
        }

        $resourceModel = new ResourceModel();
        //获取料列表
        $resourceList = $resourceModel->getResourceListByExpertId2($expertId, 1, 1, $page, $pageSize);
        foreach ($resourceList as &$resource) {
            $resourceModel->addCronSoldNum($resource);
        }

        $this->responseJson($resourceList);
    }

    public function getExpertResourceListV2() {
        $param = $this->checkApiParam(['expert_id', 'platform'], ['user_id' => 0, 'page' => 1, 'pagesize' => 10, 'finished' => 0]);
        $userId = intval($param['user_id']);
        if($userId){
            $this->checkToken();
        }

        $expertId = intval($param['expert_id']);
        $page = intval($param['page']);
        $pageSize = intval($param['pagesize']);
        $platform = intval($param['platform']);
        $finished = intval($param['finished']);

        $resourceModel = new ResourceModel();
        //获取料列表
        $resourceList = $resourceModel->getExpertResourceList($expertId, $platform, 1, $finished, $page, $pageSize);
        foreach ($resourceList as &$resource) {
            $resourceModel->addCronSoldNum($resource);
        }

        $this->responseJson($resourceList);
    }

    public function getAttentionList() {
      $param = $this->checkApiParam(['user_id'], ['page' => 1, 'pagesize' => 20, 'platform' => 2]);
      $userId = intval($param['user_id']);
      $page = intval($param['page']);
      $pagesize = intval($param['pagesize']);
      $platform = intval($param['platform']);
      if($userId){
        $this->checkToken();
      }
       $RedisModel=new RedisModel("resource");
      $redisKey = RESOURCE_VIEW;
      $resourceModel = new ResourceModel();
	    $userFollowModel = new UserFollowModel();
	    $expertModel = new ExpertModel();
	    $betRecordModel = new BetRecordModel();
      $condition = ['user_id' => $userId, 'status' => 1];
      $orderBy = ['ctime' => 'desc'];
      $data = $resourceModel->getAttentionList($condition, [], $page, $pagesize, $orderBy);
      $result = [];
      foreach($data as $value) {
        $baseInfo = $resourceModel->getResourceBriefInfo($value['resource_id'], true);
		//$viewNum = $RedisModel->redisGetHashList($redisKey, $value['resource_id']);//浏览量
        $expertId = $baseInfo['expert_id'];
        $isFollowExpert = 0;
        if($userId){
          $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
        }
        $expert = $expertModel->expertInfoV2($expertId);
        $expertInfo = array(
          'expert_id' => $expertId,
          'expert_name' => $expert['expert_name'],
          'real_name' => $expert['real_name'],
          'headimgurl' => $expert['headimgurl'],
          'phone' => $expert['phone'],
          'platform' => $expert['platform'],
          'tag' => $expert['tag'],
          'push_resource_time' => $expert['push_resource_time'],
          'identity_desc' => $expert['identity_desc'],
          'is_follow_expert' => $isFollowExpert,
          'max_bet_record' => $expert['max_bet_record_v2']>=60?$expert['max_bet_record_v2']:'--',
          'create_time' => $expert['create_time'],
          'combat_gains_ten' => $betRecordModel->nearTenScore($expertId, $platform),
          'lately_red'=>$expert['lately_red'],
          'max_red_num'=>$expert['max_red_num'],

        );

        $resourceScheduleList = $resourceModel->getResourceScheduleList($baseInfo['resource_id']);
        $bet_status = $resourceModel->getBetStatus($resourceScheduleList);
		
		if(empty($resourceScheduleList)){
                $bet_status=$baseInfo['bet_status'];
        }
        //如果有手动判的 已手动判的为准
        if ($baseInfo['bet_status']) {
                $bet_status=$baseInfo['bet_status'];
        }
        $resourceInfo = array(
          'resource_id' => $baseInfo['resource_id'],
          'title' => $baseInfo['title'],
          'resource_type' => $baseInfo['resource_type'],
          'is_groupbuy' => $baseInfo['is_groupbuy'],
          'is_limited' => $baseInfo['is_limited'],
          'is_schedule_over' => $baseInfo['is_schedule_over'],
          'price' => $baseInfo['price'],
          'is_free' => $baseInfo['is_free'],
          'price_int' => $baseInfo['price_int'],
          'release_time_friendly' => $baseInfo['release_time_friendly'],
          'create_time' => $baseInfo['create_time'],
          'stat_time' => $baseInfo['create_time'],
          'limited_time_friendly' => $baseInfo['limited_time_friendly'],
          'create_time_friendly' => $baseInfo['create_time_friendly'],
          'bet_status' => $bet_status,
          'sold_num' => $baseInfo['sold_num'] + $baseInfo['cron_sold_num'],
          'thresh_num' => $baseInfo['thresh_num'],
          'schedule' => $resourceScheduleList,
          'expert' => $expertInfo,
		 // 'view_num'=>$viewNum?$viewNum:0
		  'view_num'=>$baseInfo['view_num']
        );
        if ($baseInfo['is_groupbuy'] == 1) {
          $resourceInfo['group'] = $resourceModel->getResourceGroupInfo($baseInfo['resource_id']);
        }
        $result[] = $resourceInfo;
      }
      $total = $resourceModel->getAttentionCount($condition);
      $this->responseJson(['total' => $total[0]['count'], 'list' => $result]);
    }

    public function setAttentStatus() {
      $param = $this->checkApiParam(['user_id', 'resource_id', 'status'], []);
      $userId = intval($param['user_id']);
      $resourceId = intval($param['resource_id']);
      $status = intval($param['status']);
      if($userId){
        $this->checkToken();
      }

      $resourceModel = new ResourceModel();
      $res = $resourceModel->setAttentStatus($resourceId, $userId, $status);
      return $this->responseJson();
    }

    /*
     * 我关注专家的料---新增
     *
     */
    public function getMyExpertResourceList(){
        $param = $this->checkApiParam(['user_id'], [ 'page' => 1, 'pagesize' => 5, 'order_by' => 1,'platform'=>1]);
        $userId = intval($param['user_id']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
		    $platform = intval($param['platform']);
        if($userId){
            // $this->checkToken();
        }else{
            $this->responseJson();
        }

        $resourceModel = new ResourceModel();
        $recommendList = $resourceModel->getMyExpertResourceList($userId,$page, $pagesize,$platform);

        if(!empty($recommendList)){

            $expertExtraModel = new ExpertExtraModel();
            $betRecordModel = new BetRecordModel();
            $expertModel = new ExpertModel();
            $userFollowModel = new UserFollowModel();
            //$userSubscribeModel = new UserSubscribeModel();
            foreach($recommendList as $key => $val){
                $isFollowExpert = $isSubscribeExpert = 0;
                $expertId = $val['expert_id'];
                $expertInfo = $expertModel->getExpertInfo($expertId);
                $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

                if($userId){
                    //检查用户是否关注专家
                    $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
                    //检查用户是否订阅
                    //$isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
                }

                $expertInfo['is_follow_expert'] = $isFollowExpert;
                $expertInfo['is_subscribe_expert'] = $isSubscribeExpert;
                $expertInfo['max_bet_record'] = intval($expertExtraInfo['max_bet_record'])>=60?intval($expertExtraInfo['max_bet_record']):'--';
                $expertInfo['combat_gains_ten'] = $betRecordModel->nearTenScore($expertId);
                $recommendList[$key]['expert'] = $expertInfo;
                $resourceModel->addCronSoldNum($recommendList[$key]);
            }
        }

        $this->responseJson($recommendList);
    }

    private function dealContentForAppExpert($content, $b = false, $h = true, $result = []) {
        $deletes = ["<div class=\"media-wrap image-wrap\">", "</div>"];
        foreach ($deletes as $item) {
            $content = str_replace($item, '', $content);    
        }
        $model = ['text' => '', 'b' => $b, 'h' => $h];
        $img = ['img' => '', 'b' => false, 'h' => true];
        $length = strlen($content);
        $key_mark = strpos($content, '<');
        if ($key_mark === false) {
            $model['text'] = $content;
            $model['h'] = $h;
            $result[] = $model;
            return $result;
        }
        $left_mark = strpos($content, '>');
        $mark_length = $left_mark - $key_mark - 1;
        $mark = substr($content, $key_mark + 1, $mark_length);
        $left = substr($content, 0, $key_mark);
        if ($left) {
            $model['text'] = $left;
            $model['h'] = false;
            $result[] = $model;
        }
        switch ($mark) {
        case 'p':
            $left_mark = strpos($content, '<p>') + 3;
            $right_mark = strpos($content, "</p>");
            $p_text_length = $right_mark - $left_mark;
            $last_mark = $right_mark + 4;
            $last_length = $length - $last_mark;
            if ($last_length > 0) {
                $last = substr($content, $last_mark, $last_length);
            }
            if ($p_text_length > 0) {
                $p_text = substr($content, $left_mark, $p_text_length);
                $result = $this->dealContentForAppExpert($p_text, false, true, $result);
            } elseif ($p_text_length == 0) {
                $model['text'] = '';
                $model['b'] = false;
                $model['h'] = true;
                $result[] = $model;
            }
            if ($last) {
                $result = $this->dealContentForAppExpert($last, false, true, $result);
            }
            break;
        case 'strong':
            $left_mark = strpos($content, '<strong>') + 8;
            $right_mark = strpos($content, "</strong>");
            $s_text_length = $right_mark - $left_mark;
            $last_mark = $right_mark + 9;
            $last_length = $length - $last_mark;
            if ($last_length > 0) {
                $last = substr($content, $last_mark, $last_length);
            }
            if ($s_text_length > 0) {
                $s_text = substr($content, $left_mark, $s_text_length);
                if ($last) {
                    $result = $this->dealContentForAppExpert($s_text, true, false, $result);
                } else {
                    $result = $this->dealContentForAppExpert($s_text, true, $h, $result);
                }
            }
            if ($last) {
                $result = $this->dealContentForAppExpert($last, false, true, $result);
            }
            break;
        default:
            $mark = substr($content, $key_mark + 1, 3);
            if ($mark == 'img') {
                $left_mark = $key_mark;
                $right_mark = strpos($content, "/>");
                $img_text_length = $right_mark - $left_mark;
                $last_mark = $right_mark + 2;
                $last_length = $length - $last_mark;
                if ($last_length > 0) {
                    $last = substr($content, $last_mark, $last_length);
                }
                if ($img_text_length > 0) {
                    $img_text = substr($content, $left_mark, $img_text_length);
                    $pattern = "/src=[\'\"]?([^\'\"]*)[\'\"]?/i";
                    preg_match_all($pattern, $img_text, $imgs);
                    if ($imgs) {
                        $img['img'] = $imgs[1][0];
                        $result[] = $img;
                    }
                }
                if ($last) {
                    $result = $this->dealContentForAppExpert($last, false, true, $result);
                }
            }
            break;
        }
        return $result;
    }

    //private function dealContent2OldForAppExpert($content) {
    //    $static = [];
    //    $deletes = ["<p>", "</p>", "<strong>", "</strong>", "<img", "/>", "<div class=\"media-wrap image-wrap\">", "</div>"];
    //    $pattern = "/src=[\'\"]?([^\'\"]*)[\'\"]?/i";
    //    preg_match_all($pattern, $content, $imgs);
    //    if ($imgs[0]) {
    //        foreach ($imgs[0] as $k => $img) {
    //            $content = str_replace($img, '', $content);
    //            $tmp_static = [];
    //            $tmp_static['static_type'] = 1;
    //            $tmp_static['url'] = $imgs[1][$k];
    //            $static[] = $tmp_static;
    //        }
    //    }
    //    foreach ($deletes as $del) {
    //        $content = str_replace($del, '', $content);
    //    }
    //    return ['content' => $content, 'static' => $static];
    //}

    private function dealContent2OldForApp($texts) {
        $static = [];
        $content = '';
        foreach ($texts as $item) {
            if (isset($item['text'])) {
                $content .= $item['text'];
            }
            if (isset($item['img'])) {
                $tmp_static = [];
                $tmp_static['static_type'] = 1;
                $tmp_static['url'] = $item['img'];
                $static[] = $tmp_static;
            }
        }
        return ['content' => $content, 'static' => $static];
    }

    private function dealContentForApp($content) {
        $delete_arr = ["<!DOCTYPE html>", "<html>", "<head>", "</head>", "<body>", "</body>", "</html>", "<br />"];
        foreach ($delete_arr as $del_s) {
                $content = str_replace($del_s, '', $content);
        }
        $original_arr = explode("\n", $content);
        $result = [];
        foreach ($original_arr as $item) {
            if (empty($item)) {
                continue;
            }
            if (in_array($item, $delete_arr)) {
                continue;
            }
            //$item .= "[dddd]";
            $item = $this->dealSpecialCharacter($item);
            //var_dump($this->dealSentence($item));die;
            $tmp = $this->dealSentence($item);
            $result = array_merge($result, $tmp);
        }
        foreach ($result as $k => $v) {
            $result[$k]['text'] = $this->recoverSpecialCharacter($v['text']);
        }
        return $result;
    }

    private function dealSentence($str, $b = false, $h = true, $result = []) {
        $model = ['text' => '', 'b' => $b, 'h' => $h];
        $img = ['img' => '', 'b' => false, 'h' => $h];
        $length = strlen($str);
        $key_mark = strpos($str, '[');
        if ($key_mark === false) {
            $model['text'] = $str;
            $model['h'] = $h;
            $result[] = $model;
            return $result;
        }
        $left_mark = strpos($str, ']');
        $mark_length = $left_mark - $key_mark - 1;
        $mark = substr($str, $key_mark + 1, $mark_length);
        $left = substr($str, 0, $key_mark);
        if ($left) {
            $model['text'] = $left;
            $model['h'] = false;
            $result[] = $model;
        }
        if ($mark == 'b') {
            $left_mark = strpos($str, '[b]') + 3;
            $right_mark = strpos($str, "[/b]");
            $b_text_length = $right_mark - $left_mark;
            $last_mark = $right_mark + 4;
            $last_length = $length - $last_mark;
            if ($last_length > 0) {
                $last = substr($str, $last_mark, $last_length);
            }
            if ($b_text_length > 0) {
                $b_text = substr($str, $left_mark, $b_text_length);
                if ($last) {
                    $result = $this->dealSentence($b_text, true, false, $result);
                } else {
                    $result = $this->dealSentence($b_text, true, true, $result);
                }
            }
            if ($last) {
                $result = $this->dealSentence($last, false, true, $result);
            }
        } elseif ($mark == 'img') {
            $left_mark = strpos($str, '[img]') + 5;
            $right_mark = strpos($str, "[/img]");
            $img_url_length = $right_mark - $left_mark;
            $last_mark = $right_mark + 6;
            $last_length = $length - $last_mark;
            if ($last_length > 0) {
                $last = substr($str, $last_mark, $last_length);
            }
            if ($img_url_length > 0) {
                $img_url = substr($str, $left_mark, $img_url_length);
                $img['img'] = $img_url;
                if ($last) {
                    $img['h'] = false;
                }
                $result[] = $img;
            }
            if ($last) {
                $result = $this->dealSentence($last, $b, $h, $result);
            }
        }
        return $result;
    }

    private function dealSpecialCharacter($content, $is_expert = 0) {
        if (!$is_expert) {
            $character = ['[', ']'];
            $length = strlen($content);
            for ($i = 0; $i++ ; $i < $length) {
                $item = $next = $nextnext = $previous = $preprevious = '';
                $item = $content[$i];
                $next = $content[$i + 1];
                $previous = $content[$i - 1];
                $nextnext = $content[$i + 2];
                $preprevious = $content[$i - 2];
                if ($item == $character[0] && $next == 'b' && $nextnext == $character[1]) {
                    continue;
                }
                if ($item == $character[0] && $next == 'i' && $nextnext == 'm') {
                    continue;
                }
                if ($item == $character[0] && $next == '/' && $nextnext == 'i') {
                    continue;
                }
                if ($item == $character[0] && $next == '/' && $nextnext == 'b') {
                    continue;
                }
                if ($item == $character[1] && $previous == 'b' && $preprevious == '/') {
                    continue;
                }
                if ($item == $character[1] && $previous == 'b' && $preprevious == $character[0]) {
                    continue;
                }
                if ($item == $character[1] && $previous == 'g' && $preprevious == 'm') {
                    continue;
                }
                if ($item == $character[0]) {
                    $content[$i] = '「';
                }  elseif ($item == $character[1]) {
                    $content[$i] = '」';
                }
            }
        }
        return $content;
    }

    private function recoverSpecialCharacter($content) {
        $content = str_replace('「', '[', $content);
        $content = str_replace('」', ']', $content);
        return $content;
    }

}
