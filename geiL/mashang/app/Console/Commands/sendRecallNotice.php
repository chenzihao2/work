<?php
/**
 * 协助卖家召回粉丝
 * User: YangChao
 * Date: 2019/1/4
 */
namespace App\Console\Commands;


use App\models\client_subscribe;
use App\models\follow;
use App\models\client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class sendRecallNotice extends Command {

    protected $signature = 'sendRecallNotice';

    protected $description = 'sendRecallNotice';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
         //$this->sendMsg(1044);
        //粉丝列表
        $followList = follow::where('star', 307917)->where('status',1)->orderBy('id', 'desc')->get();
	//var_dump(count($followList));
        foreach ($followList AS $key=>$val){
            $fans_uid = $val['fans'];
            $this->sendMsg($fans_uid);
            //sleep(1);
        }
    }

    public function sendMsg($fans_uid) {
        //换取openid
        $openid = null;
         $subscribeInfo = client_subscribe::select('openid')->where('user_id', $fans_uid)->where('status', 1)->first();
         if(!empty($subscribeInfo)){
             $openid = $subscribeInfo['openid'];
        }
        //换取openid
        //$userInfo = client::select('openid', 'serviceid')->where('id', $fans_uid)->first();
        //$openid = $userInfo['serviceid'];

        if(!$openid){
            return null;
        }
        $token = $this->msg_access_token(2);

        //公众号
        //$noticeTemplateId = "GwestDBz8wpAgVD8PnHMgZvlKiLOBD6N4nQ2pPvDJLw";
        $noticeTemplateId = "FVh-d4Jz_wQJCR_lnpQgKZGuF-27ge1EE72f1_Uj8yo";
        $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

        $params['touser'] = $openid;
        $params['template_id'] = $noticeTemplateId;
        //支付信息
        $msg = array();
        $msg['first'] = [
            'value' => '由于腾讯风控，原始微信号被封，请添加新微信号',
            'color' => '#ff0000'
        ];
        $msg['keyword1'] = [
            'value' => '【tiegu8898】',
        ];
        $msg['keyword2'] = [
            'value' => '【hyui6567r】',
        ];
        $msg['keyword3'] = [
            'value' => date("Y-m-d H:i"),
        ];
        $msg['keyword4'] = [
            'value' => "因卖家原微信问题，请加卖家新微信获取料。",
            'color' => '#008fff'
        ];

        $params['data'] = $msg;

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');
	var_dump($result);
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
            $key = 'gzh_access_token_subscribe';

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

                $key = 'gzh_access_token_subscribe';
            }
            $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $outopt = file_get_contents($action_url);
            $data = json_decode($outopt, True);
            Redis::setex($key, 7000, $data['access_token']);
            return $data['access_token'];
        }
    }


}
