<?php
/**
 * User: WangHui
 * Date: 2018/7/26
 * Time: 16:00
 */

namespace App\Console\Commands;


use App\models\order;
use App\models\refund_order;
use Illuminate\Console\Command;
use QKPHP\Common\Utils\Http;

class refund_check extends Command {

    protected $signature = 'refund_check';

    protected $description = 'refund_check';

    public function __construct() {
        parent::__construct();

    }

    public function handle() {
        $sourceOffset = 0;
        $refundOrder = refund_order::where('status', 0)->offset($sourceOffset)->limit(1)->first();
        while ($refundOrder) {
            //查询订单详情
            $order_info = order::select()->where('ordernum', $refundOrder['order'])->first();
            if($order_info['payment'] == 1){
                //微信支付
                $result = $this->wechatCheckRefund($refundOrder['order']);
            } elseif ($order_info['payment'] == 2) {
                //华移支付
                $result = $this->hyCheckRefund($refundOrder['order']);
            }
            if ($result) {
                refund_order::where('order', $refundOrder['order'])->update(['status' => 2]);
            }

            $sourceOffset++;
            $refundOrder = refund_order::where('status', 0)->offset($sourceOffset)->limit(1)->first();
        }
    }

    /**
     * 华移支付检测订单退款状态
     * @param $orderNum
     * @return bool
     */
    private function hyCheckRefund($orderNum){
        //华移支付
        $url = config('pay.hypay.orderStatusUrl');
        $params['orderNo'] = $orderNum;
        $params['merchantNo'] = config('pay.hypay.merchantNo');
        $params['timestamp'] = time() * 1000;
        $params['sign'] = $this->createSign($params);
        list($status, $content) = Http::post($url, $params);
        $content = json_decode($content, true);
        if ($content['code'] == 1) {
            if ($content['result']['code'] == 4) {
                    return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 微信检查退款订单状态
     * @param $orderNum
     * @return mixed
     */
    private function wechatCheckRefund($orderNum){
        $weixinApi = "https://api.mch.weixin.qq.com/pay/refundquery";
        // $time=time();
        $time = bin2hex(random_bytes(16));
        $config['appid'] = config('wxxcx.wechat_appid');
        $config['mch_id'] = config('pay.wxpay.mchid');
        $config['out_trade_no'] = $orderNum;

        $config['nonce_str'] = bin2hex(random_bytes(16));
        $keywords = 'appid=' . $config['appid'] . '&mch_id=' . $config['mch_id'] . '&nonce_str=' . $time . '&out_trade_no=' . $config['out_trade_no'] . '&key=SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3';
        $sign = md5($keywords);
        $str = "<xml>
                <appid>" . config('wxxcx.wechat_appid') . "</appid>
                <mch_id>" . config('pay.wxpay.mchid') . "</mch_id>
                <out_trade_no>" . $orderNum . "</out_trade_no>
                <nonce_str>$time</nonce_str>
                <sign>$sign</sign>
            </xml>";
        $result = $this->https_curl_json($weixinApi, $str, 'xml');
        $result = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if (isset($result['refund_status_0']) && $result['refund_status_0'] == "SUCCESS") {
            return true;
        }
        return false;
    }

    /**
     * curl 发送请求
     * param string $url 地址
     * param xml/array $data 参数
     * param string xml/json $type 所传类型
     * param string ca 是否需要ca证书 0 / 1
     */
    public function https_curl_json($url, $data, $type, $ca = '') {

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if ($type == 'json') {//json $_POST=json_decode(file_get_contents('php://input'), TRUE);
            $headers = array(
                "Content-type: application/json;charset=UTF-8",
                "Accept: application/json",
                "Cache-Control: no-cache",
                "Pragma: no-cache"
            );
            $data = json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        } else if ($type == 'xml') {
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        }

        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        // 是否需要微信ca证书
        if ($ca == 1) {
            curl_setopt($curl, CURLOPT_CAPATH, "weixin/rootca.pem");
            curl_setopt($curl, CURLOPT_SSLCERT, "weixin/apiclient_cert.pem");
            curl_setopt($curl, CURLOPT_SSLKEY, "weixin/apiclient_key.pem");
        }

        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno' . curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $output;
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