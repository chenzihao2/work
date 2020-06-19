<?php
/**
 * 分销商用户中心
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\Controllers\Dist\Base;

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Common\SmsSend;
use QK\HaoLiao\Common\StringHandler;
use QK\HaoLiao\Controllers\Dist\DistController as Dist;
use QK\HaoLiao\Model\DistExtraModel;
use QK\HaoLiao\Model\DistModel;
use QK\HaoLiao\Model\DistMoneyChangeModel;
use QK\HaoLiao\Model\DistWithdrawModel;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\SmsModel;
use QK\HaoLiao\Model\UserModel;

class DistController extends Dist {

    /**
     * 分销商及用户信息
     */
    public function info() {
        $param = $this->checkApiParam(['user_id']);
        $userId = $param['user_id'];

        //获取用户详情
        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);
        $data = [];
        //是否经销商  1：是 0：否
        $data['is_dist'] = 0;
        $data['user']['user_id'] = $userId;
        $data['user']['phone'] = intval($userInfo['phone']);
        $data['user']['nick_name'] = $userInfo['nick_name'];
        $data['user']['sex'] = $userInfo['sex'];
        $data['user']['headimgurl'] = $userInfo['headimgurl'];
        $data['user']['user_status'] = $userInfo['user_status'];

        //获取分销商详情
        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfo($userId);
        $data['dist'] = [];
        if(!empty($distInfo)){
            $distExtraModel = new DistExtraModel();
            $distExtraInfo = $distExtraModel->getDistExtraInfo($userId);

            $distWithdrawModel = new DistWithdrawModel();
            $distWithDrawInfo = $distWithdrawModel->getDistWithDraw($userId);

            $data['is_dist'] = 1;
            $data['dist']['dist_id'] = $userId;
            $data['dist']['phone'] = $distInfo['phone'];
            $data['dist']['dist_name'] = $distInfo['dist_name'];
            $data['dist']['poster'] = $distInfo['poster'];
            $data['dist']['dist_status'] = $distInfo['dist_status'];
            $data['dist']['income'] = $distExtraInfo['income'];
            $data['dist']['balance'] = $distExtraInfo['balance'];
            $data['dist']['income_yuan'] = $distExtraInfo['income_yuan'];
            $data['dist']['balance_yuan'] = $distExtraInfo['balance_yuan'];
            $data['dist']['withdraw_money_yuan'] = isset($distWithDrawInfo['withdraw_money_yuan']) ? $distWithDrawInfo['withdraw_money_yuan'] : '0.00';
        }

        $this->responseJson($data);
    }
    

    /**
     * 发送验证码
     */
    public function sendCode(){
        $params = $this->checkApiParam(['user_id', 'phone']);

        //获取分销商详情
        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfo($params['user_id']);
        if(isset($distInfo['phone']) && !empty($distInfo['phone'])){
            $this->responseJsonError(1201);
        }

        $smsSend = new SmsSend();
        if (!$smsSend->mobileCheck($params['phone'])) {
            $this->responseJsonError(1105);
        }

        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfoByPhone($params['phone']);
        if(!empty($distInfo)){
            $this->responseJsonError(1001);
        }

        $smsModel = new SmsModel();
        $sendTimes = $smsModel->todaySendCount($params['phone']);
        if ($sendTimes > 3) {
            $this->responseJsonError(1102);
        }
        $code = rand(1111, 9999);
        $content = '注册验证码' . $code . '，五分钟内有效！为保障您的账户安全，请勿将验证码透露给他人。【好料精选】';
        $smsSend->send($params['phone'], $content);
        $smsModel->sendLog($params['phone'], $code, $params['user_id']);
        $redisModel = new RedisModel("user");
        $redisModel->redisSet(SMS_CODE . $params['phone'], $code, 300);
        $this->responseJson();
    }

    /**
     * 绑定手机号操作
     */
    public function bindPhone(){
        $param = $this->checkApiParam(['user_id', 'phone', 'code']);
        $userId = intval($param['user_id']);
        $phone = intval($param['phone']);
        $code = intval($param['code']);

        //获取分销商详情
        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfo($userId);
        if(isset($distInfo['phone']) && !empty($distInfo['phone'])){
            $this->responseJsonError(1201);
        }

        $redisModel = new RedisModel("user");
        $redisCode = $redisModel->redisGet(SMS_CODE . $phone);
        if ($code != $redisCode) {
            //验证失败
            $this->responseJsonError(1103);
        }

        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfoByPhone($phone);
        if(!empty($distInfo)){
            $this->responseJsonError(1001);
        }

        if($distInfo['phone']){
            $this->responseJsonError(1001);
        }


        $distCode = StringHandler::newInstance()->encode($userId);

        // 生成二维码
        $url = $this->_appSetting->getConstantSetting('DOMAIN_CUSTOMER') . '#/?dist_id=' . $distCode;
        $fileName = $distCode . '.jpg';
        $qrCode = CommonHandler::newInstance()->qrCode($url, $fileName);
        if(empty($qrCode)){
            $this->responseJsonError(110);
        }

        // 生成海报
        $poster = CommonHandler::newInstance()->makePoster($qrCode['fullPath'], $fileName);

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($userId);

        $data = [];
        $data['dist_id'] = $userId;
        $data['phone'] = $phone;
        $data['dist_name'] = $userInfo['nick_name'];
        $data['poster'] = $poster['url'];

        $res = $distModel->newDist($data);
        if($res){
            $this->responseJson();
        }

        $this->responseJsonError(101);
    }

    /**
     * 收益明细
     */
    public function incomeList(){
        $params = $this->checkApiParam(['user_id'], ['page' => 1, 'pagesize' => 10]);
        $userId = intval($params['user_id']);
        $page = intval($params['page']);
        $pagesize = intval($params['pagesize']);
        $distMoneyChangeModel = new DistMoneyChangeModel();
        $incomeList = $distMoneyChangeModel->getDistMoneyChangeList($userId, ['change_type' => 1], $page, $pagesize);
        $this->responseJson($incomeList);
    }


}