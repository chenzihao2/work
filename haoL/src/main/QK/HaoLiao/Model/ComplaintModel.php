<?php
/**
 * 投诉处理
 * User: YangChao
 * Date: 2018/11/17
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALComplaint;

class ComplaintModel extends BaseModel {

    public function __construct(){
        parent::__construct();
    }

    /**
     * 创建一个投诉建议 或 反馈
     * @param $data
     * @return mixed
     */
    public function createComplain($data){
        $data['create_time'] = time();
        if (!empty($data['price'])) {
            $data['price'] = $this->ncPriceYuan2Fen($data['price']);
        }
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($data['user_id']);
        $data['user_source'] = $userInfo['source'];
        $dalComplaint = new DALComplaint($this->_appSetting);
        return $dalComplaint->createComplain($data);
    }

    /**
     * 获取投诉建议列表
     * @param $where
     * @param $page
     * @param $pageSize
     * @return array|bool
     */
    public function getComplaintList($where, $page, $pageSize){
        $start = ($page - 1) * $pageSize;
        $dalComplaint = new DALComplaint($this->_appSetting);
        $complaintList = $dalComplaint->getComplaintList($where, $start, $pageSize);
        return$complaintList;
    }

    /**
     * 获取投诉建议总数
     * @param $where
     * @return mixed
     */
    public function getComplaintTotal($where){
        $dalComplaint = new DALComplaint($this->_appSetting);
        $complaintList = $dalComplaint->getComplaintTotal($where);
        return$complaintList;
    }

}