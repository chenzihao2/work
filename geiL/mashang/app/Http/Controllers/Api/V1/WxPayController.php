<?php

namespace App\Http\Controllers\Api\V1;

use App\extract;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use WxPayNotify;
use WxPayUnifiedOrder;

//use Overtrue\Wechat\Payment;
//use Overtrue\Wechat\Payment\Order;
//use Overtrue\Wechat\Payment\Business;
//use Overtrue\Wechat\Payment\UnifiedOrder;

class WxPayController extends Controller
{

    public function servers()
    {
        $data = [];

        // 订单号
        $order_number = $this->order_number();

        $input = new WxPayUnifiedOrder();
        $input->SetBody("test");    // 商品简单描述
        $input->SetOut_trade_no($order_number); //订单号
        $input->SetTotal_fee("1");  // 金额
        $input->SetNotify_url(config('constants.frontend_domain') . "/api/weixin/notify");  // 推送信息
        $input->SetTrade_type("JSAPI");
        $input->SetOpenid("oZdwA0bTUcgeiKeyIHM3oB0KMnDs");  // 用户openId
        $order = \WxPayApi::unifiedOrder($input);
        header("Content-Type: application/json");

        $data['timestamp'] = time();
        $data['appId'] = $order['appid'];
        $data['nonceStr'] = $order['nonce_str'];
        $data['package'] = "prepay_id=".$order['prepay_id'];
        $data['signType'] = "MD5";
        $keywords = 'appId='.$order['appid'].'&nonceStr='.$order['nonce_str'].'&package=prepay_id='.$order['prepay_id'].'&signType=MD5&timeStamp='.$data['timestamp'].'&key=SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3';
        $data['paySign'] = md5($keywords);
        return response()->json($data);
    }

    public function order_number()
    {
        return date('Ymd') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    }

    public function notify()
    {
        $xml = file_get_contents("php://input");
        $data = $this->xmlToArray($xml);
        \Log::INFO($data);

        // 交易成功
//        if ( !empty($return['result_code']) && $return['result_code'] == 'SUCCESS') {
//            extract::where("code", "2017092209333")->update(['status' => "1"]);
//        } else {
//            extract::where("code", "2017092209333")->update(['status' => "2"]);
//        }

    }


    /**
     * xml 转换为 array
     * @param xml
     * @return array
     */
    public function xmlToArray($xml)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        $val = json_decode(json_encode($xmlstring), true);

        return $val;
    }

    public function server()
    {
        $business = new Business(
            $appid = "oZdwA0URzyLrfEDUjqzdmFvzaJMg",
            $APP_KEY = "001b7d3059af1a707a5d4e432aa45b7a",
            $MCH_ID = "1487651632",
            $MCH_KEY = "SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3"
        );

        /**
         * 第 2 步：定义订单
         */
        $order = new Order();
        $order->body = 'test body';
        $order->out_trade_no = md5(uniqid().microtime());
        $order->total_fee = '1'; // 单位为 “分”, 字符串类型
        $order->openid = "oZdwA0fNPAFqXAyx4xHdoFjroTLQ";
        $order->notify_url = config('constants.frontend_domain');

        /**
         * 第 3 步：统一下单
         */
        $unifiedOrder = new UnifiedOrder($business, $order);
        print_r($unifiedOrder);die;

        /**
         * 第 4 步：生成支付配置文件
         */
        $payment = new Payment($unifiedOrder);
        print_r($payment);
    }
}
