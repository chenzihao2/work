<?php
/**
 * 投诉相关接口
 * User: YangChao
 * Date: 2018/11/17
 */

namespace QK\HaoLiao\Controllers\User\Base;

use QK\HaoLiao\Model\ComplaintModel;
use QK\HaoLiao\Model\ResourceModel;

class ComplaintController extends UserController {

    /**
     * 获取投诉料信息页
     */
    public function resource(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id', 'resource_id']);
        $resourceId = intval($param['resource_id']);
        $resourceModel = new ResourceModel();
        $resourceInfo = $resourceModel->getResourceInfo($resourceId);
        $this->responseJson($resourceInfo);
    }

    /**
     * 提交投诉建议
     */
    public function submit(){
        $this->checkToken();
        $param = $this->checkApiParam(['user_id', 'resource_id'], ['content' => '', 'image' => '', 'price' => '']);
        $userId = intval($param['user_id']);
        $resourceId = intval($param['resource_id']);
        $content = trim($param['content']);
        $image = $param['image'];
        $price = $param['price'];

        $data = [];
        $data['user_id'] = $userId;
        $data['resource_id'] = $resourceId;
        $data['content'] = $content;
        $data['image'] = $image;
        $data['price'] = $price;
        $data['module_type'] = 1;
        $complaintModel = new ComplaintModel();
        $complaintId = $complaintModel->createComplain($data);
        $this->responseJson(['complaint_id' => $complaintId]);
    }

}