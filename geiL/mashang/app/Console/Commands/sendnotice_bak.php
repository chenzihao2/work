<?php
/**
 * User: YangChao
 * Date: 2018/9/14
 */

namespace App\Console\Commands;


use App\models\client;
use App\models\follow;
use App\models\source;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class sendnotice extends Command {

    protected $signature = 'sendnotice';

    protected $description = 'sendnotice';

    public function __construct() {
        parent::__construct();

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
                $this->sendMsg($sourceInfo, $fans_uid);
                sleep(1);
            }
            //修改为已通知
            source::where('sid', $sid)->update(['is_notice'=>2]);
        }
    }

    public function getOpenId($uid) {
        $client = client::select('openid', 'serviceid')->where('id', $uid)->first();
        return $client;
    }

    public function sendMsg($sourceInfo, $fans_uid) {
        //换取openid
        $userInfo = $this->getOpenId($fans_uid);
        $token = $this->msg_access_token(2);

        //公众号
        // $noticeTemplateId = "7rPIjyK-B-Th5MrfGe6fIxN4Pd-o3NmPTHMXkca3HQs";
        $noticeTemplateId = "Wq6-EbGFmATC_TNeSxEXwV_Aks1IbTDY1-38mFH8hKY";
        $openid = $userInfo['serviceid'];
        $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

        $params['touser'] = $openid;
        $params['template_id'] = $noticeTemplateId;
        $params['url'] = "https://glm9.qiudashi.com/pay/payment?scene=s.".$sourceInfo['sid']."&uid=".$sourceInfo['uid'];
        //支付信息
        $msg = array();
        $msg['first'] = [
            'value' => '您好，您关注的作者发布了新的作品！作品名称：' . $sourceInfo['title'],
            'color' => '#576b95'
        ];//信息详情
        $msg['keyword1'] = [
            'value' => $sourceInfo['nickname'],
            'color' => '#ff1212'
        ];//信息详情
        $msg['keyword2'] = [
            'value' => $sourceInfo['createtime'],
        ];//发布时间
        $msg['remark'] = [
            'value' => "点击查看详细内容。"
        ];

        $params['data'] = $msg;

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');
            return ($result);
        } else {
            return null;
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

    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            $key = 'gzh_access_token';

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
                $appid = config("wxxcx.wechat_appid");
                $appsecret = config("wxxcx.wechat_appsecret");

                $key = 'gzh_access_token';
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