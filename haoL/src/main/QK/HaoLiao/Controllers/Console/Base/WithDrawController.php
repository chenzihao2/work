<?php
/**
 * 提现管理
 * User: WangHui
 * Date: 2018/11/16
 * Time: 下午4:16
 */

namespace QK\HaoLiao\Controllers\Console\Base;


use QK\HaoLiao\Common\PayParams;
use QK\HaoLiao\Common\WeChatParams;
use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\DistExtraModel;
use QK\HaoLiao\Model\DistWithdrawModel;
use QK\HaoLiao\Model\ExpertExtraModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertPresentAccountModel;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\Model\WithDrawModel;
use QK\WeChat\Pay\WeChatWithDraw;
use QK\WeChat\WeChatSendMessage;

class WithDrawController extends ConsoleController {

    /**
     * 提现列表
     */
    public function withDrawList() {
        $params = $this->checkApiParam(['query', 'page', 'pagesize']);
        $query = json_decode($params['query'], 1);
        $page = $params['page'];
        $pagesize = $params['pagesize'];
        $withDrawModel = new WithDrawModel();
        $list = $withDrawModel->getWithDrawList($query, $page, $pagesize);
        $this->responseJson($list);
    }

    /**
     * 提现详情
     */
    public function withDrawInfo() {
        $params = $this->checkApiParam(['expert_id', 'withdraw_id'], ['page' => 1, 'pagesize' => 10]);
        $expertId = $params['expert_id'];
        $withdrawId = $params['withdraw_id'];
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $withDrawModel = new WithDrawModel();
        $list = $withDrawModel->getMoneyChangeList($expertId, $page, $pageSize, $withdrawId);
        $count = $withDrawModel->getMoneyChangeCount($expertId, $withdrawId);
        $data['list'] = $list;
        $data['count'] = $count;
        $this->responseJson($data);
    }

