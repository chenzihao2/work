<?php
/**
 * banner相关接口
 * User: twenj
 * Date: 2019/03/04
 */

namespace QK\HaoLiao\Controllers\User\V2;
use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Controllers\User\Base\ExpertController as Expert;
use QK\HaoLiao\Model\UserSubscribeModel;
use QK\HaoLiao\Model\UserFollowModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\RedisManageModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertSubscribeModel;
use QK\HaoLiao\Model\ResourceModel;

class ExpertController extends Expert {
    /**
     * 获取专家详细信息
     */
    public function expertInfo(){
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0, 'is_new' => 0]);
        $isNew = $param['is_new'];
        $userId = $param['user_id'];
        $expertId = $param['expert_id'];

        if($userId){
            $this->checkToken();
        }

        //获取专家信息
        $expertModel = new ExpertModel();
        $data = $expertModel->getExpertInfo($expertId);
        if(empty($data)){
            $this->responseJsonError(1301);
        }
        $expertExtraModel = new ExpertExtraModel();
        $dataExtra = $expertExtraModel->getExpertExtraInfo($expertId);
        $data['profit_rate'] = intval($dataExtra['profit_rate']);
        $data['profit_all'] = intval($dataExtra['profit_all']);//回报率
        $data['max_bet_record'] = intval($dataExtra['max_bet_record']);
        if($isNew) {
            $data['max_bet_record'] = intval($dataExtra['max_bet_record_v2']);
        }
        //$data['max_red_num_V2'] = intval($dataExtra['max_red_num']);//最高连红
        $data['max_red_num'] = intval($dataExtra['max_red_num']);//最高连红

        //敏感数据去除
        unset($data['phone']);
        unset($data['idcard_number']);
        unset($data['real_name']);

        $isFollowExpert = 0;  //检查用户是否关注专家
        $isSubscribeExpert = 0;  //检查用户是否订阅

        if ($userId) {
            //检查用户是否关注专家
            $userFollowModel = new UserFollowModel();
            $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);

            //检查用户是否订阅
            //$userSubscribeModel = new UserSubscribeModel();
            //$isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);
        }

        if($userId == $expertId){
            $isSubscribeExpert = 1;
        }

        //获取专家30日订阅价格
        $expertSubscribeModel = new ExpertSubscribeModel();
        $expertSubscribe = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30);

        $data['is_follow_expert'] = $isFollowExpert;
        $data['is_subscribe_expert'] = $isSubscribeExpert;
        $data['subscribe_price'] = $expertSubscribe['subscribe_price'];
        $data['length_day'] = $expertSubscribe['length_day'];
        $this->responseJson($data);
        //$this->echoJson($data);
        //fastcgi_finish_request();
        //$expertModel->updateStatInfo($expertId);
    }

    /**
     * 获取推荐专家TOP8
     */
    public function recommendTop(){
        $param = $this->checkApiParam([], ['user_id' => 0, 'platform' => 1, 'is_new' => 0]);
        $is_new = $param['is_new'];
        $userId = $param['user_id'];
        $platform = $param['platform'];
        if($userId){
            $this->checkToken();
        }
        $expertModel = new ExpertModel();
        $RecommendTop = $expertModel->getExpertRecommendTop(8, $platform, $is_new);
        $this->responseJson($RecommendTop);
    }

    /**
     * 获取专家推荐列表（去除已订阅已关注的）
     */
    public function expertList(){
        $param = $this->checkApiParam([], ['user_id' => 0, 'platform' => 1, 'start' => 0, 'page' => 1, 'pagesize' => 5, 'order_by' => 1]);
        $userId = intval($param['user_id']);
        $start = intval($param['start']);
        $orderBy = intval($param['order_by']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);

        if($userId){
            $this->checkToken();
        }

        $removeExpertIds = [];
        if($orderBy != 3){
            //获取订阅列表
            //$userSubscribe = new UserSubscribeModel();
            //$subscribeList = $userSubscribe->getUserSubscribeList($userId);

            //获取关注列表
            $userFollowModel = new UserFollowModel();
            $followList = $userFollowModel->followExpertList($userId);

            $removeExpertIds = array_values(array_unique(array_column($followList, 'expert_id')));
        }

        $expertModel = new ExpertModel();
        $expertList = $expertModel->getExpertList($start, $page, $pagesize, $removeExpertIds, $orderBy, $platform);

        if(!empty($expertList)){
            $resourceModel = new ResourceModel();
            foreach($expertList as $key=>$val){
                $resourceList = $resourceModel->getResourceListByExpertId($val['expert_id'], 1,  1, 2);
                foreach ($resourceList as &$resource) {
                    $resourceModel->addCronSoldNum($resource);
                }
                $expertList[$key]['resource_list'] = $resourceList;
            }
        }

        $this->responseJson($expertList);
    }

    /**
     * 专家列表
     */
    public function allList() {
        $param = $this->checkApiParam([], ['user_id' => 0, 'page' => 1, 'pagesize' => 50, 'order_by' => 1, 'day' => 3, 'platform' => 1]);
        $userId = intval($param['user_id']);
        $orderBy = intval($param['order_by']);
        $day = intval($param['day']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);

        if($userId){
            $this->checkToken();
        }

        $expertModel = new expertModel();
        $lists = $expertModel->allExpertList($userId, $orderBy, $day, $page, $pagesize, $platform);
        $this->responseJson(!$lists ? [] : $lists, 'ok');
    }

    public function allListV2() {
        $expertModel = new ExpertModel();
        $redisModel = new RedisModel('expert');
        $redisManageModel = new RedisManageModel('expert');

        $param = $this->checkApiParam([], ['user_id' => 0, 'page' => 1, 'pagesize' => 50, 'order_by' => 1, 'order_index' => 3, 'platform' => 1]);
        $userId = intval($param['user_id']);
        if($userId){
            $this->checkToken();
        }

        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);

        $orderBy = intval($param['order_by']);
        if ($orderBy == 3) {   //按命中率排序
            $orderIndex = intval($param['order_index']);
            $redisKey = EXPERT_BET_RECORD . $orderIndex . ':' .$platform;
            $redisKeyIsExists = $redisModel->redisKeys($redisKey);
            if (empty($redisKeyIsExists)) {
                //更新排序缓存
                $expertList = $expertModel->getNewExpertList($platform);
                //$expertList = $expertModel->expertList();
                foreach ($expertList as $expert) {
                    $expertModel->calBetRecord($expert['expert_id'], $platform);
                }
            }
            $expertIds = $redisManageModel->getListBySort($redisKey, ($page - 1) * $pagesize, $pagesize);
        } else {
            $redisKey = EXPERT_ALL_LIST_V2;
            $expertIds = $redisManageModel->getList($redisKey, $orderBy, $page, $pagesize);
            $expertIds = array();
            if(empty($expertIds)) {   //默认排序和盈利率排序
                $condition = "`expert_status` = 1";
                $condition .= ($platform > 0) ? " AND `platform` in (0, $platform)" : " AND `platform` = $platform";

                $order = ($platform == 1) ? 'is_placement desc, expert_id asc' : 'is_wx_placement desc, expert_id asc';
                $search_fileds = ($platform == 1) ? 'is_placement, expert_id' : 'is_wx_placement as is_placement, expert_id';
                $join = array();
                if($orderBy == 2) {
                    $order = 'profit_rate desc, expert_id asc';
                    $search_fileds = 'hl_user_expert.expert_id';
                    $join = ['join' => [['hl_user_expert_extra', 'hl_user_expert_extra.expert_id = hl_user_expert.expert_id']]];
                }

                $expertIds = $expertModel->listsV2($condition, $search_fileds, $order, $join, ($page - 1) * $pagesize, $pagesize);
                $lasttopcount = 0;
                for ($i = 9; $i >= 0; $i--) {
                    if ($lasttopcount == 0 && $expertIds[$i]['is_placement'] != 0) {
                        $lasttopcount = $i;
                    }
                    if ($i <= $lasttopcount && $i > intval($lasttopcount / 2)) {
                        $dy = $lasttopcount - $i;
                        $tmp = $expertIds[$i];
                        $expertIds[$i] = $expertIds[$dy];
                        $expertIds[$dy] = $tmp;
                    }
                }
                $expertIds = array_column($expertIds, 'expert_id');

                $redisManageModel->setList($redisKey, $orderBy, $page, $pagesize, $expertIds);
            }
        }

        $lists = [];
        $userFollowModel = new UserFollowModel();
        $expertExtraModel = new ExpertExtraModel();
        foreach ($expertIds as $expertId) {
            $expertInfo = $expertModel->expertInfoV2($expertId);
            $dataExtra = $expertExtraModel->getExpertExtraInfo($expertId);
            $isFollowExpert = 0;
            if($userId){
                //检查用户是否关注专家
                $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
            }

            $maxBetRecord = $expertInfo['max_bet_record_v2'];
            if($orderBy == 3) {
                $betRecordScore = $redisModel->redisZScore($redisKey, $expertId);
                if (empty($maxBetRecord)){
                    $maxBetRecord = 0;
                } else {
                    $maxBetRecord = intval($betRecordScore / 10);     //去除存入时最后位的是否发料位
                }
            }

            $lately_red=$expertInfo['lately_red'];//近几中几
            $max_red_num=$dataExtra['max_red_num'];//连红
            $lists[] = array(
                'expert_id' => $expertInfo['expert_id'],
                'expert_name' => $expertInfo['expert_name'],
                'headimgurl' => $expertInfo['headimgurl'],
                'identity_desc' => $expertInfo['identity_desc'],
                'tag' => $expertInfo['tag'],
                'profit_rate' => $expertInfo['profit_rate'],
                'profit_all' => $expertInfo['profit_all'],//回报率
                'publish_resource_num' => $expertInfo['publish_resource_num'],
                'max_bet_record' => $maxBetRecord,
                'max_red_num' => $max_red_num,
                'is_follow_expert' => $isFollowExpert,
                'is_subscribe_expert' => 0,
                'lately_red'=>$lately_red
            );
        }

        $this->responseJson($lists, 'ok');
    }



    public function allListV3() {
        $expertModel = new ExpertModel();
        $redisModel = new RedisModel('expert');
        $redisManageModel = new RedisManageModel('expert');

        $param = $this->checkApiParam([], ['user_id' => 0, 'page' => 1, 'pagesize' => 50, 'order_by' => 1, 'order_index' => 3, 'platform' => 1]);
        $userId = intval($param['user_id']);
        if($userId){
            $this->checkToken();
        }

        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);

        $orderBy = intval($param['order_by']);
        if ($orderBy == 3) {   //按命中率排序
            $orderIndex = intval($param['order_index']);
            $redisKey = EXPERT_BET_RECORD . $orderIndex . ':' .$platform;
            $redisKeyIsExists = $redisModel->redisKeys($redisKey);
            if (empty($redisKeyIsExists)) {
                //更新排序缓存
                $expertList = $expertModel->getNewExpertList($platform);
                //$expertList = $expertModel->expertList();
                foreach ($expertList as $expert) {
                    $expertModel->calBetRecord($expert['expert_id'], $platform);
                }
            }
            $expertIds = $redisManageModel->getListBySort($redisKey, ($page - 1) * $pagesize, $pagesize);
        } else {
            $redisKey = EXPERT_ALL_LIST_V2;
            $expertIds = $redisManageModel->getList($redisKey, $orderBy, $page, $pagesize);
            $expertIds = array();
            if(empty($expertIds)) {   //默认排序和盈利率排序
                $condition = "`expert_status` = 1";
                $condition .= ($platform > 0) ? " AND `platform` in (0, $platform)" : " AND `platform` = $platform";

                $order = ($platform == 1) ? 'is_placement desc, expert_id asc' : 'is_wx_placement desc, expert_id asc';
                $search_fileds = ($platform == 1) ? 'is_placement, expert_id' : 'is_wx_placement as is_placement, expert_id';
                $join = array();
                if($orderBy == 2) {
                    $order = 'profit_all desc, expert_id asc';
                    $search_fileds = 'hl_user_expert.expert_id';
                    $join = ['join' => [['hl_user_expert_extra', 'hl_user_expert_extra.expert_id = hl_user_expert.expert_id']]];
                }

                $expertIds = $expertModel->listsV2($condition, $search_fileds, $order, $join, ($page - 1) * $pagesize, $pagesize);
                $lasttopcount = 0;
                for ($i = 9; $i >= 0; $i--) {
                    if ($lasttopcount == 0 && $expertIds[$i]['is_placement'] != 0) {
                        $lasttopcount = $i;
                    }
                    if ($i <= $lasttopcount && $i > intval($lasttopcount / 2)) {
                        $dy = $lasttopcount - $i;
                        $tmp = $expertIds[$i];
                        $expertIds[$i] = $expertIds[$dy];
                        $expertIds[$dy] = $tmp;
                    }
                }
                $expertIds = array_column($expertIds, 'expert_id');

                $redisManageModel->setList($redisKey, $orderBy, $page, $pagesize, $expertIds);
            }
        }

        $lists = [];
        $userFollowModel = new UserFollowModel();
        $expertExtraModel = new ExpertExtraModel();

        foreach ($expertIds as $expertId) {
            $expertInfo = $expertModel->expertInfoV2($expertId);
            $dataExtra = $expertExtraModel->getExpertExtraInfo($expertId);
            $isFollowExpert = 0;
            if($userId){
                //检查用户是否关注专家
                $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);
            }

            $maxBetRecord = $expertInfo['max_bet_record_v2'];
            if($orderBy == 3) {
                $betRecordScore = $redisModel->redisZScore($redisKey, $expertId);
                if (empty($maxBetRecord)){
                    $maxBetRecord = 0;
                } else {
                    $maxBetRecord = intval($betRecordScore / 10);     //去除存入时最后位的是否发料位
                }
            }

            $lately_red=$expertInfo['lately_red'];//近几中几
            $max_red_num=$dataExtra['max_red_num'];//连红
            $lists[] = array(
                'expert_id' => $expertInfo['expert_id'],
                'expert_name' => $expertInfo['expert_name'],
                'headimgurl' => $expertInfo['headimgurl'],
                'identity_desc' => $expertInfo['identity_desc'],
                'tag' => $expertInfo['tag'],
                'profit_rate' => $expertInfo['profit_rate'],
                'profit_all' => $expertInfo['profit_all'],//回报率
                'publish_resource_num' => $expertInfo['publish_resource_num'],
                'max_bet_record' => $maxBetRecord,
                'max_red_num' => $max_red_num,
                'is_follow_expert' => $isFollowExpert,
                'is_subscribe_expert' => 0,
                'lately_red'=>$lately_red
            );
        }

        $this->responseJson($lists, 'ok');
    }





    public function recommandExpert() {
        $param = $this->checkApiParam([], ['user_id' => 0, 'platform' => 2]);
        $user_id = $param['user_id'];
        $platform = intval($param['platform']);
        $expertModel = new ExpertModel();
        $rec_list = [];
        $rec_list = $expertModel->getRecommandExpertList($user_id, 0, 1, 5, [], 2, $platform);
        if ($rec_list) {
            $userFollowModel = new UserFollowModel();
            foreach ($rec_list as $k => $v) {
                if ($user_id) {
                    $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($user_id, $v['expert_id']);
                    $rec_list[$k]['isFollowExpert'] = $isFollowExpert;
                } else {
                    $rec_list[$k]['isFollowExpert'] = 0;
                }
            }
        }
        $this->responseJson($rec_list);
    }




    /*
     * 热门专家列表-新增
     */
    public function hotExpert(){
        $param = $this->checkApiParam([], ['user_id' => 0, 'count' => 4,'page'=>4, 'pagesize' => 200, 'order_by' => 1, 'platform' => 1]);
        $userId = intval($param['user_id']);
        $count = intval($param['count']);
        $orderBy = intval($param['order_by']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);
        $page = intval($param['page']);

        //获取命中率高的专家
        $expertModel = new ExpertModel();
        $lists = $expertModel->hotExpertList($userId, $orderBy,  $count, $page,$pagesize, $platform);

        $this->responseJson($lists);
    }

    /*
     * 关注得专家列表-新增
     */
    public  function myExpertList(){
        $param = $this->checkApiParam(['user_id'], ['user_id' => 0, 'page' => 1, 'pagesize' => 50, 'order_by' => 1, 'day' => 3, 'platform' => 2]);
        $userId = intval($param['user_id']);

        //$orderBy = intval($param['order_by']);
        //$day = intval($param['day']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $platform = intval($param['platform']);



        if($userId){
            //$this->checkToken();
        } else {
            $this->responseJson([]);
        }
        //获取订阅列表
        $userSubscribe = new UserSubscribeModel();
        $subscribeList = $userSubscribe->getUserSubscribeList($userId);

        //获取关注列表
        $userFollowModel = new UserFollowModel();
        $followList = $userFollowModel->followMyExpertList($userId,$page, $pagesize, $platform);

        if(empty($followList)){
            $this->responseJson([]);
        }
        //去除重复得
        $followList = StringHandler::newInstance()->getDiffArrayByPk($followList, $subscribeList, 'expert_id');

        $userSubscribeModel = new UserSubscribeModel();
        foreach($followList as &$v){
            $isFollowExpert = 0;  //检查用户是否关注专家
            $isSubscribeExpert = 0;  //检查用户是否订阅

            //检查用户是否关注专家
            $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $v['expert_id']);
            $v['is_follow_expert']=$isFollowExpert;
            //检查用户是否订阅

            $isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $v['expert_id']);
            $v['is_subscribe_expert']=$isSubscribeExpert;
        }
        /*
        if(!empty($followList)){
            $resourceModel = new ResourceModel();
            foreach($followList as $key=>$val){
                $resourceList = $resourceModel->getResourceListByExpertId($val['expert_id'], 1, 1, 2);
                $followList[$key]['resource_list'] = $resourceList;
            }
        }*/

        $this->responseJson($followList);
        //dump($followList);
    }
}
