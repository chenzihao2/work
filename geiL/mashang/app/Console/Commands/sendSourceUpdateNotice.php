<?php
/**
 * 包时段和更新料服务通知
 * User: YangChao
 * Date: 2018/12/21
 */

namespace App\Console\Commands;

use App\models\buyer;
use App\models\client;
use App\models\client_subscribe;
use App\models\follow;
use App\models\order;
use App\models\source;
use App\models\source_sensitives;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class sendSourceUpdateNotice extends Command {

    protected $signature = 'sendSourceUpdateNotice';

    protected $description = 'sendSourceUpdateNotice';

    private $target_url = '?sid=%s&uid=%s&is_notice=1';

    public function __construct() {
        parent::__construct();
        $this->target_url = config('constants.tbd_domain1') . $this->target_url;
    }

    public function handle() {
        //包时段更新通知序列
        $redisKey = 'pack_source_update_notice_list';
        while ($sid = Redis::rpop($redisKey)) {
            //获取料详情
            $sourceInfo = source::select('sid', 'uid', 'title', 'createtime', 'pack_day', 'delayed_day')->where('sid', $sid)->first();
            // $orderList = order::select('sid',  'buyerid')->where('sid', $sid)->where('orderstatus', 1)->get()->ToArray();
            $limitTime = date('Y-m-d H:i:s', time() - (($sourceInfo['pack_day'] + $sourceInfo['delayed_day']) * 86400));
            $orderList = order::select('sid',  'buyerid')->where([['sid', '=', $sid], ['createtime', '>=', $limitTime]])->where('orderstatus', 1)->get()->ToArray();

            foreach ($orderList AS $key=>$val){
                $userId = $val['buyerid'];
                $this->sendMsg($sourceInfo, $userId, 1);
            }
        }

        //料更新通知序列
        $redisKey = 'source_update_notice_list';
        while ($sid = Redis::rpop($redisKey)) {
            $orderList = order::select('sid',  'buyerid')->where('sid', $sid)->where('orderstatus', 1)->get()->ToArray();
            //获取料详情
            $sourceInfo = source::select('sid', 'title', 'createtime')->where('sid', $sid)->first();
            foreach ($orderList AS $key=>$val){
                $userId = $val['buyerid'];
                $this->sendMsg($sourceInfo, $userId, 2);
            }
        }

        //包时段延期提醒
        $redisKey = "pack_source_delayed_notice_list";
        while ($sid = Redis::rpop($redisKey)) {
            //获取料详情
            $sourceInfo = source::select('sid', 'title', 'uid', 'createtime', 'pack_day', 'delayed_day')->where('sid', $sid)->first();
            // $orderList = order::select('sid',  'buyerid')->where('sid', $sid)->where('orderstatus', 1)->get()->ToArray();
            $limitTime = date('Y-m-d H:i:s', time() - (($sourceInfo['pack_day'] + $sourceInfo['delayed_day']) * 86400));
            $orderList = order::select('sid',  'buyerid')->where([['sid', '=', $sid], ['createtime', '>=', $limitTime]])->where('orderstatus', 1)->get()->ToArray();

            //卖家提醒
            $this->sendMsg($sourceInfo, $sourceInfo['uid'], 4);

            foreach ($orderList AS $key=>$val){
                $userId = $val['buyerid'];
                //买家提醒
                $this->sendMsg($sourceInfo, $userId, 3);
            }
        }

        //赛后3小时免费看单提醒
        $play_time_start = time() - 3600 * 3 - 60;
        $play_time_end = time() - 3600 * 3;
        $sourceList = source::select()->where('free_watch', 1)->where('play_time', '>=', $play_time_end)->where('play_time', '<=', $play_time_start)->where('status', 1)->get();
        foreach($sourceList as $key => $val){
            //粉丝列表
            $followList = follow::where('star', $val['uid'])->where('status',1)->get();
            foreach ($followList AS $ke=>$va){
                $fans_uid = $va['fans'];
                $this->sendMsg($val, $fans_uid, 5);
            }
        }

    }

    public function sendMsg($sourceInfo, $userId, $type = 1) {
        //检测用户是否在黑名单
        $buyerStatus = buyer::checkBuyerStatus($sourceInfo['uid'], $userId);
        if(!$buyerStatus){
            return null;
        }

        $apply_sourece_info = source_sensitives::apply($sourceInfo);
        $sourceInfo['title'] = $apply_sourece_info['title'];

        switch($type){
            case 1:
                //换取openid
                $openid = null;
                $subscribeInfo = client_subscribe::select('openid')->where('user_id', $userId)->where('status', 1)->first();
                if(!empty($subscribeInfo)){
                    $openid = $subscribeInfo['openid'];
                }
                if(!$openid){
                    return null;
                }
                $token = $this->msg_access_token(2);

                //包时段料更新通知
                $noticeTemplateId = "Vunrao8nVjWqdnotleBksNtOMGcEoCdfAK8wD4gsqFw";
                $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

                $params['touser'] = $openid;
                $params['template_id'] = $noticeTemplateId;
                $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
                $url = sprintf($this->target_url, $sourceInfo['sid'],$sourceInfo['uid']);
                $params['url'] = $url;
                //消息模版信息
                $msg = array();
                $msg['first'] = [
                    'value' => '您好，您购买的料已更新！'
                ];

                $msg['keyword1'] = [
                    'value' => '包时段料更新'
                ];

                $msg['keyword2'] = [
                    'value' => $sourceInfo['title'],
                ];
                $msg['keyword3'] = [
                    'value' => '已更新',
                ];
                //发布时间
                $msg['remark'] = [
                    'value' => "若消息通知打扰到您，您可取消关注。"
                ];

                $params['data'] = $msg;

                break;
            case 2:

                //换取openid
                $openid = null;
                $subscribeInfo = client_subscribe::select('openid')->where('user_id', $userId)->where('status', 1)->first();
                if(!empty($subscribeInfo)){
                    $openid = $subscribeInfo['openid'];
                }
                if(!$openid){
                    return null;
                }
                $token = $this->msg_access_token(2);

                //料内容修订更新通知
                $noticeTemplateId = "Vunrao8nVjWqdnotleBksNtOMGcEoCdfAK8wD4gsqFw";
                $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

                $params['touser'] = $openid;
                $params['template_id'] = $noticeTemplateId;
                $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
                $url = sprintf($this->target_url, $sourceInfo['sid'],$sourceInfo['uid']);
                $params['url'] = $url;
                //消息模版信息
                $msg = array();
                $msg['first'] = [
                    'value' => '您好，您购买的料已更新！'
                ];
                $msg['keyword1'] = [
                    'value' => '料内容修订'
                ];

                $msg['keyword2'] = [
                    'value' => $sourceInfo['title'],
                ];
                $msg['keyword3'] = [
                    'value' => '已更新',
                ];
                //发布时间
                $msg['remark'] = [
                    'value' => "若消息通知打扰到您，您可取消关注。"
                ];

                $params['data'] = $msg;

                break;
            case 3:
                //换取openid
                $userInfo = client::select('openid', 'serviceid', 'nickname')->where('id', $userId)->first();
                $openid = $userInfo['serviceid'];

                if(!$openid){
                    return null;
                }
                $token = $this->msg_access_token(2);

                //包时段延期提醒
                $noticeTemplateId = "u96pM3GxZ1TKse_6h_C9HgKrkBrUHk08PLrytYPKNa4";
                $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

                $params['touser'] = $openid;
                $params['template_id'] = $noticeTemplateId;
                $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
                $url = sprintf($this->target_url, $sourceInfo['sid'],$sourceInfo['uid']);
                $params['url'] = $url;
                //消息模版信息
                $msg = array();
                $msg['first'] = [
                    'value' => '您购买的包时段料已经延期' . $sourceInfo['delayed_day'] . '天，您的包时段查看时限将延期' . $sourceInfo['delayed_day'] . '天到期。'
                ];
                $msg['keyword1'] = [
                    'value' => $sourceInfo['title']
                ];
                $msg['keyword2'] = [
                    'value' => $sourceInfo['delayed_day'] . '天',
                ];
                $msg['keyword3'] = [
                    'value' => '卖家延期',
                ];
                //发布时间
                $msg['remark'] = [
                    'value' => "查看详情"
                ];
                $params['data'] = $msg;

                break;
            case 4:
                //换取openid
                $userInfo = client::select('openid', 'serviceid', 'nickname')->where('id', $userId)->first();
                $openid = $userInfo['serviceid'];

                if(!$openid){
                    return null;
                }
                $token = $this->msg_access_token(2);

                //包时段延期提醒
                $noticeTemplateId = "u96pM3GxZ1TKse_6h_C9HgKrkBrUHk08PLrytYPKNa4";
                $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

                $params['touser'] = $openid;
                $params['template_id'] = $noticeTemplateId;
                $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
                $url = sprintf($this->target_url, $sourceInfo['sid'],$sourceInfo['uid']);
                $params['url'] = $url;
                //消息模版信息
                $msg = array();
                $msg['first'] = [
                    'value' => '您已经选择延期' . $sourceInfo['delayed_day'] . '天，购买该料的粉丝时段都将延期' . $sourceInfo['delayed_day'] . '天到期。'
                ];
                $msg['keyword1'] = [
                    'value' => $sourceInfo['title']
                ];
                $msg['keyword2'] = [
                    'value' => $sourceInfo['delayed_day'] . '天',
                ];
                $msg['keyword3'] = [
                    'value' => '卖家延期',
                ];
                //发布时间
                $msg['remark'] = [
                    'value' => "查看详情"
                ];
                $params['data'] = $msg;

                break;
            case 5:
                //换取openid
                $userInfo = client::select('openid', 'serviceid', 'nickname')->where('id', $userId)->first();
                $openid = $userInfo['serviceid'];

                if(!$openid){
                    return null;
                }
                $token = $this->msg_access_token(2);
                //赛后3小时免费看单提醒

                $userInfo = client::select('nickname')->where('id', $sourceInfo['uid'])->first();

                $noticeTemplateId = "9vmML7-CCZbB9r1ZJU1dZ8P73jqgLg6KRGcxRDmbuM4";
                $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

                $params['touser'] = $openid;
                $params['template_id'] = $noticeTemplateId;
                $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
                $url = sprintf($this->target_url, $sourceInfo['sid'],$sourceInfo['uid']);
                $params['url'] = $url;
                //消息模版信息
                $msg = array();
                $msg['first'] = [
                    'value' => '您关注的卖家【' . $userInfo['nickname'] . '】发布的料已可免费看单，欢迎扫码免费查看。'
                ];
                $msg['keyword1'] = [
                    'value' => $sourceInfo['title']
                ];
                $msg['keyword2'] = [
                    'value' => $sourceInfo['delayed_day'] . '天',
                ];
                $msg['keyword3'] = [
                    'value' => $sourceInfo['createtime'],
                ];
                $msg['remark'] = [
                    'value' => "点击详情免费查看料内容"
                ];
                $params['data'] = $msg;

                break;
        }


        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');
            return ($result);
        } else {
            return null;
        }

    }

    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } elseif($type == 2) {
            $key = 'send_access_token';
        } else {
            $key = 'gzh_access_token_subscribe_3';
        }
        $re = Redis::exists($key);
        if ($re) {
            return Redis::get($key);
        } else {
            //小程序
            if ($type == 1) {
                $appid = 'wx1ad97741a12767f9';
                $appsecret = '001b7d3059af1a707a5d4e432aa45b7a';
            } elseif($type == 2) {
                //公众号
                $appid = config("wxxcx.wechat_subscribe_appid");
                $appsecret = config("wxxcx.wechat_subscribe_appsecret");

                //$key = 'gzh_access_token_subscribe';
            } elseif($type == 3) {
                //公众号
                $appid = config("wxxcx.wechat_appid");
                $appsecret = config("wxxcx.wechat_appsecret");

                //$key = 'gzh_access_token_subscribe_3';
            }
            $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $outopt = file_get_contents($action_url);
            $data = json_decode($outopt, True);
            Redis::setex($key, 7000, $data['access_token']);
            return $data['access_token'];
        }
    }

    public function postCurl($url, $data, $type) {
        if ($type == 'json') {
            $data = json_encode($data);//对数组进行json编码
            $header = array(
                "Content-type: application/json;charset=UTF-8",
                "Accept: application/json",
                "Cache-Control: no-cache",
                "Pragma: no-cache"
            );
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $res = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Error+' . curl_error($curl);
        }
        curl_close($curl);
        return $res;
    }


}
