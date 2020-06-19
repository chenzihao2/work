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

use WxPayRefund;

include_once(app_path() . "/alipay/wappay/service/AlipayTradeService.php");
include_once(app_path() . "/alipay/wappay/buildermodel/AlipayTradeFastpayRefundQueryContentBuilder.php");

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
            if ($order_info['payment'] == 1) {
                $create_time = $order_info['createtime'];
                //微信支付
                $result = $this->wechatCheckRefund($refundOrder, $create_time);
            } elseif ($order_info['payment'] == 3) {
                //支付宝支付
                $result = $this->aliPayCheckRefund($refundOrder);
				
            }

            if ($result) {
                refund_order::where('order', $refundOrder['order'])->update(['status' => 2]);
            }

            $sourceOffset++;
            $refundOrder = refund_order::where('status', 0)->offset($sourceOffset)->limit(1)->first();
        }
    }

    /**
     * 微信检查退款订单状态
     * @param $orderNum
     * @return mixed
     */
    private function wechatCheckRefund($refundOrder, $create_time = '') {
        $orderNum = ($refundOrder['is_batch_order'] == 1) ? $refundOrder['batch_ordernum'] : $refundOrder['order'];
        $weixinApi = "https://api.mch.weixin.qq.com/pay/refundquery";
        // $time=time();
        // 这里需要注意取商户号的的配置信息需要根据当前订单所使用的商户号而定
        // 商户号-> 0:当前生产环境主体商户号，其他:针对配置文件pay.php中的slave_[NO]而定
	      $mch_config_key = ($refundOrder['mch_account'] == 0) ? 'master' : 'slave_' . $refundOrder['mch_account'];
	      $mch_config = config('pay.wxpay.' . $mch_config_key);
	
        $time = bin2hex(random_bytes(16));
        $config['appid'] = config('wxxcx.wechat_appid');
        $config['mch_id'] = $mch_config['mchid'];
        $config['out_trade_no'] = $orderNum;

        $config['nonce_str'] = bin2hex(random_bytes(16));
        $keywords = 'appid=' . $config['appid'] . '&mch_id=' . $config['mch_id'] . '&nonce_str=' . $time . '&out_refund_no=' . $refundOrder['refund'] . '&key=' . $mch_config['mch_secret_key'];
        $sign = md5($keywords);
        $str = "<xml>
                <appid>" . config('wxxcx.wechat_appid') . "</appid>
                <mch_id>" . $mch_config['mchid'] . "</mch_id>
                <out_refund_no>" . $refundOrder['refund'] . "</out_refund_no>
                <nonce_str>$time</nonce_str>
                <sign>$sign</sign>
            </xml>";
        $result = $this->https_curl_json($weixinApi, $str, 'xml');
        $result = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        if (isset($result['refund_status_0']) && $result['refund_status_0'] == "SUCCESS") {
            return true;
        } elseif (isset($result['result_code']) && $result['result_code'] == 'FAIL') {
            //微信支付退款
            $input = new WxPayRefund();
            $input->SetOut_trade_no($orderNum);
	          $total_fee = $refundOrder['price'];
            if ($refundOrder['is_batch_order'] == 1) {
              $total_fee = order::select()->where('batch_order_num', $refundOrder['batch_ordernum'])->where('orderstatus', 1)->sum('price');
            }
            $input->SetTotal_fee($total_fee * 100);
            $input->SetRefund_fee($refundOrder['price'] * 100);
            $input->SetNotify_url(config('pay.wxpay.refund_notify_url'));

            $input->SetOut_refund_no($refundOrder['refund']);
            $input->SetOp_user_id("1487651632");
            $input->SetNonce_str("1487651632");

            $mch_config_key = ($refundOrder['mch_account'] == 0) ? 'master' : 'slave_' . $refundOrder['mch_account'];
            if ($create_time < '2020-06-16 16:30:00') {
                $mch_config_key = 'master_1';
            }
            $mch_config = config('pay.wxpay.' . $mch_config_key);

            $config['appid'] = config('wxxcx.wechat_appid');
            $config['secret'] = config('wxxcx.wechat_appsecret');
            $config = array_merge($config, $mch_config);

            $result = \WxPayApi::refund($input, $config);
            //$result_info = 'order_num:['. $orderNum  .']【' . json_encode($result)  . '】';
            //\Log::info($result_info);
        }
        return false;
    }


    private function aliPayCheckRefund($refund) {
	      $orderNum = ($refund['is_batch_order'] == 1) ? $refund['batch_ordernum'] : $refund['order'];
        $RequestBuilder = new \AlipayTradeFastpayRefundQueryContentBuilder();
        $RequestBuilder->setOutTradeNo($orderNum);
        $RequestBuilder->setOutRequestNo($refund['refund']);

        $config = [
            'app_id' => config("pay.alipay.appid"),
            'merchant_private_key' => config("pay.alipay.private_key"),
            'notify_url' => config("pay.alipay.notify_url"),
            'return_url' => config("pay.alipay.return_url"),
            'charset' => "UTF-8",
            'sign_type' => "RSA2",
            'gatewayUrl' => "https://openapi.alipay.com/gateway.do",
            //支付宝公钥,查看地址：https://openhome.alipay.com/platform/keyManage.htm 对应APPID下的支付宝公钥。
            'alipay_public_key' => config("pay.alipay.alipay_publick_key"),
        ];


       /* $Response = new \AlipayTradeService($config);
        $result = $Response->refundQuery($RequestBuilder);
        if ($result->code == 10000) {
            return true;
        } else {}*/
	      $RequestBuilder = new \AlipayTradeRefundContentBuilder();
        $RequestBuilder->setOutTradeNo($orderNum);
        $RequestBuilder->setRefundAmount($refund['price']);
        $RequestBuilder->setOutRequestNo($refund['refund']);
        $RequestBuilder->setRefundReason('判定黑单');

        $Response = new \AlipayTradeService($config);
        $result=$Response->Refund($RequestBuilder);
        if ($result->code == 10000) {
          return true;
        } else {
          return false;
        }
        
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
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);

        if ($type == 'json') {//json $_POST=json_decode(file_get_contents('php://input'), TRUE);
            $headers = array(
                "Content-type: application/json;charset=UTF-8", "Accept: application/json", "Cache-Control: no-cache", "Pragma: no-cache"
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
    public function createSign($parms) {
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

    public function getJoin($arrVal,$joinType=1,$joinChar='&',$sequenceKey=null){
        $resArr = array();
        if(is_array($arrVal) && !empty($arrVal)) {
            if(empty($sequenceKey)){
                foreach ($arrVal as $k => $v) {
                    if($joinType == 1) array_push($resArr,$k.'='.$v);
                    else if($joinType == 2) array_push($resArr,$v);
                }
            }else{
                $arrKey = explode(',',$sequenceKey);
                foreach ($arrKey as $v) {
                    if(!isset($arrVal[$v])) return false;
                    if($joinType == 1) array_push($resArr,$v.'='.$arrVal[$v]);
                    else if($joinType == 2) array_push($resArr,$arrVal[$v]);
                }
            }
        }
        return join($joinChar,$resArr);
    }

    public function httpRequestOnce($url, $param, $type = 'post', $verify = '', $header = '') {
        if (!empty($param) && is_array($param)) {
            $param = http_build_query($param);
        }
        $curlHandle = curl_init($url . ($type != 'post' ? "?$param" : ''));                                        // 初始化curl
        $options = array(
            CURLOPT_HEADER => false,
            // 不显示返回的Header区域内容
            CURLOPT_RETURNTRANSFER => true,
            // 获取的信息以文件流的形式返回
            CURLOPT_CONNECTTIMEOUT => 20,
            // 连接超时
            CURLOPT_TIMEOUT => 40
            // 总超时
        );
        if ($type == 'post') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $param;
        }
        if ($verify !== '') {
            $options[CURLOPT_SSL_VERIFYPEER] = $verify; // 验证对方提供的（读取https）证书是否有效，过期，或是否通过CA颁发的！
            $options[CURLOPT_SSL_VERIFYHOST] = $verify; // 从证书中检查SSL加密算法是否存在
        }
        if ($header !== '') {
            $options[CURLOPT_HTTPHEADER] = $header; //header信息设置
        }
        curl_setopt_array($curlHandle, $options);
        $httpResult = curl_exec($curlHandle);
        $errorMsg = curl_error($curlHandle);
        if (false === $httpResult || !empty($errorMsg)) {
            $errorNo = curl_errno($curlHandle);
            $errorInfo = curl_getinfo($curlHandle);
            curl_close($curlHandle);
            return array(
                'result' => false,
                'msg' => $errorMsg,
                'url' => "[$type]$url?" . urldecode($param),
                'errno' => $errorNo,
                'errinfo' => $errorInfo
            );
        }
        curl_close($curlHandle);//关闭curl
        return array(
            'result' => true,
            'msg' => $httpResult,
            'url' => "[$type]$url?" . urldecode($param)
        );
    }
}
