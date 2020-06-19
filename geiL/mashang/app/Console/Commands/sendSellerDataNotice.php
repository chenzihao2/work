<?php
/**
 * 卖家交易数据消息通知发送
 * User: YangChao
 * Date: 2019/1/8
 */
namespace App\Console\Commands;

use App\models\seller_data;
use App\models\client;
use App\models\client_subscribe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;


class sendSellerDataNotice extends Command {

    protected $signature = 'sendSellerDataNotice';

    protected $description = 'sendSellerDataNotice';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {

        $date = date('Y-m-d', strtotime('-1 day'));
        $sellerData = seller_data::select()->where('date', $date)->get();
        foreach($sellerData as $key => $val){
            $redisKey = 'seller_data_notice' . $val['selledid'];
            if($val['order_total'] == 0 && $val['refund_total'] == 0){
                if(Redis::exists($redisKey)){
                    continue;
                } else {
                    $this->sendMsg($val);
                    Redis::set($redisKey, $val['selledid']);
                }
            } else {
                $this->sendMsg($val);
            }
            //sleep(1);
        }
    }

    public function sendMsg($sellerData) {
        //换取openid
        $openid = null;
        $subscribeInfo = client_subscribe::select('openid')->where('user_id', $sellerData['selledid'])->where('status', 1)->first();
        if(!empty($subscribeInfo)){
             $openid = $subscribeInfo['openid'];
        }
        //换取openid
        $userInfo = client::select('openid', 'serviceid', 'nickname')->where('id', $sellerData['selledid'])->first();
        //$openid = $userInfo['serviceid'];
        $nickname = $userInfo['nickname'];

        if(!$openid){
            return null;
        }
        $token = $this->msg_access_token(2);

        //公众号
        //$noticeTemplateId = "LVgwgq4i7oEZwpnE1QmW2lYqfZpCHjU0I2GBn1u8hI4";
        $noticeTemplateId = "GcHCvUUFkJHc0C7kPr08FRdhe6mnU1Jerw7-o_F7kOQ";
        $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

        $params['touser'] = $openid;
        $params['template_id'] = $noticeTemplateId;
        $params['url'] = "https://glm9.qiudashi.com/home/my";
        //支付信息
        $msg = array();
        $msg['first'] = [
            'value' => '【' . $nickname . '】您好，给料给您奉上昨日的交易总额，祝您生意兴隆！'
        ];
        $msg['keyword1'] = [
            'value' => '截至' . date('Y-m-d 00:00:00'),
        ];
        $msg['keyword2'] = [
            'value' => $sellerData['order_total'] . '笔',
            'color' => '#ff0000'
        ];
        $msg['keyword3'] = [
            'value' => $sellerData['order_price_total'] . '元',
            'color' => '#ff0000'
        ];
        $msg['remark'] = [
            'value' => "其中，不对返还" . $sellerData['refund_total']. "笔，退款金额" . $sellerData['refund_price_total']. "元"
        ];

        $params['data'] = $msg;

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');var_dump($result);
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


}
