<?php
/**
 * 料相关接口
 * User: YangChao
 * Date: 2018/10/11
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\OrderModel;
use QK\HaoLiao\Model\RedisKeyManageModel;
use QK\HaoLiao\Model\ResourceModel;

class ResourceController extends ExpertController {

    /**
     * 提交料信息
     */
    public function submitInfo(){
//        $param = [
//            'user_id' => 2,
//            'expert_id' => 2,
//            'title' => '测试料',
//            'detail' => [
//                [
//                    'content' => '测试料内容',
//                    'static' => [
//                        'img' => [
//                            "/resource/1.jpg",
//                            "/resource/2.jpg",
//                        ]
//                    ],
//                    'schedule' => [
//                        [
//                            "league_id" => 1,
//                            "schedule_id" => 1,
//                            "schedule_time" => 1541998920,
//                        ],
//                        [
//                            "league_id" => 1,
//                            "schedule_id" => 1,
//                            "schedule_time" => 1544590920,
//                        ]
//                    ]
//                ],
//                [
//                    'content' => '测试料内容',
//                    'static' => [
//                        'img' => [
//                            "/resource/1.jpg",
//                            "/resource/2.jpg",
//                        ]
//                    ],
//                    'schedule' => [
//                        [
//                            "league_id" => 1,
//                            "schedule_id" => 1,
//                            "schedule_time" => 1541998920,
//                        ],
//                        [
//                            "league_id" => 1,
//                            "schedule_id" => 1,
//                            "schedule_time" => 1544590920,
//                        ]
//                    ]
//                ]
//            ],
//            'price' => '10.99',
//            'resource_type' => 1
//        ];

        $param = $this->checkApiParam(['user_id', 'expert_id', 'title', 'detail', 'price', 'resource_type']);

        $resourceDetailArr = json_decode($param['detail'], true);
        $resourceType = intval($param['resource_type']);

        if (empty($resourceDetailArr)) {
            //请填写比赛内容
            $this->responseJsonError(2010);
        }

        if ($resourceType == 2) {
            //不中退款仅可关联单场比赛
            if (count($resourceDetailArr) > 1) {
                $this->responseJsonError(2011);
            }
            if (count($resourceDetailArr[0]['schedule']) > 1) {
                $this->responseJsonError(2011);
            }
        }

        $resource = [];
        //resource表数据
        $resource['expert_id'] = intval($param['expert_id']);
        $resource['push_expert_id'] = intval($param['user_id']);
        $resource['title'] = trim($param['title']);
        $resource['price'] = trim($param['price']);
        $resource['resource_type'] = intval($param['resource_type']);
        $resource['create_time'] = $resource['release_time'] = time();

        $resourceModel = new ResourceModel();
        //创建一个料，获取料ID
        $resourceId = $resourceModel->createResource($resource);
        if (!$resourceId) {
            //料内容生成失败，请重试
            $this->responseJson(2012);
        }

        //最后一场比赛的比赛时间
        $schedule_time = 0;

        foreach ($resourceDetailArr as $key => $val) {
            //resource_detail表数据
            $resourceDetail = [];
            $resourceDetail['resource_id'] = $resourceId;
            $resourceDetail['content'] = $val['content'];
            //创建一个料内容详情，获取料内容详情ID
            $detailId = $resourceModel->createResourceDetail($resourceDetail);

            //resource_static表数据
            if (!empty($val['static']['img'])) {
                foreach ($val['static']['img'] as $ki => $vi) {
                    $resourceStatic = [];
                    $resourceStatic['resource_id'] = $resourceId;
                    $resourceStatic['detail_id'] = $detailId;
                    $resourceStatic['static_type'] = 1;
                    $resourceStatic['url'] = $vi;
                    $staticId = $resourceModel->createResourceStatic($resourceStatic);
                }
            }

            //resource_schedule表数据
            if (!empty($val['schedule'])) {
                foreach ($val['schedule'] as $ks => $vs) {
                    $resourceSchedule = [];
                    $resourceSchedule['resource_id'] = $resourceId;
                    $resourceSchedule['detail_id'] = $detailId;
                    $resourceSchedule['league_id'] = $vs['league_id'];
                    $resourceSchedule['schedule_id'] = $vs['schedule_id'];
                    //创建关联的赛事
                    $resourceModel->createResourceSchedule($resourceSchedule);

                    //最后一场比赛的比赛时间
                    $schedule_time = $schedule_time >= $vs['schedule_time'] ? $schedule_time : $vs['schedule_time'];
                }
            }
        }

        if ($resourceId) {
            //resource_extra表数据
            $resourceExtra = [];
            $resourceExtra['resource_id'] = $resourceId;
            $resourceExtra['schedule_time'] = $schedule_time;
            //创建一个料扩展
            $resourceModel->createResourceExtra($resourceExtra);
            $redisManageModel  = new RedisKeyManageModel('resource');
            $redisManageModel->delExpertKey(intval($param['expert_id']));
            $data['resource_id'] = $resourceId;
            $this->responseJson($data);
        } else {
            //料内容生成失败，请重试
            $this->responseJson(2012);
        }
    }

    /**
     * 获取内容管理列表
     */
    public function expertManageList(){
        $param = $this->checkApiParam(['user_id', 'expert_id'], ['page' => 1, 'pagesize' => 20]);
        $expertId = intval($param['expert_id']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);

        //获取料列表
        $resourceModel = new ResourceModel();
        $resourceTotal = $resourceModel->getResourceTotalByExpertId($expertId);
        $resourceList = $resourceModel->getResourceListByExpertId($expertId, 2, $page, $pagesize);
        $data = [];
        if (!empty($resourceList)) {
            foreach ($resourceList as $key => $val) {
                $data[$key]['resource_id'] = (int)$val['resource_id'];
                $data[$key]['title'] = $val['title'];
                $data[$key]['price'] = $val['price'];
                $data[$key]['resource_type'] = (int)$val['resource_type'];
                $data[$key]['sold_num'] = (int)$val['sold_num'];
                $data[$key]['view_num'] = (int)$val['view_num'];
                $data[$key]['is_schedule_over'] = (int)$val['is_schedule_over'];
                $data[$key]['bet_status'] = (int)$val['bet_status'];
                $data[$key]['resource_status'] = (int)$val['resource_status'];
                $data[$key]['release_time_friendly'] = $val['release_time_friendly'];
            }
        }
        $this->responseJson(['total' => $resourceTotal, 'list' => $data]);
    }

    /**
     * 修改料信息状态
     */
    public function operationResourceStatus(){
        $param = $this->checkApiParam(['user_id', 'expert_id', 'resource_id', 'operation_code']);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);
        $resourceId = intval($param['resource_id']);
        //操作码    1:发布、上架 2:下架 4:已删除
        $operationCode = intval($param['operation_code']);

        $resourceModel = new ResourceModel();
        //操作修改料状态
        $res = $resourceModel->operationResourceStatus($resourceId, $operationCode, $userId, $expertId);
        if ($res === true) {
            $this->responseJson();
        } else {
            $this->responseJsonError($res);
        }
    }

    /**
     * 专家查看料详情
     */
    public function expertInfo(){
        $param = $this->checkApiParam(['user_id', 'expert_id', 'resource_id']);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);
        $resourceId = intval($param['resource_id']);

        $data = [];

        $expertModel = new ExpertModel();
        //获取专家信息
        $expertInfo = $expertModel->getExpertInfo($expertId);

        $data['expert'] = $expertInfo;

        $resourceModel = new ResourceModel();
        //获取料信息
        $resourceInfo = $resourceModel->getResourceDetailedInfo($resourceId);
        if (empty($resourceInfo)) {
            //料信息不存在
            $this->responseJsonError(2001);
        }
        if ($expertId != $resourceInfo['expert_id']) {
            //料信息不存在
            $this->responseJsonError(101);
        }

        $data['resource'] = $resourceInfo;
        $this->responseJson($data);
    }

    /**
     * 料的售卖记录
     */
    public function salesRecord(){
        $param = $this->checkApiParam(['user_id', 'expert_id', 'resource_id'], ['page' => 1, 'pagesize' => 9]);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);
        $resourceId = intval($param['resource_id']);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);

        //获取料详情
        $resourceModel = new ResourceModel();
        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        if($resourceInfo['expert_id'] != $expertId){
            $this->responseJsonError(101);
        }

        //获取订单列表
        $orderModel = new OrderModel();
        $orderList = $orderModel->getResourceOrderList($resourceId, $page, $pagesize);

        //获取订单总数
        $soldNum = $orderModel->getResourceSoldNum($resourceId);

        //获取订单总额
        $soldMoney = $orderModel->getResourceSoldMoney($resourceId);

        $data = [];
        $data['sold_num'] = $soldNum;
        $data['sold_money'] = $soldMoney;
        $data['list'] = $orderList;

        $this->responseJson($data);
    }


}