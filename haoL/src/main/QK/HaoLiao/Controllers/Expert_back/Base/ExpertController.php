<?php
/**
 * 专家账户相关管理
 * User: YangChao
 * Date: 2018/10/9
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use Firebase\JWT\JWT;
use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Controllers\Expert\ExpertController as Expert;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertPresentAccountModel;
use QK\HaoLiao\Model\ExpertWithDrawModel;

class ExpertController extends Expert {

    /**
     * 获取专家信息
     */
    public function info() {
        $this->setTokenCheck(false);
        $param = $this->checkApiParam(['user_id']);
        $userId = $param['user_id'];
        $expertModel = new ExpertModel();
        $identity = $expertModel->checkIdentity($userId);
        if ($identity == 1) {
            //普通用户，专家信息置为空
            $expertInfo = [];
        } else {
            if ($identity == 2) {
                //子账户获取专家主id
                $subAccountExpertInfo = $expertModel->getExpertInfoBySubAccount($userId);
                $expertId = $subAccountExpertInfo['expert_id'];
                $subAccountStatus = $subAccountExpertInfo['subaccount_status'];
            } else {
                $expertId = $userId;
            }
            //获取专家信息
            $expertInfo = $expertModel->getExpertInfo($expertId);
            $tokenArray = [
                "uid" => $expertId, "nickname" => $expertInfo['expert_name'], "iat" => time(), "exp" => time() + 7200,
            ];
            $key = $this->_appSetting->getConstantSetting('JWT-KEY');

            $token = JWT::encode($tokenArray, $key);

            $expertInfo['expert_token'] = $token;
            if (isset($subAccountStatus)) {
                $expertInfo['sub_account_status'] = $subAccountStatus;
            }
        }

        $data['is_expert'] = $identity;
        $data['info'] = $expertInfo;
        $this->responseJson($data);
    }


    /**
     * 提交专家信息
     */
    public function submitInfo() {
        $params = $this->checkApiParam([
            'user_id', 'real_name', 'expert_name', 'idcard_number', 'bank', 'bank_number', 'alipay_number', 'phone', 'headimgurl',
        ]);

        $userId = $params['user_id'];
        $expertModel = new ExpertModel();
        $identity = $expertModel->checkIdentity($userId);
        if($identity==1){
            if (CommonHandler::newInstance()->checkIdCard($params['idcard_number'])) {
                $expertModel->newExpert($params);
                $this->responseJson();
            } else {
                $this->responseJsonError(107);
            }
        }else{
            $expertInfo = $expertModel->getExpertInfo($userId);
            if(!empty($expertInfo) && $expertInfo['status'] == 4){
                //审核未通过用户重新提交
                if (CommonHandler::newInstance()->checkIdCard($params['idcard_number'])) {
                    $expertModel->updateExpertApply($userId, $params);
                    $this->responseJson();
                } else {
                    $this->responseJsonError(107);
                }
            }
            $this->responseJsonError(103);
        }
    }

    /**
     * 专家账户设置信息
     */
    public function accountInfo() {
        $param = $this->checkApiParam(['user_id', 'expert_id']);
        $expertId = intval($param['expert_id']);

        //获取专家账户信息
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($expertId);

        //获取专家账户扩展信息
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

        //获取专家提现账户列表
        $expertPresentAccountModel = new ExpertPresentAccountModel();
        $expertPresentAccountList = $expertPresentAccountModel->getExpertPresentAccountList($expertId);

        $data = [];
        $data['expert_id'] = $expertId;
        $data['expert_name'] = $expertInfo['expert_name'];
        $data['real_name'] = $expertInfo['real_name'];
        $data['headimgurl'] = $expertInfo['headimgurl'];
        $data['phone'] = intval($expertInfo['phone']);
        $data['subscribe_num'] = intval($expertExtraInfo['subscribe_num']);
        $data['follow_num'] = intval($expertExtraInfo['follow_num']);
        foreach ($expertPresentAccountList as $key => $val) {
            if ($val['type'] == 1) {
                $data['alipay_account'] = $val['account'];
            } elseif ($val['type'] == 2) {
                $data['bank_account'] = $val['account'];
                $data['bank'] = $val['bank'];
            }
        }
        $this->responseJson($data);
    }

    /**
     * 专家订阅信息
     */
    public function expertSubInfo() {
        $param = $this->checkApiParam(['expert_id']);

        $expertId = $param['expert_id'];
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($expertId);
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);

        $data = [];
        $data['expert_id'] = $expertId;
        $data['expert_name'] = $expertInfo['expert_name'];
        $data['real_name'] = $expertInfo['real_name'];
        $data['headimgurl'] = $expertInfo['headimgurl'];
        $data['subscribe_num'] = intval($expertExtraInfo['subscribe_num']);
        $data['follow_num'] = intval($expertExtraInfo['follow_num']);
        $this->responseJson($data);
    }

    /**
     * 专家金额信息
     */
    public function expertMoneyInfo() {
        $param = $this->checkApiParam(['expert_id']);
        $expertId = $param['expert_id'];
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($expertId);
        $expertExtraModel = new ExpertExtraModel();
        $expertExtraInfo = $expertExtraModel->getExpertExtraInfo($expertId);
        $expertWithDrawModel = new ExpertWithDrawModel();
        $withDrawInfo = $expertWithDrawModel->getWithDrawing($expertId);

        $data = [];
        $data['expert_id'] = $expertId;
        $data['expert_name'] = $expertInfo['expert_name'];
        $data['real_name'] = $expertInfo['real_name'];
        $data['headimgurl'] = $expertInfo['headimgurl'];
        $data['income'] = $expertExtraInfo['income'];
        $data['balance'] = $expertExtraInfo['balance'];
        $data['withdrawed'] = $expertExtraInfo['withdrawed'];
        $data['freezing'] = $expertExtraInfo['freezing'];
        $data['withdrawing'] = $withDrawInfo['withdraw_money'] ? $withDrawInfo['withdraw_money'] : '0.00';
        if ($data['withdrawing'] > 0) {
            $data['is_withdraw'] = 0;
        } else {
            $data['is_withdraw'] = 1;
        }
        $this->responseJson($data);

    }


}
