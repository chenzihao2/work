<?php
/**
 * 微信通知相关接口
 * User: zyj
 * Date: 2020/03/13
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\NoticeModel;
use QK\WSF\Settings\AppSetting;
use QK\HaoLiao\Model\RedisModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\DAL\DALUserSubscribe;
use QK\HaoLiao\Model\UserModel;
use QK\WeChat\WeChatToken;
use QK\HaoLiao\Common\WeChatParams;
use QK\WeChat\WeChatSendMessage;

class NoticeController extends ConsoleController{
    // 方案列表
    public function resourceList(){
        $param = $this->checkApiParam([], ['title' => '', 'page' => 1, 'pagesize' => 20]);
        $resourceModel= new ResourceModel();
        $condtion='';
        if($param['title']){
            $condtion=" title like '%".$param['title']."%' and resource_status=1 ";
        }

        $fields = '';
        //$order = ' resource_id desc ';
        $order = $order = ' release_time desc ';
        $list= $resourceModel->lists($condtion, $fields , $order,[], 0, $limit =50);
        $this->responseJson($list);
    }


    /*
     * 通知列表
     */

    public function noticeList(){
        $param = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 20]);
        $query = json_decode($param['query'], true);
        $condition = array();
        /* if (!empty($query['id'])) {
             $condition['id'] = $query['id'];
         }
         if (!empty($query['title'])) {
             $condition['title'] = ['like', '%'.trim($query['title']).'%'];
         }
         if (!empty($query['content'])) {
             $condition['content'] =  ['like', '%'.trim($query['content']).'%'];
         }
         if (!empty($query['start_time'])) {
             $condition['ctime'][] = ['>', $query['start_time']];
         }
         if (!empty($query['end_time'])) {
             $condition['ctime'][] = ['<=', $query['end_time']];
         }*/

        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);

        $noticeModel = new NoticeModel();
        $resourceModel=new ResourceModel();
        $expertModel=new ExpertModel();
        $orderBy = ['id' => 'DESC'];
        $data = $noticeModel->getNoticeList($condition, array(), $page, $pagesize, $orderBy);

        $list = $data['list'];
        foreach($list as &$v){
            $resourceInfo=$resourceModel->getResourceInfo($v['rid']);
            $expertInfo=$expertModel->getExpertInfo($v['expert_id']);

            $v['resource_title']=$resourceInfo['title'];
            $v['expert_name']=$expertInfo['expert_name'];
        }
        $data['list'] = $list;
        $this->responseJson($data);
    }
    /*
     * 创建通知
     * title 标题
     * content 内容
     * remarks 备注
     * rid 方案id
     */
    public function createNotice(){
        $params = $this->checkApiParam(['title','content','remarks','rid'], [ 'status' => 0,'user_id'=>0]);

        $noticeModel = new NoticeModel();
        $resourceModel=new ResourceModel();
        $resourceInfo=$resourceModel->getResourceInfo($params['rid']);

        $params['ctime'] = date('Y-m-d H:i:s');
        $params['expert_id'] = $resourceInfo['expert_id'];
        $params['complete_time'] = date('Y-m-d H:i:s',$resourceInfo['release_time']);

        $id=0;
        if(!$params['user_id']){
            // $params['user_id']=26044;

            $id=$noticeModel->createNotice($params);
        }


        $this->sendData($id,$params['rid'],$params['content'],$params['title'],$params['complete_time'],$params['remarks'],$params['user_id']);



        $this->responseJson();
    }

    /*
     * 修改通知--暂时不用
     * title 标题
     * content 内容
     * remarks 备注
     * complete_time 完成时间
     * rid 方案id
     * id 通知id
     */
    public function updateNotice(){
        $params = $this->checkApiParam(['id'], [ 'title'=> '',  'content' => '', 'remarks' => '', 'status' => 0]);
        $noticeModel = new NoticeModel();
        $params['ctime'] = date('Y-m-d H:i:s');
        $res=$noticeModel->updateNotice($params['id'],$params);
        $this->responseJson();
    }

    /* 暂时不用
     * 单个未发送得通知发送
     *
     */
    public function sendNotice(){
        $params = $this->checkApiParam(['id'], ['user_id'=>0]);
        $noticeModel = new NoticeModel();
        $messageInfo=$noticeModel->findNotice($params['id']);
        $this->sendData($messageInfo['rid'],$messageInfo['content'],$messageInfo['title'],$messageInfo['complete_time'],$messageInfo['remarks'],$params['user_id']);
    }

    /*
     * 新发布通知
     * $id 消息id
     * $rid 方案id
     * $content 内容
     * $title 标题
     * $complete_time 完成时间
     * $remark 备注
     * $user_id 测试用户
     *
     */
    public function sendData($id=0,$rid,$content,$title,$complete_time,$remark,$user_id=0){

        if(!$rid || !$content || !$title || !$complete_time || !$remark){
            return false;
        }
        $_appSetting = AppSetting::newInstance(AppRoot);
        //TODO 默认走配置中的默认id
        $weChatId = $GLOBALS['weChatId'] = $_appSetting->getConstantSetting('DEFAULT_WECHATID');
        $noticeModel = new NoticeModel();
        $userModel = new UserModel();


        // 模版内容
        $messageData = array();
        $messageData['first'] = [
            'value' => $content,
            'color' => '#2f84f2'
        ];
        //料标题
        $messageData['keyword1'] = [
            'value' => $title,
        ];
        //时间
        $messageData['keyword2'] = [
            'value' => $complete_time,
        ];
        $messageData['remark'] = [
            'value' => $remark
        ];

        $templateId = "8yFI2l9z-xnNXWXsx3luZnDo-w4HNv3TuXRcJLgXGGo";//线上
        $url = "https://customer.haoliao188.com/#/content?rid={$rid}&share=1";


        $userList = $userModel->getWechatSubUsers();

        $this->sendMsg($weChatId, $templateId, $userList, $messageData, $url, $user_id,$id);
    }



    /**
     * 发送模版消息
     * @param $weChatId
     * @param $templateId
     * @param $userList
     * @param $messageData
     * @param $url
     */
    function sendMsg($weChatId, $templateId, $userList, $messageData, $url,$ceshi_user_id=0,$id=0) {

        $weChatParams = new WeChatParams();
        $accessToken = $this->weChatToken($weChatId);
        $weChatConfig = $weChatParams->getNewWeChatParams('', $weChatId);
        $appId = $weChatConfig['id'];
        $appKey = $weChatConfig['appKey'];
        $weChatSendMessage = new WeChatSendMessage($appId, $appKey, $accessToken);

        $count=0;
        foreach ($userList as $key => $val) {
            $userId = $val['user_id'];

            // 获取用户微信信息
            if ($ceshi_user_id && $userId == $ceshi_user_id) {

                $userOpenId = $val['openid'];
                //dump($userOpenId);
                $res=$weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData, $url);
                //$res=json_decode($res['msg'],true);
                //dump($res);
                break;
            }
            if(!$ceshi_user_id){
                $userOpenId = $val['openid'];
            }

            //未关注此公众号的用户不发送消息
            if(empty($userOpenId)) {
                continue;
            }
            $count++;

            //$res=$weChatSendMessage->sendMessage($userOpenId, $templateId, $messageData, $url);

        }


        //修改状态
        if($id){
            $noticeModel = new NoticeModel();
            $params['status'] =1;
            $params['count'] =$count;

            $noticeModel->updateNotice($id,$params);
        }


    }


    /**
     * 微信AccessToken获取
     * @param $weChatId
     * @return bool|mixed|null|string
     */
    function weChatToken($weChatId) {
        $accessTokenRedisKey = "Access_Token_" . $weChatId;
        $redisModel = new RedisModel('wechat');
        $accessToken = $redisModel->redisGet($accessTokenRedisKey);
        if (empty($accessToken)) {
            $weChatParam = new WeChatParams();
            $weChatParams = $weChatParam->getNewWeChatParams('', $weChatId);
            $appId = $weChatParams['id'];
            $appKey = $weChatParams['appKey'];
            $token = new WeChatToken($appId, $appKey);
            $tokenInfo = $token->getToken();
            if (array_key_exists('code', $tokenInfo)) {
                return false;
            } else {
                $redisModel->redisSet($accessTokenRedisKey, $tokenInfo['access_token'], 7150);
                $accessToken = $tokenInfo['access_token'];
            }
        }
        return $accessToken;
    }
}
