<?php
/**
 * User: YangChao
 * Date: 2018/9/14
 */

namespace App\Console\Commands;


use App\models\buyer;
use App\models\client;
use App\models\client_subscribe;
use App\models\follow;
use App\models\source;
use App\models\source_sensitives;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\models\ksyun_sms;

class sendnotice extends Command {

    protected $signature = 'sendnotice';

    protected $description = 'sendnotice';

    private $target_url = '?sid=%s&uid=%s&is_notice=1';
    private $host = [382115, 4416, 1011];
    private $exception_type = [
        1 => '异常ip登录',
        2 => '异常投诉数量',
        3 => '异常链接访问', 
        4 => '购买链接异常访问', 
    ];
    private $exception_info = [
        1 => '异常ip登录，异常用户id为%s',
        2 => '异常投诉数量，请前往后台查看',
        3 => "异常链接访问\n卖家id为 %s\n料id为 %s \n料售出数量为 %s \n料累计访问次数为 %s \n料累计访问人次为 %s \n料访问新用户人数为 %s",
        4 => "购买链接异常访问\n卖家id为 %s\n料id为 %s\n访问的买家id为 %s \n引导者id为 %s \n累计访问人数为 %s",
    ];

    public function __construct() {
        parent::__construct();
        $this->target_url = config('constants.tbd_domain1') . $this->target_url;
    }

    public function handle() {
        $redisKey = 'source_notice_list';
        while ($sid = Redis::rpop($redisKey)) {
            //获取料详情
            $sourceInfo = source::select('source.sid', 'uid', 'nickname', 'title', 'source.createtime', 'is_notice')->LeftJoin('client', 'source.uid', 'client.id')->where('source.sid', $sid)->first();
            $uid = $sourceInfo['uid'];
            //已通知过
            if($sourceInfo['is_notice'] == 2){
                continue;
            }
            //粉丝列表
            $followList = follow::where('star', $uid)->where('status',1)->get();
            foreach ($followList AS $key=>$val){
                $fans_uid = $val['fans'];
                //检测用户是否在黑名单
                $buyerStatus = buyer::checkBuyerStatus($uid, $fans_uid);
                if(!$buyerStatus){
                    continue;
                }
                $this->sendMsg($sourceInfo, $fans_uid);
            }
            //修改为已通知
            source::where('sid', $sid)->update(['is_notice'=>2]);
        }
    }

