<?php
/**
 * 投诉相关接口
 * User: twenj
 * Date: 2018/11/17
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Model\ComplaintModel;
use QK\HaoLiao\Controllers\User\Base\ComplaintController as Complaint;

class ComplaintController extends Complaint {

    /**
     * 用户反馈
     */
    public function feedback() {
        $this->checkToken();
        $param = $this->checkApiParam(['user_id'], ['type' => 1, 'content' => '', 'image' => '', 'phone' => '']);
        $userId = intval($param['user_id']);
        $type = intval($param['type']);
        $content = trim($param['content']);
        $image = $param['image'];
        $phone = $param['phone'];

        $data = [];
        $data['user_id'] = $userId;
        $data['type'] = $type;
        $data['content'] = $content;
        $data['image'] = $image;
        $data['phone'] = $phone;
        $data['module_type'] = 2;
        $complaintModel = new ComplaintModel();
        $complaintId = $complaintModel->createComplain($data);
        $this->responseJson(['complaint_id' => $complaintId]);
    }

}