<?php
/**
 * 专家相关接口
 * User: YangChao
 * Date: 2018/10/18
 */

namespace QK\HaoLiao\Controllers\User\Base;


use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertSubscribeModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\UserFollowModel;
use QK\HaoLiao\Model\UserSubscribeModel;

class ExpertController extends UserController {

    /**
     * 获取专家详细信息
     */
    public function expertInfo(){
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0]);
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
        //敏感数据去除
        unset($data['phone']);
        unset($data['idcard_number']);
        unset($data['real_name']);

        //检查用户是否关注专家
        $userFollowModel = new UserFollowModel();
        $isFollowExpert = (int)$userFollowModel->checkFollowExpertStatus($userId, $expertId);

        //检查用户是否订阅
        $userSubscribeModel = new UserSubscribeModel();
        $isSubscribeExpert = (int)$userSubscribeModel->checkUserSubscribeExpert($userId, $expertId);


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
    }

    /**
     * 获取推荐专家TOP3
     */
    public function recommendTop(){
        $param = $this->checkApiParam([], ['user_id' => 0]);
        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        }
        $expertModel = new ExpertModel();
        $RecommendTop = $expertModel->getExpertRecommendTop(3);
        $this->responseJson($RecommendTop);
    }

    /**
     * 用户订阅专家列表
     */
    public function subscribeList(){
        $param = $this->checkApiParam([], ['user_id' => 0]);
        $userId = intval($param['user_id']);
        if($userId){
            $this->checkToken();
        } else {
            $this->responseJson([]);
        }
        $userSubscribe = new UserSubscribeModel();
        $subscribeList = $userSubscribe->getUserSubscribeList($userId);

        if(empty($subscribeList)){
            $this->responseJson([]);
        }

        if(!empty($subscribeList)){
            $resourceModel = new ResourceModel();
            foreach($subscribeList as $key => $val){
                $resourceList = $resourceModel->getResourceListByExpertId($val['expert_id'], 1, 1, 2);
                $subscribeList[$key]['resource_list'] = $resourceList;
            }
        }

        $this->responseJson($subscribeList);
    }

    /**
     * 用户关注专家列表（去除已订阅专家）
     */
    public function followList(){
        $param = $this->checkApiParam([], ['user_id' => 0]);
        $userId = intval($param['user_id']);
        if($userId){
            $this->checkToken();
        } else {
            $this->responseJson([]);
        }
        //获取订阅列表
        $userSubscribe = new UserSubscribeModel();
        $subscribeList = $userSubscribe->getUserSubscribeList($userId);

        //获取关注列表
        $userFollowModel = new UserFollowModel();
        $followList = $userFollowModel->followExpertList($userId);
        if(empty($followList)){
            $this->responseJson([]);
        }

        $followList = StringHandler::newInstance()->getDiffArrayByPk($followList, $subscribeList, 'expert_id');

        if(!empty($followList)){
            $resourceModel = new ResourceModel();
            foreach($followList as $key=>$val){
                $resourceList = $resourceModel->getResourceListByExpertId($val['expert_id'], 1, 1, 2);
                $followList[$key]['resource_list'] = $resourceList;
            }
        }

        $this->responseJson($followList);
    }

    /**
     * 获取专家推荐列表（去除已订阅已关注的）
     */
    public function expertList(){
        $param = $this->checkApiParam([], ['user_id' => 0, 'start' => 0, 'page' => 1, 'pagesize' => 5, 'order_by' => 1]);
        $userId = intval($param['user_id']);
        $start = intval($param['start']);
        $orderBy = intval($param['order_by']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);

        if($userId){
            $this->checkToken();
        }

        $removeExpertIds = [];
        if($orderBy != 3){
            //获取订阅列表
            $userSubscribe = new UserSubscribeModel();
            $subscribeList = $userSubscribe->getUserSubscribeList($userId);

            //获取关注列表
            $userFollowModel = new UserFollowModel();
            $followList = $userFollowModel->followExpertList($userId);

            $removeExpertIds = array_values(array_unique(array_column(array_merge($subscribeList, $followList), 'expert_id')));
        }

        $expertModel = new ExpertModel();
        $expertList = $expertModel->getExpertList($start, $page, $pagesize, $removeExpertIds, $orderBy);

        if(!empty($expertList)){
            $resourceModel = new ResourceModel();
            foreach($expertList as $key=>$val){
                $resourceList = $resourceModel->getResourceListByExpertId($val['expert_id'], 1,  1, 2);
                $expertList[$key]['resource_list'] = $resourceList;
            }
        }

        $this->responseJson($expertList);
    }


    /**
     * 关注/取消专家
     */
    public function follow(){
        $this->checkToken();
        $params = $this->checkApiParam(['user_id', 'expert_id'], ['status' => 2]);
        $userId = $params['user_id'];
        $expertId = $params['expert_id'];
        $status = $params['status'];
        if($userId==$expertId && $status==1){
            $this->responseJsonError(1000,'不能关注自己');
        }
        $userFollowModel = new UserFollowModel();
        $userFollowModel->followExpert($userId, $expertId, $status);
        $this->responseJson();
    }

}
