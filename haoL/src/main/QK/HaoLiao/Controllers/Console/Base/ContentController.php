<?php
/**
 * 内容管理
 * User: WangHui
 * Date: 2018/11/5
 * Time: 上午10:21
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\BetRecordModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertMoneyChangeModel;
use QK\HaoLiao\Model\MatchModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\RedisKeyManageModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\StatModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Model\PushMsgModel;
use QK\HaoLiao\Common\CommonHandler;

class ContentController extends ConsoleController {
    private $play_method = [1 => '主队', 2 => '主队', 3 => '大小分'];
    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
    }

    public function syncData() {
        $resource_model = new ResourceModel($this->_appSetting);
        $resource_model->syncData();
    }
    /**
     * 料列表
     */
    public function resourceList() {
        $params = $this->checkApiParam([], ['query' => "",'order'=>'', 'page' => 1, 'pagesize' => 10]);
        $query = json_decode($params['query'],1);
        $order = json_decode($params['order'],1);
        $page = $params['page'];
        $size = $params['pagesize'];
        $resourceModel = new ResourceModel();
        $list = $resourceModel->getResourceList($query, $page, $size,$order);
        if(!empty($list)){
            $expertModel = new ExpertModel();
            foreach($list as $key => $val){
                $expertInfo = $expertModel->getExpertInfo($val['expert_id']);
                $list[$key]['expert_name'] = $expertInfo['expert_name'];

                $sortVal = intval(floor($val['sort']/100000000));
                $sortRule = array(
                    0 => 0,
                    1 => 10,
                    2 => 9,
                    3 => 8,
                    4 => 7,
                    5 => 6,
                    6 => 5,
                    7 => 4,
                    8 => 3,
                    9 => 2,
                    10 => 1
                );
                $list[$key]['sort'] = $sortRule[$sortVal];
            }
        }
        $data['list'] = $list;
        $data['count'] = $resourceModel->resourceListCount($query);
        $this->responseJson($data);
    }

    /**
     * 料详情
     */
    public function resourceDetail() {
        $params = $this->checkApiParam(['resource_id']);
        $resourceId = $params['resource_id'];
        $resourceModel = new ResourceModel();
        $info = $resourceModel->getResourceDetailedInfo($resourceId);
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($info['expert_id']);
        if ($info['is_groupbuy'] == 1) {
            $groupInfo = $resourceModel->getResourceGroupInfo($resourceId);
            $info['groupbuy_num'] = $groupInfo['num'];
            $info['groupbuy_price'] = $groupInfo['price'];
        }
        $finalExpertInfo['expert_id'] = $info['expert_id'];
        $finalExpertInfo['expert_name'] = $expertInfo['expert_name'];
        $finalExpertInfo['headimgurl'] = $expertInfo['headimgurl'];
        $data['expert'] = $finalExpertInfo;
        $data['resource'] = $info;
        $this->responseJson($data);
    }

    /**
     * 料推荐
     */
    public function resourceRecommend() {
        $params = $this->checkApiParam(['resource_id', 'recommend_desc']);
        $resourceId = $params['resource_id'];
        $desc = $params['recommend_desc'];
        $resourceModel = new ResourceModel();
        $params['recommend_desc'] = $desc;
        $resourceModel->updateResourceExtra($resourceId, $params);
        $this->responseJson();
    }

    public function refreshStatus() {
        $params = $this->checkApiParam(['resource_id']);
        $resource_id = $params['resource_id'];
        $resourceModel = new ResourceModel();
        $resourceModel->updateResourceExtra($resource_id, ['bet_status' => 0]);
        $this->responseJson();
    }

    /**
     * 设置红黑单
     * 1红单 2走单 3黑单
     */
    public function setRedStatus() {
        $params = $this->checkApiParam(['resource_id', 'bet_status']);
        $resourceId = $params['resource_id'];
        $betStatus = $params['bet_status'];
        $resourceModel = new ResourceModel();

        $resourceExtraInfo = $resourceModel->getResourceExtraInfo($resourceId);
        if($resourceExtraInfo['bet_status']){
            $this->responseJsonError(101, '不可重复判定红黑单');
        }

        $orderModel = new OrderModel();

        $params['bet_status'] = $betStatus;
        $resourceModel->updateResourceExtra($resourceId, $params);
        //判单之后取消置顶
        $resourceUpdateInfo = array('wx_placement' => 0, 'bd_placement' => 0,'sort'=>0);
        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        //手动判单-处理标记已完赛
       // $scheduleList = $resourceModel->getResourceScheduleList($resourceId);
        if(!$resourceInfo['is_schedule_over']){
            $resourceUpdateInfo['is_schedule_over'] = 1;
            $resourceUpdateInfo['schedule_over_date'] = strtotime(date("Y-m-d", time()));
            $resourceUpdateInfo['modify_time'] = time();
        }


        $resourceModel->updateResource($resourceId, $resourceUpdateInfo, false);


        if (($betStatus == 3 || $betStatus == 2) && $resourceInfo['resource_type'] == 2) {
            //黑单写入退款数据库
            $resourceModel->setRefundResource($resourceId);
        }
        $expertId = $resourceInfo['expert_id'];
        //更新专家信息
        $common = new CommonHandler();
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $prefix_url = $appSetting->getConstantSetting("UpdateExpertUrl");
        $url = $prefix_url . $expertId;
        $common->httpGet($url, []);

        //专家金额修改
        if ($resourceInfo['resource_type'] == 2) {
            //获取料金额价格，从冻结金额中减去。
            $resourceAmount = $orderModel->getResourceAmount($resourceId,$expertId);
            if($resourceAmount){
                if($betStatus==3 || $betStatus == 2){
                    //黑单处理冻结金额
                    //获取料金额价格，从冻结金额中减去。
                    $expertExtraIncOrDec['freezing'] = "-" . $resourceAmount;
                }
                if($betStatus==1){
                    $expertExtraIncOrDec['freezing'] = "-" . $resourceAmount;
                    $expertExtraIncOrDec['income'] = "+" . $resourceAmount;
                    //增加专家可提现
                    $expertExtraIncOrDec['balance'] = "+" . $resourceAmount;
                    //增加金额变更记录
                    $expertMoneyChangeModel = new ExpertMoneyChangeModel();
                    $expertMoneyChangeModel->setMoneyChange(0, $expertId, 1, 2, $resourceAmount);
                }
                $expertExtraModel = new ExpertExtraModel();
                $expertExtraModel->setExpertExtraIncOrDec($expertId, $expertExtraIncOrDec);
            }
            if($betStatus == 1){
                //进入红单列表
                $redisModel = new RedisModel("resource");
                $redisModel->redisLpush(RESOURCE_RED_LIST, $resourceId);
            }
        }
        //红黑数据 写入统计表
        $statTime = date("Y-m-d", $resourceInfo['stat_time']);
        //获取关联赛事类型
        $matchType = $resourceModel->getResourceMatchType($resourceId);

        $statModel = new StatModel();
        $statModel->betRecordStat($expertId, $statTime, $matchType, $betStatus);

        //连红数据落地
        //$expertExtraModel = new ExpertExtraModel();
        //if($betStatus==3){
        //    //重置为0
        //    $expertExtraIncOrDec['red_num'] = 0;
        //    $expertExtraModel->updateExtra($expertId,$expertExtraIncOrDec);
        //}

        //$expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

        //if($betStatus==1){
        //    $expertExtraData = [];
        //    $expertExtraData['red_num'] = $expertExtraInfo['red_num'] + 1;

        //    if($expertExtraInfo['max_red_num'] < $expertExtraData['red_num']){
        //        $expertExtraData['max_red_num'] = $expertExtraData['red_num'];
        //    }
        //    $expertExtraModel->updateExtra($expertId, $expertExtraData);
        //}

        ////近十场战绩删除
        $redisManageModel = new RedisKeyManageModel('betRecord');
        $redisManageModel->delExpertStat($expertId);
        ////盈利率--2019-12-04 使用新的计算规则
        //$profitInfo = $expertExtraModel->countProfitRate($expertExtraInfo, $resourceInfo['odds'], $betStatus);
        //if ($profitInfo) {
        //    $upExtraData['profit_rate'] = $profitInfo['profitRate'];
        //    //$upExtraData['profit_all'] = $profitInfo['profitAll'];
        //    $upExtraData['profit_resource_num'] = $profitInfo['profitResourceNum'];
        //}


        //// 更新所有命中率有序集合
        //$expertModel = new ExpertModel();
        //$betRecord = $expertModel->updateBetRecord($expertId);
        //$upExtraData['max_bet_record'] = $betRecord['max_bet_record'];
        //$expertExtraModel->updateExtra($expertId, $upExtraData);

        ////新版本命中率（近N场次的命中率）
        //$platform = $resourceInfo['bd_display'] ? 1 : 2;
        //$expertModel->calBetRecord($expertId, $platform);

        $this->responseJson();
    }

    /**
     * 联赛列表
     */
    public function leagueList() {
        $param = $this->checkApiParam([], ['match_type' => 1, 'start_time' => time(), 'end_time' => '']);
        $startTime = $param['start_time'];
        $endTime = $param['end_time'];
        $matchType = $param['match_type'];
        $matchModel = new MatchModel();
        $leagueList = $matchModel->leagueList($matchType, $startTime, 1, false, $endTime);
        $this->responseJson($leagueList);
    }

    /**
     * 全部联赛列表
     */
    public function leagueTotalList() {
        $param = $this->checkApiParam(['match_type']);
        $type = $param['match_type'];
        $matchModel = new MatchModel();
        $leagueList = $matchModel->leagueTotalList($type);
        $this->responseJson($leagueList);
    }

    /**
     * 赛事列表
     */
    public function scheduleList() {
        $param = $this->checkApiParam(['league_id'], ['match_type' => 1, 'start_time' => time(), 'page' => null, 'pagesize' => 0]);
        $matchType = intval($param['match_type']);
        $leagueId = intval($param['league_id']);
        $scheduleTime = date('Y-m-d', $param['start_time']);
        $scheduleTimeEnd = date('Y-m-d', $param['start_time'] + 86400);
        $page = null;
        $pagesize = intval($param['pagesize']);

        $matchModel = new MatchModel();
        $leagueList = $matchModel->getScheduleList($matchType, $leagueId, 1, null, $scheduleTime, $scheduleTimeEnd, $page, $pagesize);

        $this->responseJson($leagueList);
    }

    /**
     * 专家列表
     */
    public function expertList() {
        $expertModel = new ExpertModel();
        $list = $expertModel->expertList();
        $this->responseJson($list);
    }

    /**
     * 新建料
     * expert_id  专家id
     * title    料标题
     * price    料价格
     * resource_type    料类型 1:普通料  2:不中退款 3:包时段  4:限时单
     * recommend_desc   编辑推荐
     * odds 赔率
     * is_notice    是否发送通知
     * display_platform 展示平台
     * is_free  是否为免费料
     * is_limited   是否为临场展示
     * limited_time 临时展示时间
     * is_groupbuy  是否为合买
     * detail{
     * schedule 关联比赛 {
     * match_type 比赛类型  start_time 开始时间 league_id   联赛id  schedule_id 比赛编号
     * }
     * static   图片
     * content  内容
     * }
     */
    public function newResource() {
        $param = $this->checkApiParam(['expert_id', 'title', 'detail', 'price'], ['resource_id' => '', 'recommend_desc' => '', 'odds' => '', 'is_notice' => 0,  'is_limited' => 0, 'limited_time' => 0, 'is_groupbuy' => 0, 'groupbuy_price' => 0, 'groupbuy_num' => 0, 'is_free' => 0, 'resource_type' => 1, 'display_platform' => 0, 'is_expert' => 0, 'no_match' => 0, 'surface' => '', 'is_auto_bet' =>  1, 'remarks' => '', 'match_type' => 0]);
        if (empty($param['expert_id'])) {
            $this->responseJsonError(2009, '专家id不能为零');
            return false;
        }

        $resourceDetailArr = json_decode($param['detail'], true);
        $is_schedule_over = 0;
        if ($param['no_match']) {
            $is_schedule_over = 2;
            unset($resourceDetailArr['schedule']);
        }
        $resourceType = intval($param['resource_type']);
        $isNotice = intval($param['is_notice']);
        $isLimited = intval($param['is_limited']);
        $limitedTime = $param['limited_time'];
        $isGroupbuy  = intval($param['is_groupbuy']);  // 合买 0 => 非合买, 1 => 合买
        $groupbuyPrice = number_format($param['groupbuy_price'], 2, '.', '');  // 合买价格
        $groupbuyNum = intval($param['groupbuy_num']);  // 合买数量

        if (!$param['price']) {
            $param['is_free'] = 1;
        }
        if (empty($resourceDetailArr)) {
            if (!$no_match) {
            //请填写比赛内容
            $this->responseJsonError(2010);
            return false;
            }
        }

        if ($resourceType == 2) {
            //不中退款仅可关联单场比赛
            if (!$no_match) {
            if (count($resourceDetailArr['schedule']) > 1) {
                $this->responseJsonError(2011);
                return false;
            }
            }
        }

        if ($isGroupbuy === 1) {  // 如果为合买料
            // 合买料必须关联比赛
            if (!$no_match) {
            if (count($resourceDetailArr['schedule']) <= 0) {
                $this->responseJsonError(2016);
                return false;
            }
            if ($groupbuyPrice >= $param['price']) {
                $this->responseJsonError(2013);
                return false;
            }
            if ($groupbuyPrice == 0) {
                $this->responseJsonError(2014);
                return false;
            }
            if ($groupbuyNum == 0) {
                $this->responseJsonError(2015);
                return false;
            }
            }
        }
        $common = new CommonHandler();
        $resourceModel = new ResourceModel();
        $matchModel = new MatchModel();
        $real_match_type = 0;
        if (isset($param['resource_id']) && $param['resource_id'] != "") {
            $update = true;
            $resourceId = $param['resource_id'];
            //更新料主体信息
            $resource = [];
            //resource表数据
            $resource['expert_id'] = intval($param['expert_id']);
            $resource['push_expert_id'] = intval($param['expert_id']);
            $resource['title'] = addslashes(trim($param['title']));
//            $resource['price'] = trim($param['price']);
            $resource['resource_type'] = intval($param['resource_type']);
            $resource['odds'] = $param['odds'];
            $resource['is_limited'] = $isLimited;
            $resource['limited_time'] = $limitedTime;
            $resource['modify_time'] = time();
            $expertModel = new ExpertModel();
            $expertInfo = $expertModel->getExpertInfo($resource['expert_id']);
            if($expertInfo['platform'] == 1) {
              $resource['bd_display'] = 1;
              $resource['wx_display'] = 0;
            }else if($expertInfo['platform'] == 2) {
              $resource['bd_display'] = 0;
              $resource['wx_display'] = 1;
            } else {
              $displayArr = $resourceModel->transPlatform($param['display_platform'], 'display');
              $resource = array_merge($resource, $displayArr);
            }  
            //创建一个料，获取料ID
            $resourceModel->updateResource($resourceId, $resource, $isNotice);
            //删除原数据
            $resourceModel->deleteResourceDetail($resourceId);
            $resourceModel->deleteResourceSchedule($resourceId);
            $resourceModel->deleteResourceStatic($resourceId);
            $recordType=1;//修改
        } else {
            $recordType=0;//添加
            foreach ($resourceDetailArr as $key => $val) {
                if ($key == 'schedule') {
                    $changeds = [];
                    foreach ($val as $item) {
                        $real_match_type = $item['match_type'];
                        $changed = $matchModel->dealLotteryChange($item);
                        if ($changed) {
                            $tmp = $item;
                            $tmp['play_method_text'] = $this->play_method[$item['play_method']];
                            foreach ($changed as $kk => $vv) {
                                $tmp[$kk] = $vv[1];
                            }
                            $tmp['changed'] = $changed;
                            $changeds[] = $tmp;
                        }
                    }
                    if ($changeds) {
                        $this->responseJson($changeds, 'changed');
                        return false;
                    }
                }
            }
            $update = false;
            $resource = [];
            //resource表数据
            $resource['expert_id'] = intval($param['expert_id']);
            $resource['push_expert_id'] = intval($param['expert_id']);
            $resource['title'] = trim($param['title']);
            $resource['price'] = trim($param['price']);
            $resource['is_free'] = $param['is_free'];
            $resource['resource_type'] = intval($param['resource_type']);
            $resource['odds'] = $param['odds'];
            $resource['is_limited'] = $isLimited;
            $resource['limited_time'] = $limitedTime;
            $resource['create_time'] = $resource['release_time'] = time();
            $resource['is_groupbuy'] = $isGroupbuy;  // 是否为合买料
            $resource['is_expert'] = 0;
            $resource['is_auto_bet'] = $param['is_auto_bet'];
            $resource['remarks'] = $param['remarks'];
            $resource['is_schedule_over'] = $is_schedule_over;
            $resource['match_type'] = $param['match_type'];
            //$resource['resource_status'] = 1;
            if ($real_match_type) {
                $resource['match_type'] = $real_match_type;
            }
            if ($param['is_expert']) {
                $resource['resource_status'] = 1;
                $resource['is_expert'] = 1;
            }

            $expertModel = new ExpertModel();
            $expertInfo = $expertModel->getExpertInfo($resource['expert_id']);
            if ($param['display_platform']) {
                if($expertInfo['platform'] == 1) {
                  $resource['bd_display'] = 1;
                  $resource['wx_display'] = 0;
                }else if($expertInfo['platform'] == 2) {
                  $resource['bd_display'] = 0;
                  $resource['wx_display'] = 1;
                }else{
                  $displayArr = $resourceModel->transPlatform($param['display_platform'], 'display');
                  $resource = array_merge($resource, $displayArr);
                }
            } else {
                $resource['bd_display'] = 1;
                $resource['wx_display'] = 0;
            }
            //创建一个料，获取料ID
            $resourceId = $resourceModel->createResource($resource);

            if (!$resourceId) {
                //料内容生成失败，请重试
                $this->responseJson(2012);
                return false;
            } else {
                if ($param['is_expert']) {
                $expertId = $resource['expert_id'];
                //触发推送
                $expertExtraModel = new ExpertExtraModel();
                $this->touchUpush($resource['expert_id'], $expertInfo['expert_name'], $resourceId);
                $expertModel->updateExpert($expertId, ['push_resource_time' => time()]);
                $expertExtraModel->setExpertExtraIncOrDec($expertId, ['publish_resource_num' => '+1']);
                }
                //方案上架
                 $resourceModel->operationResourceStatus($resourceId, 1, 0, 0, true);
            }
        }

        //最后一场比赛的比赛时间
        $schedule_time = 0;
        foreach ($resourceDetailArr as $key => $val) {
            //resource_detail表数据
            $detailId = 0;
            $content='';
            if ($key == 'content') {
                $resourceDetail = [];
                $resourceDetail['resource_id'] = $resourceId;
                $resourceDetail['content'] = addslashes($val);
                $content=$resourceDetail['content'];
                //创建一个料内容详情，获取料内容详情ID
                $detailId = $resourceModel->createResourceDetail($resourceDetail);
            }
        }

        //封面图
        $surface = $param['surface'];
        if ($surface) {
            $resourceStatic = ['resource_id' => $resourceId, 'static_type' => 1];
            $resourceStatic['detail_id'] = $detailId;
            $resourceStatic['url'] = $surface;
            $resourceModel->createResourceStatic($resourceStatic);
        }
        $recordDataList=[];//修改记录
        foreach ($resourceDetailArr as $key => $val) {
            //resource_schedule表数据
            if ($key == 'schedule') {

                foreach ($val as $ks => $vs) {
                    if(!isset($vs['league_id']) || !isset($vs['schedule_id'])){
                        continue;
                    }
                    $resourceSchedule = [];
                    $resourceSchedule['resource_id'] = $resourceId;
                    $resourceSchedule['detail_id'] = $detailId;
                    $resourceSchedule['league_id'] = $vs['league_id'];
                    $resourceSchedule['schedule_id'] = $vs['schedule_id'];
                    $resourceSchedule['type'] = $vs['match_type'];
                    //jc bd
                    $resourceSchedule['lottery_type'] = $vs['lottery_type'];
                    //all  大小球 亚盘
                    if($vs['lottery_type'] == 0) {
                        if ($vs['play'] == 0) {
                            $resourceSchedule['lottery_type'] = 3; 
                        } else if ($vs['play'] == 3) {
                            $resourceSchedule['lottery_type'] = 4; //大小球
                        }
                    }
                    $resourceSchedule['lottery_id'] = $vs['lottery_id'];
                    $resourceSchedule['h'] = $vs['h'];
                    $resourceSchedule['w'] = $vs['w'];
                    $resourceSchedule['d'] = $vs['play_method'] ?: '';
                    if ($vs['match_type'] == 1) {
                        $resourceSchedule['d'] = $vs['d'] ?: '';
                    }
                    $resourceSchedule['l'] = $vs['l'];
                    $resourceSchedule['recommend'] = $vs['recommend'];

                    $resourceModel->createResourceSchedule($resourceSchedule);
                    /**修改记录**/
                    $recordDataList[]=$resourceSchedule;
                    /**修改记录**/
                    //$scheduleInfo = $matchModel->getScheduleInfo($vs['schedule_id'], $vs['match_type']);

                    //最后一场比赛的比赛时间
                    //$schedule_time = $schedule_time >= $scheduleInfo['schedule_time'] ? $schedule_time : $scheduleInfo['schedule_time'];
                }
            }
        }

        /**修改记录**/
        $appSetting = AppSetting::newInstance(APP_ROOT);
        $prefix_url = $appSetting->getConstantSetting("addRecord");
        foreach($recordDataList as $v){
            $v['content']=$content;
            $v['status']=$recordType;
            $v['resource_id']=$resourceId;
            $common->httpPost($prefix_url,$v);
        }
        /**修改记录**/
        //创建关联的赛事
        if ($resourceId) {
            //resource_extra表数据
            $resourceExtra = [];
            $resourceExtra['resource_id'] = $resourceId;
            $resourceExtra['schedule_time'] = $schedule_time;
            //创建一个料扩展
            if (isset($param['recommend_desc'])) {
                $recommendDesc = addslashes($param['recommend_desc']);
            } else {
                $recommendDesc = "";
            }
            $resourceExtra['recommend_desc'] = $recommendDesc;
            if ($update) {
                $resourceExtra['modify_time'] = time();
                $resourceModel->updateResourceExtra($resourceId, $resourceExtra);
            } else {
                $resourceModel->createResourceExtra($resourceExtra);

                // 如果是合买料
                if ($isGroupbuy === 1) {
                    $groupData = array(
                        'resource_id' => $resourceId,
                        'limit_time' => $schedule_time - (30 * 60),  // 开赛前半小时为合买限定时间
                        'num' => $groupbuyNum,
                        'price' => $groupbuyPrice,
                    );
                    $resourceModel->createResourceGroup($groupData);
                 }
            }
            $redisManageModel = new RedisKeyManageModel('resource');
            $redisManageModel->delExpertKey(intval($param['expert_id']));

            $data['resource_id'] = $resourceId;
            $this->responseJson($data);
        } else {
            //料内容生成失败，请重试
            $this->responseJson(2012);
        }
    }

    public function operationResourceStatus() {
        $param = $this->checkApiParam(['resource_id', 'operation_code']);
        $resourceId = intval($param['resource_id']);
        //操作码    1:发布、上架 2:下架 4:已删除
        $operationCode = intval($param['operation_code']);

        $resourceModel = new ResourceModel();
        //操作修改料状态
        $res = $resourceModel->operationResourceStatus($resourceId, $operationCode, 0, 0, true);
        if ($res === true) {
            $resource_info = $resourceModel->getResourceDetailedInfo($resourceId);
            $expert_id = $resource_info['expert_id'];
            //近十场战绩删除
            $redisManageModel = new RedisKeyManageModel('betRecord');
            $redisManageModel->delExpertStat($expert_id);
            if ($operationCode == 1) {
                if (!$resource_info['wx_display'] || ($resource_info['wx_display'] && $resource_info['bd_display'])) {

                    $expert_model = new ExpertModel();
                    $expert_info = $expert_model->getExpertInfo($expert_id);
                    $expert_platform = $expert_info['platform'];
                    if (in_array($expert_platform, [0, 1])) {
                        $expert_name = $expert_info['expert_name'] ?: '';
                        $this->touchUpush($expert_id, $expert_name, $resourceId);
                    }
                }
            }
            $this->responseJson();
        } else {
            $this->responseJsonError($res);
        }

    }

    /**
     * 置顶操作
     */
    public function setPlacement() {
        $params = $this->checkApiParam(['resource_id', 'is_placement']);
        $resourceId = intval($params['resource_id']);
        $isPlacement = intval($params['is_placement']);

        $resourceModel = new ResourceModel();
        $res = $resourceModel->setPlacement($resourceId, $isPlacement);
        if ($res) {
            $this->responseJson();
        } else {
            $this->responseJsonError(-1, "操作失败");
        }
    }

    /**
     * 合买控制列表
     */
    public function groupList() {
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 10]);
        $page = intval($params['page']);
        $pagesize = intval($params['pagesize']);
        $start_item = ($page - 1) * $pagesize;
        if (!empty($params['query'])) {
            $query = json_decode($params['query'], true);
            $resourceId = isset($query['resource_id']) ? intval($query['resource_id']) : null;
            $groupStatus = isset($query['group_status']) ? (string)$query['group_status'] : null;
            $startTime = isset($query['start_time']) ? intval($query['start_time']) : null;
            $endTime = isset($query['end_time']) ? intval($query['end_time']) : null;
        }

        $other = [];

        $resourceModel = new ResourceModel();
        $condition = '1 = 1';
        $condition .= ' AND resource_status = 1';
        $condition .= ' AND is_groupbuy = 1';
        if (!empty($resourceId)) {
            $condition .= ' AND hl_resource.resource_id = ' . $resourceId;
        }
        if (!is_null($groupStatus) && $groupStatus !== '') {
            $condition .= ' AND status = ' . $groupStatus;
            $other['join'] = [['hl_resource_group', 'hl_resource.resource_id = hl_resource_group.resource_id']];
        }
        if (!empty($startTime)) {
            $condition .= ' AND hl_resource.create_time >= ' . $startTime;
        }
        if (!empty($endTime)) {
            $condition .= ' AND hl_resource.create_time <= ' . $endTime;
        }
        $list = $resourceModel->groupControlList($condition, $other, $start_item, $pagesize);

        return $this->responseJson(empty($list) ? [] : $list);
    }

    /**
     * 停止某合买料展示销量增加的脚本
     */
    public function stopCronSoldNum() {
        $params = $this->checkApiParam(['resource_id']);
        $resourceId = intval($params['resource_id']);

        $resourceModel = new ResourceModel();
        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        $resourceGroupInfo = $resourceModel->getResourceGroupInfo($resourceId);

        $checkResult = $resourceModel->checkGroupOperable($resourceInfo, $resourceGroupInfo);
        if ($checkResult !== true) {
            $this->responseJsonError(2002);
        }

        $resourceModel->stopSoldCron($resourceId);

        $this->responseJson([], '操作成功');
    }

    /**
     * 显示销量增加
     */
    public function cronSoldInc() {
        $params = $this->checkApiParam(['resource_id']);
        $resourceId = intval($params['resource_id']);

        $resourceModel = new ResourceModel();
        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        $resourceGroupInfo = $resourceModel->getResourceGroupInfo($resourceId);

        $checkResult = $resourceModel->checkGroupOperable($resourceInfo, $resourceGroupInfo);
        if ($checkResult !== true) {
            $this->responseJsonError(2002);
        }

        $resourceExtraInfo = $resourceModel->getResourceExtraInfo($resourceId);
        $resourceModel->cronSoldInc($resourceExtraInfo, $resourceGroupInfo);

        $this->responseJson([], '操作成功');
    }

    //触发推送
    public function touchUpush($expert_id, $expert_name, $resource_id) {
        //$expert_id, $expert_name, $resource_id
        //$params = $this->checkApiParam(['resource_id', 'expert_id', 'expert_name']);
        //$expert_id = $params['expert_id'];
        //$resource_id = $params['resource_id'];
        //$expert_name = $params['expert_name'];
        if (empty($expert_id) || empty($resource_id) || empty($expert_name)) {
            return false;
        }
        $expert_model = new ExpertModel();
        $push_model = new PushMsgModel();
        $user_ids  = $expert_model->getFollowUserByExpert($expert_id);
        if ($user_ids) {
            $touch_info = ['resource_id' => $resource_id, 'expert_name' => $expert_name];
            $push_model->createTouchMsg(2, $touch_info, $user_ids);
        }
    }



    /**
     * 调整排序
     */
    public function updateSort() {
        $param = $this->checkApiParam(['resource_id', 'sort']);
        $sort_num = 0;
        $updateCondition = array();
        switch($param['sort']) {
            case 1: //置顶1 最大位
                $sort_num = 10;
                $updateCondition = array('sort' => ['=', 1000000000]);
                break;
            case 2: //置顶2
                $sort_num = 9;
                $updateCondition = array('sort' => ['=', 900000000]);
                break;
            case 3: //置顶3
                $sort_num = 8;
                $updateCondition = array('sort' => ['=', 800000000]);
                break;
            case 4: //置顶4
                $sort_num = 7;
                $updateCondition = array('sort' => ['=', 700000000]);
                break;
            case 5: //置顶5
                $sort_num = 6;
                $updateCondition = array('sort' => ['=', 600000000]);
                break;
            case 6: //置顶6
                $sort_num = 5;
                $updateCondition = array('sort' => ['=', 500000000]);
                break;
            case 7: //置顶7
                $sort_num = 4;
                $updateCondition = array('sort' => ['=', 400000000]);
                break;
            case 8: //置顶8
                $sort_num = 3;
                $updateCondition = array('sort' => ['=', 300000000]);
                break;
            case 9: //置顶9
                $sort_num = 2;
                $updateCondition = array('sort' => ['=', 200000000]);
                break;
            case 10: //置顶10,末尾
                $sort_num = 1;
                $updateCondition = array('sort' => ['=', 100000000]);
                break;
        }

        $resourceModel = new ResourceModel();
        if($param['sort']){
            $ret = $resourceModel->updateSort($updateCondition, 0);
        }
        $res = $resourceModel->updateSort(array('resource_id' => $param['resource_id']), $sort_num);
        $this->responseJson();
    }

}
