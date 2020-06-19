<?php
/**
 * 料相关接口
 * User: YangChao
 * Date: 2018/10/22
 */

namespace QK\HaoLiao\Controllers\User\Base;

use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertSubscribeModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\UserSubscribeModel;

class ResourceController extends UserController {

    /**
     * 获取专家料列表
     */
    public function expertResourceList(){
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
        $resourceList = $resourceModel->getResourceListByExpertId($expertId, 1, $page, $pageSize);

        $this->responseJson($resourceList);
    }

    /**
     * 获取料详情
     */
    public function resourceInfo(){
        $param = $this->checkApiParam(['resource_id'], ['user_id' => 0]);
        $userId = intval($param['user_id']);
        $resourceId = intval($param['resource_id']);

        if($userId){
            $this->checkToken();
        }

        $resourceModel = new ResourceModel();
        //获取料详情
        $resourceInfo = $resourceModel->getResourceDetailedInfo($resourceId);

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
        unset($expertInfo['phone']);
        unset($expertInfo['idcard_number']);
        unset($expertInfo['real_name']);

        //是否购买  1:已购买  0:未购买
        $isBuy = 0;

        //已完赛，直接查看
//        if($resourceInfo['is_schedule_over'] == 1){
//            $isBuy = 1;
//        }

        //已判定红黑单，直接查看
        if($resourceInfo['bet_status']){
            $isBuy = 1;
        }

        if($userId){
            //检测用户是否购买过此料
            $orderModel = new OrderModel();
            $userIsBuyResource = $orderModel->checkUserBuyV2($userId, $resourceId);
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
            if($userId == $resourceInfo['expert_id']){
                $isBuy = 1;
                $userIsSubscribe = 1;
            }
        }

        //获取专家30日订阅价格
        $expertSubscribeModel = new ExpertSubscribeModel();
        $expertSubscribe = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30);

        $isLimited = 0;
        $limitedTime = '';
        if($resourceInfo['is_limited'] && $resourceInfo['limited_time'] > time()){
            $isLimited = 1;
            $limitedTime = date("m月d日 H:i", $resourceInfo['limited_time']);
        }

        if(!$isBuy || $isLimited){
            //未购买，处理数据，干掉内容和附件
            foreach($resourceInfo['detail'] as $key => $val){
                $resourceInfo['detail'][$key]['static'] = [];
                $resourceInfo['detail'][$key]['content'] = '';
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
        $this->responseJson($data);
    }

}