    /**
     * 提现处理
     */
    public function execute() {
        //审核、通过、拒绝
        $params = $this->checkApiParam(['withdraw_id', 'type']);
        $withdrawId = $params['withdraw_id'];
        $type = $params['type'];
        $withDrawModel = new WithDrawModel();
        $status = $withDrawModel->getWithDrawStatus($withdrawId);
        if ($type == 2 || $type == 4) {
            //提交中才允许审核或拒绝
            if ($status == 1) {
                $update = [];
                $update['withdraw_status'] = 2;
                $update['check_time'] = time();
                $withDrawModel->updateWithDraw($withdrawId, $update);
                $this->responseJson([]);
            } else {
                $this->responseJsonError('101');
            }
        } else {
            if ($type == 3) {
                //审核过才允许提现
                if ($status == 2) {
                    $payParams = new PayParams();
                    $withDrawChannel = $payParams->getWithDrawChannel();
                    if ($withDrawChannel == 1) {
                        $result = $this->weChatWithDraw($withdrawId);
                        if ($result['return_code'] == 'SUCCESS') {
                            if ($result['result_code'] == 'FAIL') {
                                //提款失败
                                $this->responseJsonError(5000, $result['err_code_des']);
                            }
                        }
                    } else {
                        $result = $this->aliPayWithDraw($withdrawId);
                        if ($result['status'] == false) {
                            //提款失败
                            $this->responseJsonError(5000, $result['msg']);
                        }
                    }
                    //提款成功
                    $update = [];
                    $update['withdraw_status'] = 4;
                    $update['check_time'] = time();
                    $withDrawModel->updateWithDraw($withdrawId, $update);

                    //更新专家扩展表
                    $withDrawInfo = $withDrawModel->getWithDrawInfo($withdrawId);
                    $expertExtraModel = new ExpertExtraModel();
                    $expertId = $withDrawInfo['expert_id'];
                    $withdraw = $withDrawInfo['withdraw_money'];
                    $service = $withDrawInfo['service_fee'];
                    $expertExtraModel->updateExpertExtraWithdrawInfo($expertId, $withdraw, $service);


                    // 模版内容
                    $messageData = array();
                    $messageData['first'] = [
                        'value' => '您的提现已完成，请您注意查收。'
                    ];
                    $messageData['keyword1'] = [
                        'value' => $withdraw
                    ];
                    $messageData['keyword2'] = [
                        'value' => date('Y-m-d H:i', $update['check_time'])
                    ];
                    $messageData['remark'] = [
                        'value' => "感谢您的使用！"
                    ];

                    //提现通知
                    $weChatId = $GLOBALS['weChatId'] = $this->_appSetting->getConstantSetting('DEFAULT_WECHATID');
                    $weChatParams = new WeChatParams();
                    $accessToken = $this->weChatToken();
                    $weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
                    $appId = $weChatConfig['id'];
                    $appKey = $weChatConfig['appKey'];
                    $weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);
                    $templateId = "Lrst15gXZx4G-6J6EhPurgUhlsfR9RS6qrsD7Sfhycc";
                    // 获取专家微信信息
                    $userModel = new UserModel();
                    $userWeChatInfo = $userModel->getUserWeChatInfo($expertId);
                    $userOpenId = $userWeChatInfo['openid'];
                    $url = $this->_appSetting->getConstantSetting("DOMAIN_EXPERT") . '#/account';
                    if(!empty($userOpenId)){
                        $weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData,$url);
                    }

                    $this->responseJson([]);
                }
            }
        }
    }

    /**
     * 微信提款
     * @param $withdrawId
     * @return mixed
     */
    private function weChatWithDraw($withdrawId) {
        $withDrawModel = new WithDrawModel();
        $withDrawInfo = $withDrawModel->getWithDrawInfo($withdrawId);
        $expertId = $withDrawInfo['expert_id'];
        //获取用户微信openid
        $userModel = new UserModel();
        $weChatInfo = $userModel->getUserWithDrawWeChatInfo($expertId);
        $openid = $weChatInfo['openid'];
        //微信渠道id
        $weChatId = $weChatInfo['wechat_id'];
        //记录提现微信信息
        $update['openid'] = $openid;
        $update['wechat_id'] = $weChatId;
        $withDrawModel->updateWithDraw($withdrawId, $update);
        $update['withdraw_status'] = 3;
        $update['complete_time'] = time();
        $update[''] = time();
        $withDrawModel->updateWithDraw($withdrawId, $update);

        $amount = $withDrawInfo['withdraw_money'] * 100;//元转分
        $programWeChatInfo = $this->_appSetting->getConstantSetting("WeChatLogin:" . $weChatId);
        $appId = $programWeChatInfo['WeChat-Mini-Id'];

        //微信支付参数
        $payParams = new PayParams();
        $weChatWithDraw = $payParams->getWithDrawInfo();
        $mchId = $weChatWithDraw['MchId'];
        $mchSecretKey = $weChatWithDraw['Mch-Secret-Key'];
        $ip = $weChatWithDraw['IP'];
        $certPath = $weChatWithDraw['CertPath'];
        $keyPath = $weChatWithDraw['KeyPath'];
        $weChatWithDraw = new WeChatWithDraw($appId, $mchId, $mchSecretKey, $certPath, $keyPath, $ip);
        return $weChatWithDraw->withdraw($openid, $withdrawId, $amount, '打款描述');
    }

    /**
     * 支付宝提款
     * @param $withdrawId
     * @return mixed
     * @throws \Exception
     */
    private function aliPayWithDraw($withdrawId) {
        $withDrawModel = new WithDrawModel();
        $withDrawInfo = $withDrawModel->getWithDrawInfo($withdrawId);
        $amount = $withDrawInfo['withdraw_money'];//元
        $expertId = $withDrawInfo['expert_id'];
        //获取支付宝提款账号
        $expertPresentAccount = new ExpertPresentAccountModel();
        $aliPayInfo = $expertPresentAccount->getPresentAccount($expertId, 1);
        $desc = "打款描述";
        $account = $aliPayInfo['account'];
        $expertModel = new ExpertModel();
        $expertInfo = $expertModel->getExpertInfo($expertId);
        $realName = $expertInfo['real_name'];
        //微信支付参数
        $payParams = new PayParams();
        $weChatWithDraw = $payParams->getWithDrawInfo(2);
        $aop = new \AopClient ();
        $aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
        $aop->appId = $weChatWithDraw['AppId'];
        $aop->rsaPrivateKey = $weChatWithDraw['Private_Key'];
        $aop->alipayrsaPublicKey = $weChatWithDraw['AliPay_Public_Key'];
        $aop->apiVersion = '1.0';
        $aop->signType = 'RSA2';
//		$aop->postCharset='GBK';
        $aop->format = 'json';
        $request = new \AlipayFundTransToaccountTransferRequest ();

        $request->setBizContent("{" . "\"out_biz_no\":\"" . $withdrawId . "\"," . "\"payee_type\":\"ALIPAY_LOGONID\"," . "\"payee_account\":\"" . $account . "\"," . "\"amount\":\"" . $amount . "\"," . "\"payer_show_name\":\"给料官方\"," . "\"payee_real_name\":\"" . $realName . "\"," . "\"remark\":\"$desc\"" . "}");
        $result = $aop->execute($request);
        $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
        $resultCode = $result->$responseNode->code;
        if (!empty($resultCode) && $resultCode == 10000) {
            $finalResult['status'] = true;
            $finalResult['msg'] = $result->$responseNode->msg;
        } else {
            $finalResult['status'] = false;
            $finalResult['msg'] = $result->$responseNode->msg . $result->$responseNode->sub_code . $result->$responseNode->sub_msg;
        }
        return $finalResult;
    }

    /**
     * 获取分销商提现列表
     */
    public function distWithDrawList(){
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $query = json_decode($params['query'], true);

        $distWithdrawModel = new DistWithdrawModel();
        $list = $distWithdrawModel->getDistWithDrawList($query, $page, $pageSize);
        if(!empty($list['list'])){
            $distExtraModel = new DistExtraModel();
            $userModel = new UserModel();
            foreach($list['list'] as $key => $val){
                $val['withdraw_money_yuan'] = bcdiv($val['withdraw_money'], 100, 2);
                $distId = $val['dist_id'];
                $userInfo = $userModel->getUserInfo($distId);
                $val['nick_name'] = $userInfo['nick_name'];
                $val['headimgurl'] = $userInfo['headimgurl'];

                $distExtraInfo = $distExtraModel->getDistExtraInfo($distId);
                $val['income'] = $distExtraInfo['income'];
                $val['balance'] = $distExtraInfo['balance'];
                $val['withdrawed'] = $distExtraInfo['withdrawed'];
                $val['income_yuan'] = $distExtraInfo['income_yuan'];
                $val['balance_yuan'] = $distExtraInfo['balance_yuan'];
                $val['withdrawed_yuan'] = $distExtraInfo['withdrawed_yuan'];
                $val['gain_user'] = $distExtraInfo['gain_user'];
                $list['list'][$key] = $val;
            }
        }

        $this->responseJson($list);
    }

    /**
     * 设置分销商提现状态
     */
    public function setDistWithDraw(){
        $params = $this->checkApiParam(['withdraw_id', 'withdraw_status']);
        $withdrawId = intval($params['withdraw_id']);
        $withdrawStatus = intval($params['withdraw_status']);
        $distWithdrawModel = new DistWithdrawModel();

        $distWithdrawModel->setDistWithDraw($withdrawId, ['withdraw_status' => $withdrawStatus]);
        $this->responseJson();
    }
}