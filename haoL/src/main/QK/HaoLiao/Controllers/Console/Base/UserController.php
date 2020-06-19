<?php
/**
 * 用户管理
 * User: YangChao
 * Date: 2018/11/07
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertPresentAccountModel;
use QK\HaoLiao\Model\ExpertSubaccountModel;
use QK\HaoLiao\Model\ExpertSubscribeModel;
use QK\HaoLiao\Model\UserModel;

class UserController extends ConsoleController {

    /**
     * 用户管理列表
     */
    public function userList(){
        $param = $this->checkApiParam([], ['query' => '','order'=>'', 'page' => 1, 'pagesize' => 20]);
//        $where = ['user_id' => 17, 'user_status' => 1, 'identity' => 2, 'create_time_start' => 1540297856, 'create_time_end' => 1540462387];
        $where = json_decode($param['query'], true);
        $order = json_decode($param['order'], true);
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $userModel = new UserModel();
        $userList = $userModel->getUserList($where, $page, $pagesize,$order);
        $this->responseJson($userList);
    }

    /**
     * 获取用户详细信息
     */
    public function userInfo(){
        $param = $this->checkApiParam(['user_id']);
        $userId = intval($param['user_id']);

        $info = [];

        //获取普通用户信息
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        $userExtraInfo = $userModel->getUserExtraInfo($userId);
        $userInfo['headimgurl']=$userInfo['headimgurl']?$userInfo['headimgurl']:'https://hl-static.haoliao188.com/resource/202002/27/ca2ed48d3859896ff049903652c287db.png';

        //用户信息
        $info['user']['info'] = array_merge($userInfo, $userExtraInfo);

        //专家信息
        $info['expert'] = [];

        if($userInfo['identity'] == 1){
            //普通用户

        } else {
            $expertSubaccountModel = new ExpertSubaccountModel();
            if($userInfo['identity'] == 2){
                //专家子用户
                $userBindInfo = $expertSubaccountModel->getUserBindInfo($userId);
                $expertId = $userBindInfo['expert_id'];
            } elseif($userInfo['identity'] == 3) {
                //专家用户
                $expertId = $userId;
            }

            $expertModel = new ExpertModel();
            $expertInfo = $expertModel->getExpertInfo($expertId);

            $expertExtraModel = new ExpertExtraModel();
            $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

            $info['expert']['info'] = array_merge($expertInfo, $expertExtraInfo);

            //子账户列表
            $subaccountList = [];
            $expertSubaccountList = $expertSubaccountModel->getSubaccountList($expertId, 1, 10);
            if(!empty($expertSubaccountList)){
                foreach($expertSubaccountList as $key => $val){
                    $subaccountList[$key] = $userModel->getUserInfo($val['user_id']);
                }
            }
            $info['expert']['subaccount'] = $subaccountList;

            //专家订阅信息
            $expertSubscribeModel = new ExpertSubscribeModel();
            $expertSubscribe = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30);

            $info['expert']['subscribe'] = $expertSubscribe;

            //提现信息
            $expertPresentAccountModel = new ExpertPresentAccountModel();
            $presentAccountList = $expertPresentAccountModel->getExpertPresentAccountList($expertId);

            $info['expert']['present_account'] = $presentAccountList;
        }
        $this->responseJson($info);
    }

    /**
     * 修改用户状态
     */
    public function statusUpdate(){
        $params = $this->checkApiParam(['user_id', 'user_status']);
        $userId = $params['user_id'];
        $userStatus = $params['user_status'];
        $update['user_status'] = $userStatus;
        $userModel = new UserModel();
        $userModel->updateUser($userId, $update);
        $this->responseJson();
    }


    /*
     * 禁止聊天
     */
    public function forbiddenSay(){
        $params = $this->checkApiParam(['user_id', 'forbidden_say']);
        $update['forbidden_say'] = $params['forbidden_say'];
        $userModel = new UserModel();
        $userModel->updateUser($params['user_id'], $update);
        $this->responseJson();
    }

        /*
        * 禁止评论
         * user_id
         * forbidden_day:禁言天数:0 正常，3：三天，7：七天，-1：永久禁言
        */
    public function isForbidden(){
        $params = $this->checkApiParam(['user_id', 'forbidden_day']);
        $update['forbidden_day'] = $params['forbidden_day'];
        $update['forbidden_time']=$params['forbidden_day']==0?0:time();
        $userModel = new UserModel();
        $userModel->updateUser($params['user_id'], $update);
        $this->responseJson();
    }

}