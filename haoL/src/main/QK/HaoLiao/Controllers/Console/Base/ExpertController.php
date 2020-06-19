<?php
/**
 * 专家管理
 * User: YangChao
 * Date: 2018/11/08
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Common\CommonHandler;
use QK\HaoLiao\Common\WeChatParams;
use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertPresentAccountModel;
use QK\HaoLiao\Model\ExpertRateModel;
use QK\HaoLiao\Model\ExpertSubaccountModel;
use QK\HaoLiao\Model\ExpertSubscribeModel;
use QK\HaoLiao\Model\RedisKeyManageModel;
use QK\HaoLiao\Model\UserModel;
use QK\HaoLiao\XML\ErrorCodeConfig;
use QK\WeChat\WeChatSendMessage;

class ExpertController extends ConsoleController {

    /**
     * 专家管理列表
     */
    public function expertList() {
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $query = json_decode($params['query'], true); //platform (-1/不存在: all；0:两端都可，1:百度小程序，2:H5)
        $expertModel = new ExpertModel();
        $list = $expertModel->getManageExpertList($query, $page, $pageSize);
        $this->responseJson($list);
    }


    /**
     * 推荐修改
     */
    public function recommendUpdate() {
        $params = $this->checkApiParam(['expert_id', 'is_recommend', 'platform']);
        $expertId = $params['expert_id'];
        $recommend = $params['is_recommend'];
        $platform = $params['platform'];
        //$update['is_recommend'] = $recommend;
        $expertModel = new ExpertModel();
        //愿推荐位置置为不推荐
        $oldExpertId = $expertModel->getRecommendExpertId($recommend, $platform);
        if ($oldExpertId != "") {
          if($platform == 1) {
            $oldUpdate['is_recommend'] = 0;
          }else {
            $oldUpdate['is_wx_recommend'] = 0;
          }
            $expertModel->updateExpert($oldExpertId, $oldUpdate);
        }
        //推荐新专家
        $update = array();
        if($platform == 1) {
            $update['is_recommend'] = $recommend;
          }else {
            $update['is_wx_recommend'] = $recommend;
          }
        $expertModel->updateExpert($expertId, $update);
        $this->responseJson();
    }

    /**
     * 置顶修改
     */
    public function placementUpdate() {
        $params = $this->checkApiParam(['expert_id', 'is_placement', 'platform']);
        $expertId = $params['expert_id'];
        $placement = $params['is_placement'];
        $platform = $params['platform'];
        //$update['is_placement'] = $placement;
        $expertModel = new ExpertModel();
        //取消原置顶位置
        $oldExpertId = $expertModel->getPlacementExpertId($placement, $platform);
        if ($oldExpertId != "") {
           if($platform == 1) {
            $oldUpdate['is_placement'] = 0;
          }else {
            $oldUpdate['is_wx_placement'] = 0;
          }
            $expertModel->updateExpert($oldExpertId, $oldUpdate);
        }
        //置顶新专家
        $update = array();
        if($platform == 1) {
          $update['is_placement'] = $placement;
        }else {
          $update['is_wx_placement'] = $placement;
        }
        $expertModel->updateExpert($expertId, $update);
        $this->responseJson();
    }

    /**
     * 状态修改
     */
    public function statusUpdate() {
        $params = $this->checkApiParam(['expert_id', 'expert_status']);
        $expertId = $params['expert_id'];
        $status = $params['expert_status'];
        $update['expert_status'] = $status;
        $update['check_time'] = time();
        $expertModel = new ExpertModel();
        $expertModel->updateExpert($expertId, $update);
        $notice =false;
        if($status==1){
            //获取专家费率
            $expertRateModel = new ExpertRateModel();
            $rate = $expertRateModel->getExpertRate($params['expert_id']);
            if(empty($rate)){
                $this->responseJsonError(5000,'请先修改专家分成费率');
            }
        }
        if($status==1){
            $notice = true;
            // 模版内容
            $messageData = array();
            //审核结果
            $messageData['keyword2'] = [
                'value' => '通过', 'color' => '#008fff'
            ];
            //备注
            $messageData['remark'] = [
                'value' => "您的专家申请已通过审核，点击详情开始发布内容吧~"
            ];
        }elseif ($status==4){
            $notice = true;
            // 模版内容
            $messageData = array();
            //审核结果
            $messageData['keyword2'] = [
                'value' => '未通过', 'color' => '#008fff'
            ];
            //备注
            $messageData['remark'] = [
                'value' => "您的专家申请未通过审核，请您点击详情，重新填写资料。"
            ];
        }
        if($notice){
            //审核通知
            $weChatId = $GLOBALS['weChatId'] = $this->_appSetting->getConstantSetting('DEFAULT_WECHATID');
            $weChatParams = new WeChatParams();
            $accessToken = $this->weChatToken();
            $weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
            $appId = $weChatConfig['id'];
            $appKey = $weChatConfig['appKey'];
            $weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);
            $expertInfo = $expertModel->getExpertInfo($expertId);

            //专家昵称
            $messageData['keyword1'] = [
                'value' => $expertInfo['expert_name'], 'color' => '#ff0000'
            ];
            //审核时间
            $messageData['keyword3'] = [
                'value' => date("m", time()) . "月" . date("d", time()) . "日 " . date("H:i", time()),
            ];
            $templateId = "qO6I_lVzXJ7vmXpwo1ORaU-WXdGYKJeJmMrTr4uZQAo";
            // 获取专家微信信息
            $userModel = new UserModel();
            $userWeChatInfo = $userModel->getUserWeChatInfo($expertId);
            $userOpenId = $userWeChatInfo['openid'];
            $url = $this->_appSetting->getConstantSetting("DOMAIN_EXPERT");
            if(!empty($userOpenId)){
                $weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData,$url);
            }
        }
        $this->responseJson();
    }

    /**
     * 专家排序
     * sort 1升，2降
     */
    public function setSort() {
        $params = $this->checkApiParam(['expert_id', 'sort_type']);
        $expertId = $params['expert_id'];
        $sort = $params['sort_type'];
        $expertModel = new ExpertModel();
        $code = $expertModel->expertSort($expertId, $sort);
        $errorCodeConfig = new ErrorCodeConfig();
        $msg = $errorCodeConfig->getErrorMessageByCode($code);
        $this->responseJson([], $msg);
    }

    /**
     * 专家信息
     */
    public function info() {
        $params = $this->checkApiParam(['expert_id']);
        $expertId = $params['expert_id'];
        $expertModel = new ExpertModel();
        $info = $expertModel->getExpertInfo($expertId);
        //获取专家30日订阅价格
        $expertSubscribeModel = new ExpertSubscribeModel();
        $expertSubscribe = $expertSubscribeModel->getExpertSubscribeByDays($expertId, 30);

        //提现信息
        $expertPresentAccountModel = new ExpertPresentAccountModel();
        $presentAccountList = $expertPresentAccountModel->getExpertPresentAccountList($expertId);

        $expertSubAccountModel = new ExpertSubaccountModel();
        $expertSubAccountList = $expertSubAccountModel->getSubaccountList($expertId, 1, 10);
        $info['subscribe_price'] = $expertSubscribe['subscribe_price'];
        $info['present_account'] = $presentAccountList;

        //子账户列表
        $subaccountList = [];
        $userModel = new UserModel();
        if (!empty($expertSubAccountList)) {
            foreach ($expertSubAccountList as $key => $val) {
                $subaccountList[$key] = $userModel->getUserInfo($val['user_id']);
            }
        }
        $info['subaccount'] = $subaccountList;

        $expertRateModel = new ExpertRateModel();
        $rate = $expertRateModel->getExpertRate($params['expert_id']);
        $info['rate'] = $rate['rate'];
        $info['effect_time'] = $rate['effect_time'];
        $this->responseJson($info);
    }

    /**
     * 编辑专家信息
     */
    public function editExpert() {
        $params = $this->checkApiParam(['real_name', 'expert_name', 'idcard_number', 'bank', 'bank_number', 'alipay_number', 'phone', 'headimgurl', 'subscribe_price', 'rate', 'effect_time','expert_type'], ['expert_id' => "", 'desc' => "", 'identity_desc' => '', 'tag' => null, 'platform' => 0]);

        $white_idCard = ['110011199901010000'];
	      $tag = !empty($params['tag']) ? str_replace('，', ',', $params['tag']) : '';
        if (CommonHandler::newInstance()->checkIdCard($params['idcard_number']) 
	        || (in_array($params['idcard_number'], $white_idCard) && $params['real_name'] == '真实专家')) {
            //新建专家
            if ($params['expert_id'] == "") {
                //新建
                $data['nickname'] = $params['expert_name'];
                $data['avatarurl'] = $params['headimgurl'];
                $data['phone'] = $params['phone'];
                $userModel = new UserModel();
                $expertModel = new ExpertModel();
                //已经手机号注册过的用户 绑定专家
                $user= $userModel->getUserInfoByPhone($params['phone']);
                if($user){
                    $uid = $params['expert_id'] = $user['user_id'];
                }else{
                    $uid = $params['expert_id'] = $userModel->consoleNewUser($data);
                }
                $expertInfo=$expertModel->getExpertInfo($uid);
                if($expertInfo['expert_id']){
                    $this->responseJsonError(103,'该手机已关联专家：'.$expertInfo['expert_name']);
                }


                $expertParams = [];
                $expertParams['expert_id'] = $uid;
                $expertParams['user_id'] = $uid;
                $expertParams['phone'] = $params['phone'];
                $expertParams['expert_name'] = $params['expert_name'];
                $expertParams['real_name'] = $params['real_name'];
                $expertParams['idcard_number'] = $params['idcard_number'];
                $expertParams['headimgurl'] = $params['headimgurl'];
                $expertParams['alipay_number'] = $params['alipay_number'];
                $expertParams['bank_number'] = $params['bank_number'];
                $expertParams['bank'] = $params['bank'];
                $expertParams['desc'] = $params['desc'];
                $expertParams['platform'] = $params['platform'];
                $expertParams['identity_desc'] = $params['identity_desc'];
                $expertParams['tag'] = $tag;
                $expertParams['expert_type'] = $params['expert_type'];
                $expertModel->newExpert($expertParams);
            } else {
                //更新
                $userModel = new UserModel();
                $expertModel = new ExpertModel();
                $updateExpertParams['real_name'] = $params['real_name'];
                $updateExpertParams['expert_name'] = $params['expert_name'];
                $updateExpertParams['phone'] = $params['phone'];
                $updateExpertParams['real_name'] = $params['real_name'];
                $updateExpertParams['headimgurl'] = $params['headimgurl'];
                $updateExpertParams['idcard_number'] = $params['idcard_number'];
                $updateExpertParams['desc'] = $params['desc'];
                $updateExpertParams['platform'] = $params['platform'];
                $updateExpertParams['identity_desc'] = $params['identity_desc'];
                $updateExpertParams['expert_type'] = $params['expert_type'];
                if($params['tag']!==null) {
                  $updateExpertParams['tag'] = $tag;
                }
                $expertModel->updateExpert($params['expert_id'], $updateExpertParams);



                //银行卡，支付宝号
                $presentAccountModel = new ExpertPresentAccountModel();
                $bankUpdate['bank'] = $params['bank'];
                $bankUpdate['account'] = $params['bank_number'];
                $aliPayUpdate['account'] = $params['alipay_number'];
                $presentAccountModel->updateExpertPresentAccount($params['expert_id'], 1, $aliPayUpdate);
                $presentAccountModel->updateExpertPresentAccount($params['expert_id'], 2, $bankUpdate);
            }

            //订阅价格
            /*$expertSubscribeModel = new ExpertSubscribeModel();
            $subscribeParams['expert_id'] = $params['expert_id'];
            $subscribeParams['subscribe_price'] = intval($params['subscribe_price'] * 100);
            $subscribeParams['length_day'] = 30;
            $subscribeParams['create_time'] = time();
            $expertSubscribeModel->updateSubscribe($subscribeParams);*/
            //提现费率
            $expertRateModel = new ExpertRateModel();
            $rateParams['expert_id'] = $params['expert_id'];
            $rateParams['rate'] = $params['rate'];
            $rateParams['effect_time'] = $params['effect_time'];
            $rateParams['create_time'] = time();
            $expertRateModel->insertNewRate($rateParams);

            $redisManage = new RedisKeyManageModel('expert');
            $redisManage->delExpertKey($params['expert_id']);
            $this->responseJson();
        } else {
            $this->responseJsonError(102);
        }
    }

    public function updateStatInfo() {
        $params = $this->checkApiParam(['expert_id']);
        $expertId = $params['expert_id'];

        $expertModel = new ExpertModel();

        //$experts = [1 ,2 ,4 ,6 ,7 ,8 ,9 ,10 ,11 ,14 ,15 ,18 ,19 ,20 ,21 ,22 ,23 ,24 ,25 ,26 ,27 ,28 ,29 ,30 ,31 ,32 ,33 ,34 ,35 ,36 ,37 ,38 ,40 ,41 ,42 ,45 ,46 ,47 ,48 ,50 ,51 ,52 ,54 ,62 ,99 ,100 ,104 ,106 ,108 ,143 ,144 ,145 ,146 ,147 ,148 ,152 ,153 ,155 ,158 ,159 ,160 ,161 ,162 ,163 ,165 ,166 ,167 ,174 ,194 ,198 ,199 ,200 ,201 ,202 ,313 ,411 ,1354 ,1357 ,2388 ,2491 ,2523 ,2876 ,2877 ,2998 ,2999 ,3002 ,3498 ,4668 ,4669 ,5853 ,5881 ,5911 ,7588 ,7589 ,9601 ,18960 ,18961 ,21294 ,21296 ,21297 ,21298 ,21300 ,21303 ,22525 ,22537 ,23551 ,24979 ,24985 ,24990 ,24994 ,25026 ,25027 ,25028 ,25029 ,25030 ,25107 ,25178 ,25179 ,25190 ,25203 ,25590 ,25593 ,25594 ,25598 ,25619 ,25648 ,25652 ,25653 ,25758 ,25759 ,25916 ,25949 ,25959];
        //if ($expertId == 'all'){
        //      foreach ($experts as $k => $item) {
        //              if ($k < 151 && $k > 120){
        //              //if ($k < 121 && $k > 80){
        //              //if ($k < 81 && $k > 40){
        //              //if ($k < 41 && $k > 10){
        //              //if (11 > $k){
        //              $r = $expertModel->updateStatInfo($item);
        //              var_dump($item . '-done');
        //              }
        //      }
        //die;
        //}

        // 统计全部专家命中率
        $r = $expertModel->updateStatInfo($expertId);


        if ($r) {
            $this->responseJson([], '操作成功');
        } else {
            $this->responseJsonError(-1, '参数错误');
        }

        // 统计全部专家命中率
        $r = $expertModel->updateStatInfo($expertId);

        if ($r) {
            $this->responseJson([], '操作成功');
        } else {
            $this->responseJsonError(-1, '参数错误');
        }
    }

	
	 //修改专家类型
    public function updateExpertType(){
        $params = $this->checkApiParam(['expert_id','expert_type']);
        $expertId = $params['expert_id'];
        $expert_type= $params['expert_type'];
        $expertModel = new ExpertModel();
        $update['expert_type'] = $expert_type;

        $r=$expertModel->updateExpert($expertId, $update);
       $this->responseJson([], '操作成功');
    }


  /*
  * 修改自动回复内容
  */
    public function replyContent(){
        $params = $this->checkApiParam(['expert_id', 'content']);
        $expertId=$params['expert_id'];
        $expertModel = new ExpertModel();

        $update['reply_content'] = $params['content'];
        $r=$expertModel->updateExpert($expertId, $update);
        if ($r) {
            $this->responseJson([], '操作成功');
        } else {
            $this->responseJsonError(-1, '参数错误');
        }
    }
}