    public function sendMsg($sourceInfo, $fans_uid) {
        //换取openid
        $openid = null;
        $subscribeInfo = client_subscribe::select('openid')->where('user_id', $fans_uid)->where('status', 1)->first();
        if(!empty($subscribeInfo)){
            $openid = $subscribeInfo['openid'];
        }
        if(!$openid){
            return null;
        }

        $apply_source_info = source_sensitives::apply($sourceInfo);
        $sourceInfo['title'] = $apply_source_info['title'];

        $token = $this->msg_access_token(2);
        $sourceInfo = source_sensitives::apply($sourceInfo);

        //公众号
        $noticeTemplateId = "ID0_4xcxuzFzE7JB4wTCsnyrL90_UjjtlhpV_Ipn_5o";
        $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

        $params['touser'] = $openid;
        $params['template_id'] = $noticeTemplateId;
        $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
        $url = '';
        //if ($sourceInfo['uid'] == 1011) {
            $url = sprintf($this->target_url, $sourceInfo['sid'],$sourceInfo['uid']);
            $params['url'] = $url;
        //}
        //支付信息
        $msg = array();
        $msg['first'] = [
            'value' => '作者：【' . $sourceInfo['nickname'] . '】',
            'color' => '#008fff'
        ];
        //信息详情
        $msg['keyword1'] = [
            'value' => $sourceInfo['title'],
            'color' => '#ff0000'
        ];
        //信息详情
        $msg['keyword2'] = [
            'value' => $sourceInfo['createtime'],
        ];
        //发布时间
        $msg['remark'] = [
            'value' => "若您不想收到作者通知，点击进入支付页面，取关作者即可。"
        ];

        $params['data'] = $msg;

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');
            return ($result);
        } else {
            return null;
        }

    }

    public function warning($type = 1, $variable = '') {
        $r_key = 'warning_' . $type;
        $time_out = Redis::get($r_key);
        if ($time_out) {
            return ;
        }
        if (is_array($variable)) {
            if ($type == 3) {
                $exception_info = sprintf($this->exception_info[$type], $variable[0], $variable[1], $variable[2], $variable[3], $variable[4], $variable[5]);
            } else {
                $exception_info = sprintf($this->exception_info[$type], $variable[0], $variable[1], $variable[2], $variable[3], $variable[4]);
            }
        } else {
            $exception_info = sprintf($this->exception_info[$type], $variable);
        }
        foreach ($this->host  as $host_id) {
            $openid = '';
            $subscribeInfo = client_subscribe::select('openid')->where('user_id', $host_id)->where('status', 1)->first();
            if(!empty($subscribeInfo)){
                $openid = $subscribeInfo['openid'];
            }
            if(!$openid){
                continue;
            }
            $token = $this->msg_access_token(2);
            $noticeTemplateId = "-SDjaezrFEgefi8gXAYSlwgvN8-rslVLjJiPXvzHZ3Y";
            $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
            $params['url'] = '#';
            $msg = array();
            $msg['first'] = [
                'value' => '【异常通知】',
                'color' => '#008fff'
            ];
            $msg['keyword1'] = [
                'value' => '异常',
                'color' => '#ff0000'
            ];
            $msg['keyword2'] = [
                'value' => $this->exception_type[$type],
            ];
            $msg['keyword4'] = [
                'value' => date('Y-m-d H:i:s'),
            ];
            $msg['keyword5'] = [
                'value' => $exception_info,
            ];
            $params['data'] = $msg;
            $this->postCurl($api, $params, 'json');
        }
        $sms_obj = new ksyun_sms();
        $sms_obj->warning_send();
        $expire_time = 300;
        if ($type == 4) {
            $expire_time = 60;
        }
        Redis::setex($r_key, $expire_time, 1);
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

    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            $key = 'send_access_token';

        }
        $re = Redis::exists($key);
        if ($re) {
            return Redis::get($key);
        } else {
            //小程序
            if ($type == 1) {
                $appid = 'wx1ad97741a12767f9';
                $appsecret = '001b7d3059af1a707a5d4e432aa45b7a';
            } else {
                //公众号
                $appid = config("wxxcx.wechat_subscribe_appid");
                $appsecret = config("wxxcx.wechat_subscribe_appsecret");

                //$key = 'gzh_access_token_subscribe';
            }
            $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $outopt = file_get_contents($action_url);
            $data = json_decode($outopt, True);
            Redis::setex($key, 7000, $data['access_token']);
            return $data['access_token'];
        }
    }


    /**
     * 三方支付
     * CURL 三方支付请求接口
     * @param $url
     * @param $param
     * @return mixed
     */
    public function payHttpPost($url, $param)
    {
        $param['timestamp'] =   time()*1000;  //统一给参数添加时间戳参数
        $reqUrl = config('pay.hypay.reqUrl');
        $param['sign'] = $this->createSign($param); //生成签名参数
        $ch = curl_init();//启动一个CURL会话
        curl_setopt($ch, CURLOPT_URL, $reqUrl.$url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); //设置请求超时时间
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); //设置请求方式为POST请求
        curl_setopt($ch, CURLOPT_POST, 1); //发送一个常规的POST请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param)); //将params 转成 a=1&b=2&c=3的形式
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //curl获取页面内容, 不直接输出
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $data = curl_exec($ch); // 已经获取到内容，没有输出到页面上。
        curl_close($ch);
        return $data;
    }


    /**
     * 三方支付
     * 获取sign签名方法
     * @return string
     */
    public function createSign($parms)
    {
        $signPars = "";
        ksort($parms);
        foreach ($parms as $k => $v) {
            if ("" != $v && "sign" != $k) {
                $signPars .= $k . "=" . $v;
            }
        }
        $secret = config('pay.hypay.secret');
        $sign = md5($signPars . $secret); //sign签名生成
        return $sign;
    }

}
