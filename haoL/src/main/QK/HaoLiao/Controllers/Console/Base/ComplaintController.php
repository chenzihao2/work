<?php
/**
 * 投诉相关接口
 * User: YangChao
 * Date: 2018/11/19
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\ComplaintModel;
use QK\HaoLiao\Model\UserModel;

class ComplaintController extends ConsoleController {

    /**
     * 获取投诉列表
     */
    public function complaintList(){
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 15]);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $query = json_decode($params['query'], true);
        $userId = isset($query['user_id']) ? $query['user_id'] : '';
        $createTimeStart = isset($query['create_time_start']) ? $query['create_time_start'] : '';
        $createTimeEnd = isset($query['create_time_end']) ? $query['create_time_end'] : '';


        $complaintModel = new ComplaintModel();
        $where = [];
        $where['user_id'] = $userId;
        $where['create_time_start'] = $createTimeStart;
        $where['create_time_end'] = $createTimeEnd;
        if (!empty($query['module_type'])) {
            @list($model_type, $type) = explode('-', $query['module_type']);
            $where['module_type'] = $model_type;
            if (!empty($type)) {
                $where['type'] = $type;
            }
        }
        if (!empty($query['user_source'])) {
            $where['user_source'] = $query['user_source'];
        }

        $complaintTotal = $complaintModel->getComplaintTotal($where);

        $complaintList = $complaintModel->getComplaintList($where, $page, $pageSize);
        
        if(!empty($complaintList)){
            $userModel = new UserModel();
            foreach($complaintList as $key => $val){
                $userInfo = $userModel->getUserInfo($val['user_id']);
                $image = json_decode($val['image'], true);
                $complaintList[$key]['price'] = isset($complaintList[$key]['price']) ? number_format($complaintList[$key]['price'] / 100, 2, '.', '') : "0.00";
                if(!empty($image)){
                    foreach($image as $k => $v){
                        $image[$k] = $v;
                    }
                }
                $complaintList[$key]['image'] = $image;
                $complaintList[$key]['nick_name'] = $userInfo['nick_name'];
                $complaintList[$key]['headimgurl'] = $userInfo['headimgurl'];
            }
        }

        $data = [];
        $data['total'] = $complaintTotal;
        $data['list'] = $complaintList;

        $this->responseJson($data);
    }

}