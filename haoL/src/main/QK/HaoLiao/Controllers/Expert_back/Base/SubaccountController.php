<?php
/**
 * 专家子账户相关管理
 * User: YangChao
 * Date: 2018/10/10
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use QK\HaoLiao\Controllers\Expert\ExpertController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertSubaccountModel;
use QK\HaoLiao\Model\UserModel;

class SubaccountController extends ExpertController {

    //最大绑定子账户数
    protected $_maxSubaccountTotal = 2;

    /**
     * 获取绑定子账户列表
     */
    public function getSubaccountList() {
        $param = $this->checkApiParam(['user_id', 'expert_id'], ['page' => 1, 'pagesize' => 10]);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);
        $page = trim($param['page']);
        $pagesize = trim($param['pagesize']);

        //获取用户身份
        $expertModel = new ExpertModel();
        $identity = $expertModel->checkIdentity($userId);
        if ($identity != 3 || $userId != $expertId) {
            //判断是否为主账户
            $this->responseJsonError(1208);
        }

        $data = [];
        $expertSubaccountModel = new ExpertSubaccountModel();
        $subaccountList = $expertSubaccountModel->getSubaccountList($expertId, $page, $pagesize);
        if (!empty($subaccountList)) {
            $userModel = new UserModel();
            foreach ($subaccountList as $key => $val) {
                $userInfo = $userModel->getUserInfo($val['user_id']);
                unset($subaccountList[$key]['user_id']);
                $subaccountList[$key]['subaccount_id'] = $userInfo['user_id'];
                $subaccountList[$key]['nick_name'] = $userInfo['nick_name'];
                $subaccountList[$key]['headimgurl'] = $userInfo['headimgurl'];
            }
        }
        $data['subaccount'] = $subaccountList;

        //判定子账户是否达到上线
        $data['is_top'] = count($subaccountList) >= $this->_maxSubaccountTotal ? 1 : 0;
        $this->responseJson($data);
    }

    /**
     * 主账户解除绑定
     */
    public function removeBind() {
        $param = $this->checkApiParam(['user_id', 'expert_id', 'subaccount_id']);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);
        $subaccount_id = intval($param['subaccount_id']);

        //获取用户身份
        $expertModel = new ExpertModel();
        $identity = $expertModel->checkIdentity($userId);
        if ($identity != 3 || $userId != $expertId) {
            //判断是否为主账户
            $this->responseJsonError(1208);
        }

        $data = [];

        //获取用户绑定数据
        $expertSubaccountModel = new ExpertSubaccountModel();
        $bindInfo = $expertSubaccountModel->getUserBindInfo($subaccount_id);

        if (!empty($bindInfo)) {
            if ($bindInfo['expert_id'] != $expertId) {
                //绑定请求信息不符
                $this->responseJsonError(1201);
            }
            $userId = $bindInfo['user_id'];
            //解除绑定操作
            $res = $expertSubaccountModel->operationBind($userId, $expertId, 0);
            if ($res) {
                //修改用户身份为普通用户
                $userModel = new UserModel();
                $userModel->updateUser($userId, ['identity' => 1]);

                $this->responseJson();
            } else {
                $this->responseJsonError(1202);
            }

        } else {
            //没有该绑定请求
            $this->responseJsonError(101);
        }
    }

    /**
     * 获取绑定唯一码
     */
    public function getBindOnlyCode() {
        $param = $this->checkApiParam(['user_id', 'expert_id']);
        $userId = intval($param['user_id']);
        $expertId = intval($param['expert_id']);

        //获取用户身份
        $expertModel = new ExpertModel();
        $identity = $expertModel->checkIdentity($userId);
        if ($identity != 3 || $userId != $expertId) {
            //判断是否为主账户
            $this->responseJsonError(1208);
        }

        $expertSubaccountModel = new ExpertSubaccountModel();
        //判定子账户数量是否已满
        $subaccountTotal = $expertSubaccountModel->getSubaccountTotal($expertId);
        if ($subaccountTotal >= $this->_maxSubaccountTotal) {
            $this->responseJsonError(1207);
        }
        //生成唯一码
        $onlyCode = $expertSubaccountModel->setBindOnlyCode($expertId);
        $data['only_code'] = $onlyCode;
        $this->responseJson($data);
    }

    /**
     * 子账户获取绑定请求信息
     */
    public function getBindRequest() {
        $param = $this->checkApiParam(['user_id', 'only_code']);
        $userId = intval($param['user_id']);
        $onlyCode = trim($param['only_code']);

        //获取用户绑定主账户ID
        $expertSubaccountModel = new ExpertSubaccountModel();
        $expertId = $expertSubaccountModel->getExpertIdByBindOnlyCode($onlyCode);

        if (!empty($expertId)) {
            $data = [];
            //获取子账户信息
            $userModel = new UserModel();
            $userInfo = $userModel->getUserInfo($userId);

            switch ($userInfo['identity']) {
                case 2:
                    //您已绑定其他主账户
                    $this->responseJsonError(1204);
                    break;
                case 3:
                    //判断用户是否为专家用户
                    $this->responseJsonError(1209);
                    break;
            }

            //获取主账户信息
            $expertModel = new ExpertModel();
            $expertInfo = $expertModel->getExpertInfo($expertId);

            $data['expert_id'] = $expertInfo['expert_id'];
            $data['expert_name'] = $expertInfo['expert_name'];
            $data['expert_headimgurl'] = $expertInfo['headimgurl'];
            $data['subaccount_id'] = $userInfo['user_id'];
            $data['subaccount_nick_name'] = $userInfo['nick_name'];
            $data['subaccount_headimgurl'] = $userInfo['headimgurl'];
            $this->responseJson($data);
        } else {
            //该绑定邀请已失效
            $this->responseJsonError(1205);
        }
    }

    /**
     * 子账号确定/取消绑定请求
     */
    public function operationBindInvitation() {
        $param = $this->checkApiParam(['user_id', 'only_code', 'is_pass']);
        $userId = intval($param['user_id']);
        $onlyCode = trim($param['only_code']);
        $isPass = intval($param['is_pass']);

        //获取用户绑定主账户ID
        $expertSubaccountModel = new ExpertSubaccountModel();
        $expertId = $expertSubaccountModel->getExpertIdByBindOnlyCode($onlyCode);

        if (!empty($expertId)) {
            if ($isPass) {
                //通过子账户绑定邀请

                //获取用户身份
                $expertModel = new ExpertModel();
                $identity = $expertModel->checkIdentity($userId);

                switch ($identity) {
                    case 2:
                        //您已绑定其他主账户
                        $this->responseJsonError(1204);
                        break;
                    case 3:
                        //判断用户是否为专家用户
                        $this->responseJsonError(1209);
                        break;
                }
                $data = [];
                $data['user_id'] = $userId;
                $data['expert_id'] = $expertId;
                $data['subaccount_status'] = 1;
                $data['bind_time'] = time();

                $res = $expertSubaccountModel->setUserBindInfo($data);
                if ($res) {
                    //修改用户身份为专家子用户
                    $userModel = new UserModel();
                    $userModel->updateUser($userId, ['identity' => 2]);

                    $expertSubaccountModel->delBindOnlyCode($onlyCode);
                    $this->responseJson();
                }
                //绑定主账户操作失败
                $this->responseJsonError(1206);
            } else {
                //拒绝子账户绑定邀请
                $expertSubaccountModel->delBindOnlyCode($onlyCode);
                $this->responseJson();
            }
        } else {
            //该绑定邀请已失效
            $this->responseJsonError(1205);
        }
    }


}
