<?php

namespace App\Http\Controllers\Api\V1;

use App\models\buyer;
use App\models\client;
use App\models\client_extra;
use App\models\client_subscribe;
use App\models\client_money_change;
use App\models\follow;
use App\models\order;
use App\models\purchase_record;
use App\models\refund_order;
use App\models\refund_order_tmp;
use App\models\source;
use App\models\contents;
use App\models\source_free_watch;
use App\models\source_update_record;
use App\models\resource;
use App\models\source_extra;
use App\models\source_sensitives;
use Endroid\QrCode\Response\QrCodeResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use League\Flysystem\Exception;
use Ufile;
use App\Http\Controllers\Api\V1\ClientsController;
use Tymon\JWTAuth\Facades\JWTAuth;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Facades\DB;
use WxPayRefund;

use Iwanli\Wxxcx\Wxxcx;
use App\Console\Commands\subscribe;


class SourcesController extends BaseController
{

    protected $wxxcx;
    public $r_content_prefix = 'content_apply_';

    function __construct(Wxxcx $wxxcx) {
        $this->wxxcx = $wxxcx;
    }



    //世界杯活动
    //是否参加活动检查
    public function AnswerCheck($uid) {

        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            return response()->json([
                'status_code' => 10001,
                'error_message' => 'token 失效或异常， 以正常渠道获取重试'
            ]);
        }

        $count = source::select('id')->where('title', "给料世界杯竞猜   扫码查看答案")->where('uid', $uid)->count();
        if ($count >= 1) {
            $return['status_code'] = 1000;
            $return['error_message'] = '已经参加活动';
            return response()->json($return);
        } else {
            $return['data'] = array("check" => true);
            $return['status_code'] = 200;
            return response()->json($return);

        }

    }

    /**
     * 设置红单黑单
     * //     * TODO：给买家推送退款消息
     */
    public function setSourceOrderStatus(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            return response()->json([
                'status_code' => '10001',
                'error_message' => 'token 失效或异常， 以正常渠道获取重试'
            ]);
        }
        $sid = $request->input("sid", "");
        $mark = $request->input("mark", "");
        if ($sid == "" || $mark == "") {
            return response()->json([
                'status_code' => '10002',
                'error_message' => '效验参数，缺少参数'
            ]);
        }
        $source = source::where('sid', $sid)->first();

        if ($source['uid'] != $uid) {
            return response()->json([
                'status_code' => '10005',
                'error_message' => '非本人料，不允许设置红单黑单'
            ]);
        }
        if ($source['pack_type'] != 2) {
            return response()->json([
                'status_code' => '10006',
                'error_message' => '非不对返还单'
            ]);
        }
        if ($source['order_status'] != 0) {
            return response()->json([
                'status_code' => '10007',
                'error_message' => '已设置红单黑单'
            ]);
        }

        //设置红单黑单
        source::where('sid', $sid)->update([
          'order_status' => $mark,
          'is_recommend' => 0,
          'recommend_sort' => 0
        ]);

        if ($mark == 1) {
            //收益增加料所售出
            $amount = order::where('sid', $sid)->where('selledid', $uid)->where('price', '>', 0)->where('orderstatus', 1)->sum('price');

            DB::table('client_extra')->where('id', $uid)->increment('balance', $amount);   // 销售者余额增加
            DB::table('client_extra')->where('id', $uid)->increment('total', $amount);  // 收入增加
            //记录金额变更
            client_money_change::setChange($uid, $amount, 1, 2);
        }
        //黑单申请退款
        //退款
        //获取所有订单
        //建立计数器，微信正常退款支持150qps，无效退款6qps
        $clock = 1;
        $refundFalseClock = 1;
        $offset = 0;
        $query = order::select()->where('sid', $sid)->where('orderstatus', 1);
        $result = $query->offset($offset)->limit(1)->first();
        while ($result) {
            //发起退款请求
            if ($mark == 2 && $result['price'] > 0) {

                //写redis
                $redisKey = "refund_list";
                Redis::lpush($redisKey, $sid);
//				$this->sendMsg($result,2);
            } else {
                $this->sendMsg($result, $mark);

            }


            $offset++;
            $query = order::select()->where('sid', $sid)->where('orderstatus', 1);
            $result = $query->offset($offset)->limit(1)->first();
            $clock++;
            if ($clock % 100 == 0) {
                sleep(1);
            }
            if ($refundFalseClock % 5 == 0) {
                sleep(1);
            }
        }

        return response()->json([
            'status_code' => 200,
            'data' => array()
        ]);
    }


    public function getOpenId($uid) {
        $client = client::select('openid', 'serviceid')->where('id', $uid)->first();
        return $client;
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

    public function sendMsg($order, $mark) {
        //换取openid
        $userInfo = $this->getOpenId($order['buyerid']);

        if (isset($userInfo['serviceid']) && $userInfo['serviceid'] != null) {
            $type = 2;
        } else {
            $type = 1;
        }

        //支付走的公众号参数，使用小程序推送会报form id错误，判断原因是 form id 与小程序参数不拼配，故小程序直接返回
//		if($type==1){
//			return true;
//		}
        $token = $this->msg_access_token($type);

        $time = date("Y-m-d H:i:s", time());
        if ($type == 1) {
            //小程序
            $noticeTemplateId = "TSzfcY9CuEd-jYzBoPpfji4Bzmxu_W8IUuUgok9F4SE";
            $openid = $userInfo['openid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$token";


            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
            //支付信息

            $params['form_id'] = $order['prepay_id'];
            $msg = array();
            $msg['keyword1'] = [
                'value' => "您买的料已经可以查看",
                //				'value' => '11',
            ];//信息详情


            if ($mark == 2) {
                $msg['keyword1'] = [
                    'value' => "您购买的料已确认为黑，钱款将退还至微信零钱",
                ];//信息详情

            } else {
                $msg['keyword1'] = [
                    'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
                ];//信息详情
            }

            $msg['keyword2'] = [
                'value' => $time,
            ];//发布时间
            $params['data'] = $msg;

        } else {
            //公众号
            $noticeTemplateId = "23rQY_hmlkQe5TMULTjGkB-cSTX-1fGkNzbvDWLjKR8";
            $openid = $userInfo['serviceid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
//			$params['appid'] = "wx1ad97741a12767f9";
            //支付信息
            $msg = array();
            if ($mark == 2) {
                $msg['keyword1'] = [
                    'value' => "您购买的料已确认为黑，钱款将原路退回至您的支付账户。",
                ];//信息详情
                $msg['keyword2'] = [
                    'value' => "黑",
                ];//发布时间

            } else {
                $msg['keyword1'] = [
                    'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
                ];//信息详情
                $msg['keyword2'] = [
                    'value' => "红",
                ];//发布时间

            }

            $msg['keyword3'] = [
                'value' => $time,
            ];//发布时间
            $params['data'] = $msg;
        }

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');
            return ($result);
        } else {
            return null;
        }

    }

    public function refundNotice() {

        $xml = file_get_contents("php://input");
        $data = $this->FromXml($xml);
        if ($data['return_code'] == "SUCCESS") {
            $refundInfo = $this->refund_decrypt($data['req_info'], "SDJy3O9G8pQevPY1Jb5Ayy2LSIzK4yB3");
            $refundInfo = $this->FromXml($refundInfo);

            //解析加密数据
//			$order = order::where("ordernum", $data['out_trade_no'])->first();
//			if($order['orderstatus'] == 0){
//				$this->updateOrderStatus($order);
//			}
        } else {
//			\Log::INFO($data['return_msg']);
        }
        $return['return_code'] = 'SUCCESS';
        $return['return_msg'] = 'OK';
        $returnXml = '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        return $returnXml;

    }


    /**
     * 输出xml字符
     * @throws Exception
     **/
    public function ToXml($data) {
        if (!is_array($data) || count($data) <= 0) {
            throw new Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
    public function FromXml($xml) {
        if (!$xml) {
            throw new Exception("xml数据异常！");
        }
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    private function refund($data) {
        $input = new WxPayRefund();
        $input->SetOut_trade_no($data['ordernum']);
        $input->SetTotal_fee($data['price'] * 100);
        $input->SetRefund_fee($data['price'] * 100);
        $input->SetNotify_url(config('constants.backend_domain') . '/pub/source/notice');
        //退款单号
        $refund = array();
        $refund['sid'] = $data['sid'];
        $refund['order'] = $data['ordernum'];
        $refund['buyerid'] = $data['buyerid'];
        $refundOrder = $this->refundOrder();
        $refund['refund'] = $refundOrder;
        $refund['price'] = $data['price'];
        $refund['oper'] = 0;
        $refund['time'] = time();
        $refund['status'] = 0;

        refund_order::create($refund);
//die;

        $input->SetOut_refund_no($refundOrder);
        $input->SetOp_user_id("1487651632");
        $input->SetNonce_str("1487651632");

        $config['appid'] = config("wxxcx.wechat_appid");
        $config['secret'] = config("wxxcx.wechat_appsecret");
        $config['mchid'] = config("pay.wxpay.mchid");
        $config['mch_secret_key'] = config("pay.wxpay.mch_secret_key");
//		$config['sslcert_path'] = 'cert/apiclient_cert.pem';
//		$config['sslkey_path'] = 'cert/apiclient_key.pem';
        $result = \WxPayApi::refund($input, $config);
        if ($result['result_code'] == "SUCCESS") {

            //更新状态

            refund_order::where('order', $data['ordernum'])->update([
                'status' => 2
            ]);
//			refund_order::create($refund);
//			refund_order::create($refund);
            return true;
        } else {
            return false;
        }
    }

    public function refund_decrypt($str, $key) {
        $str = base64_decode($str);
        $str = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $str, MCRYPT_MODE_ECB);
        $block = mcrypt_get_block_size('rijndael_128', 'ecb');
        $pad = ord($str[($len = strlen($str)) - 1]);
        $len = strlen($str);
        $pad = ord($str[$len - 1]);
        return substr($str, 0, strlen($str) - $pad);
    }

    private function refundOrder() {
        return md5(time() . rand(0, 10000));
    }

    public function curl_post_ssl($url, $vars, $second = 30, $aHeader = array()) {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //这里设置代理，如果有的话
        //curl_setopt($ch,CURLOPT_PROXY, '10.206.30.98');
        //curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        //以下两种方式需选择一种

        //第一种方法，cert 与 key 分别属于两个.pem文件
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        //curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/cert.pem');
        //默认格式为PEM，可以注释
        //curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        //curl_setopt($ch,CURLOPT_SSLKEY,getcwd().'/private.pem');

        //第二种方式，两个文件合成一个.pem文件
        curl_setopt($ch, CURLOPT_SSLCERT, getcwd() . '/all.pem');

        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            echo "call faild, errorCode:$error\n";
            curl_close($ch);
            return false;
        }
    }

    /**
     * 获取某个料的详细信息
     */
    public function getSourcesDetails(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        //var_dump($clients['id']);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
        }
        $sid = $request->input("sid", "");
        $oid = $request->input('oid', "");
        $cid = $request->input('cid', "");
        $direct = $request->input('direct', '1') - 0;
        if (empty($sid)) {
            return $this->errorReturn('10002', "效验参数，缺少参数");
        }

        $source = source::where('sid', $sid)->first();

        //是否可以免费看
        $free = $this->checkSourceFreeWatch($sid);
        //非发料人，检查购买订单
        if ($source['uid'] != $uid) {
            $orders = $this->getOrdersInfo($sid, $oid, $direct, $uid);
            if (empty($orders)) {
                if ($free == 0) {
                    return response()->json([
                        'status_code' => '10005',
                        'error_message' => '未购买'
                    ]);
                }
            }
        }
        $pageSize = 7;

        $count = 0;
        $contents = [];

        $sourceUid = $source['uid'];
        $sourceUserInfo = client::select('id', 'nickname', 'avatarurl', 'is_white')->where('id', $sourceUid)->first();
        $is_white = $sourceUserInfo['is_white'] ?: 0;
        $refund_time = '';

        if (isset($orders) && !empty($orders)) {
            foreach ($orders as $order) {
                if ($source['pack_type'] == 2) {
                    $order_num = $order['ordernum'];
                    $tmp_data = refund_order_tmp::where('order', $order_num)->first();
                    $refund_time = $tmp_data['refund_time'];
                    if ($refund_time) {
                        $refund_time = date('Y-m-d H:i:s', $refund_time);
                    }
                }
                $contentQuery = contents::where('sid', $sid)->orderBy('cid', 'desc');
                if (!$is_white) {
                    $contentQuery->where('is_check', 1);
                }
                if ($order['pack_type'] == 1) {
                    $start_time = date('Y-m-d H:i:s', strtotime($order['start_time']) - 86400);
                    // $start_time = $order['start_time'];
                    if (($order['orderstatus']) != 0) {
                        $end_time = date('Y-m-d H:i:s', strtotime($order['createtime']) + 86400 * ($source['pack_day'] + $source['delayed_day']));
                    } else {
                        $end_time = date('Y-m-d H:i:s', time());
                    }
                    $contentQuery->where('createtime', '>=', $start_time)->where('createtime', '<', $end_time);
                }
                if ($cid && $direct == 1) {
                    $contentQuery->where('cid', '<', $cid);
                }

                if ($cid && $direct != 1) {
                    $contentQuery->where('cid', '>', $cid);
                    $currentCount = $contentQuery->count();     //当前order的总条数，如果大于pagesize，
                    // if ($currentCount > $pageSize + 1 - $count) {
                    // 	$contentQuery->offset($currentCount - ($pageSize + 1 - $count));
                    // }
                }
                // $contentsList = $contentQuery->limit($pageSize + 1 - $count)->get()->ToArray();
                $contentsList = $contentQuery->get()->ToArray();
                if (count($contentsList) > 0) {
                    foreach ($contentsList as $index => $content) {
                        $contentsList[$index]['oid'] = $order['id'];
                    }
                }

                if ($order['pack_type'] == 3) {
                    if ($source['play_start'] == 1) {
                        $now = time();
                        $watchStart = $source['play_time'] - 60 * 60;
                        if ($now <= $watchStart) {
                            //不可看
                            $contentsList = array();

                        }
                    }
                }
                if ($direct == 1) {
                    $contents = array_merge($contents, $contentsList);
                } else {
                    if (empty($oid) && empty($cid)) {
                        $contents = array_merge($contents, $contentsList);
                    } else {
                        $contents = array_merge($contentsList, $contents);
                    }
                }
                $count += count($contentsList);
                if ($count > $pageSize) {
                    break;
                }
            }
        } else {

            //三小时后免费看的料，增加查看日志
            if ($source['uid'] != $uid) {
                $log = source_free_watch::where('uid', $uid)->where('sid', $sid)->first();
                if (empty($log)) {
                    $watchLog['uid'] = $uid;
                    $watchLog['sid'] = $sid;
                    $watchLog['times'] = 1;
                    $watchLog['create_time'] = time();
                    DB::table('source_free_watch')->insert($watchLog);
                } else {
                    $watchLog['times'] = $log['times'] + 1;
                    $watchLog['last_time'] = time();
                    DB::table('source_free_watch')->where('id', $log['id'])->update($watchLog);
                }
            }

            $contentQuery = contents::where('sid', $sid)->orderBy('cid', 'desc');
            if (!$is_white) {
                $contentQuery->where('is_check', 1);
            }
            if ($cid && $direct == 1) {
                $contentQuery->where('cid', '<', $cid);
            }
            if ($cid && $direct != 1) {
                $contentQuery->where('cid', '>', $cid);
                $currentCount = $contentQuery->count();     //当前order的总条数，如果大于pagesize，
                // if ($currentCount > $pageSize + 1) {
                // 	$contentQuery->offset($currentCount - $pageSize - 1);
                // }
            }
            // $contents = $contentQuery->limit($pageSize + 1 - $count)->get()->ToArray();
            $contents = $contentQuery->get()->ToArray();
        }

        $contents = $this->getResourcesInfo($source, $contents);
        //比赛类型料检查是否可看
        $watch = 1;
        if ($source['pack_type'] == 3) {
            if ($source['play_start'] == 1) {
                $now = time();
                $getInfoTime = $source['play_time'] - 60 * 60;
                if ($now >= $getInfoTime) {
                    //可看信息
                    $watch = 1;
                } else {
                    //不可看信息
                    $watch = 0;
                }
            }
        }
        if ($source['uid'] == $uid) {
            //本人料始终可看
            $watch = 1;
        }

        $ret = [
            'source' => [
                'sid' => $source['sid'],
                'title' => $source['title'],
                'sub_title' => $source['sub_title'],
                //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
                'pack_type' => $source['pack_type'],
                'pack_day' => $source['pack_day'],
                'delayed_day' => $source['delayed_day'],
                'order_status' => $source['order_status'],
                'price' => $source['price'],
                'refund_time' => $refund_time,
                'watch' => $watch,
                'watch_time' => date("Y-m-d H:i:s", $source['play_time'] - 60 * 60),
                'status' => strrev(sprintf('%08d', decbin($source['status']))),
            ]
        ];
        $ret['source'] = source_sensitives::apply($ret['source']);

        // $ret['hasNext'] = count($contents) > $pageSize;
        $ret['hasNext'] = false;
        if ($ret['hasNext']) {
            if ($direct == -1) {
                array_shift($contents);
            } else {
                array_pop($contents);
            }
        }

        
        $ret['contents'] = $contents;
        $r_con_key = $this->r_content_prefix . $source['sid'];
        $apply_contents = Redis::get($r_con_key);
        if ($apply_contents) {
            $ret['contents'] = json_decode($apply_contents, 1);
        } else {
            $ret['contents'] = source_sensitives::apply($ret['contents'], 1, $is_white);
            Redis::set($r_con_key, json_encode($ret['contents']));
        }
        $ret['is_owner'] = false;
        if ($source['uid'] == $uid) {
            $ret['is_owner'] = true;
        }
        if ($uid != $source['uid']) {
            $latelyOrder = order::where('sid', $sid)->where('buyerid', $uid)->whereRaw('orderstatus in (1,3)')->first();
            if ($latelyOrder && !empty($latelyOrder) && $source['pack_type'] == 1 && $source['price'] > 0) {
                if (strtotime($latelyOrder['createtime']) + ($source['pack_day'] + $source['delayed_day']) * 86400 < time()) {
                    $ret['tips'] = '你购买的包时段料已过期，请续费';
                    $ret['recharge'] = true;
                }
            }
        }
        if ($source['title'] == "给料世界杯竞猜   扫码查看答案") {
            $ret['worldCup'] = true;
            $userName = client::select('nickname')->where('id', $source['uid'])->first();
            $ret['sellName'] = $userName['nickname'];
        }

        //增加发布料用户信息
        $sourceUserInfo['is_follow'] = 0;

        $followInfo = follow::where('star', $sourceUid)->where('fans', $uid)->first();
        if(empty($followInfo)){
            $sourceUserInfo['is_follow'] = 0;
        }else{
            if ($followInfo['status']==1){
                $sourceUserInfo['is_follow'] = 1;
            }else{
                $sourceUserInfo['is_follow'] = 0;
            }
        }
        $ret['pushUser'] = $sourceUserInfo;
        //是否关注消息助手
        $appid = config("wxxcx.wechat_subscribe_appid");
        $subscribeInfo = client_subscribe::where('user_id', $uid)->where('appid', $appid)->first();
        $subscribe = $subscribeInfo['subscribe'];
        if (!$subscribe) {
            $openid = $subscribeInfo['openid'];
            $sub_obj = new subscribe(); 
            $subscribe = $sub_obj->check_subscribe($openid, $uid);
        }
        $ret['subscribe'] = $subscribe;

        return response()->json([
            'status_code' => 200,
            'data' => $ret
        ]);
        /*
                $stagnate_day = 2*86400;
                // 是否为制作者
                $permission = 4;
                if ( $data['uid'] == $uid ) {
                    $permission = 1;
                    $contentList = $this->contentsList($sid,$cid,$page_direct);
                } else {
                    if ( floatval($data['price']) != 0 || $data['thresh'] != 0 ) {
                        $order = order::where('sid', $sid)->where('buyerid', $uid)->orderBy('createtime', 'desc')->first();
                        // 判断是否购买
                        $buy = $this->decAnalysis($order['orderstatus'], '1');
                        if ( $buy != 1 ) {
                            $permission = 3;
                            $return['status_code'] = '10005';
                            $return['permission'] = $permission;
                            $return['error_message'] = '未购买';
                            return response()->json($return);
                        } else {
                            $permission = 4;
                            if($data['pack_type'] != 0){
                                $content = DB::table('contents')->where('sid',$data['sid'])->orderBy('createtime','desc')->first();
                                $content = $this->object2Array($content);
                                $source_delayed = DB::table('source_update_record')->where('sid',$data['sid'])->where('rkey','delayed_day')
                                    ->where('rvalue',$data['delayed_day'])->orderBy('createtime','desc')->first();
                                if(time()-strtotime($content['createtime'])>$stagnate_day){
                                    $data['stagnate'] = "今日卖家无更新";
                                }
                                if($source_delayed&&!empty($source_delayed)){
                                    $source_delayed = $this->object2Array($source_delayed);
                                    $delay_starttime = $source_delayed['createtime'];
                                    if(time()<strtotime($delay_starttime.' +'.$data['delayed_day'].' day') && time()>strtotime($delay_starttime)){
                                        $data['tips'] = "卖家暂停更新".$data['delayed_day']."天,有效期将顺延";
                                    }
                                    if(time()-strtotime($delay_starttime.' +'.$data['delayed_day'].' day')>$stagnate_day){
                                        $data['stagnate'] = "今日卖家无更新";
                                    }
                                }
                                $all_delayedDay = $all_delayedDay = DB::table('source_update_record')
                                    ->where('sid',$data['sid'])->where('rkey','delayed_day')->sum('rvalue');
                                if(!$all_delayedDay&&!isset($all_delayedDay)){
                                    $all_delayedDay = 0;
                                }

                                if(time()>strtotime($order['start_time'])+86400*$data['pack_day']+86400*$all_delayedDay){
                                    $data['expired'] = '你购买的包时段料已过期，请续费';
                                    $data['need_pay'] = 1;
                                }
                                $contentList = $this->contentsList($sid,$cid,$page_direct);
                            }else{
                                $contentList = $this->contentsList($sid,$cid,$page_direct);
                            }
                            $del = $this->decAnalysis($order['orderstatus'], '2');
                            if ( $del == 1 ) {
                                order::where('id', $order['id'])->update(['orderstatus' => ($order['orderstatus'] | 2)]);
                            }
                        }
                    } else {
                        $contentList = $this->contentsList($sid,$cid,$page_direct);
                        $del = $this->decAnalysis($data['status'], 2);      //用户删除
                        if ( $del == 1 ) {
                            $this->errorReturn('10004','料已被删除');
                        }
                    }
                }

                $offelf = $this->decAnalysis($data['status'], 4);       //系统下架
                if ( $offelf == 1 ) {
                    $this->errorReturn('10003','后台已删除');
                }
                $data['pagenum'] = $contentList['pagenum'];
                $data['contents'] = $contentList['data'];

                $status = decbin($data['status']);
                $data['status'] = strrev(sprintf('%08d', $status));

                $return['status_code'] = '200';
                $return['permission'] = $permission;
                $return['data'] = $data;

                return response()->json($return);
        */
    }

    public function batchOrderDetails(Request $request, $uid) {
      $token = JWTAuth::getToken();
      $clients = $this->UserInfo($token);
      if (empty($token) || $clients['id'] != $uid) {
        return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
      }
      
      $suid = $request->input("suid", "");
      $sids = $request->input("sids", "");
      $sids = json_decode($sids, true);
	
      $batchSources = array();
      $sourceList = source::whereIn('sid', $sids)->get()->ToArray();
      $sourceUserInfo = client::select('id', 'nickname', 'avatarurl', 'is_white')->where('id', $suid)->first();
      $is_white = $sourceUserInfo['is_white'];
      foreach($sourceList as $source) {
        $watch = 1;
        $is_free = $this->checkSourceFreeWatch($source['sid']); //是否可以免费看
        $contents = [];   //内容信息
        if ($source['uid'] != $uid) {
          $orders = $this->getOrdersInfo($source['sid'], 0, 1, $uid);
          if (empty($orders)) {
            if($is_free == 0) {
              continue;
              //return response()->json(['status_code' => '10005', 'error_message' => '未购买']);
            } else {
              //三小时后免费看的料，增加查看日志
              $log = source_free_watch::where('uid', $uid)->where('sid', $source['sid'])->first();
              if (empty($log)) {
                $watchLog['uid'] = $uid;
                $watchLog['sid'] = $source['sid'];
                $watchLog['times'] = 1;
                $watchLog['create_time'] = time();
                DB::table('source_free_watch')->insert($watchLog);
              } else {
                $watchLog['times'] = $log['times'] + 1;
                $watchLog['last_time'] = time();
                DB::table('source_free_watch')->where('id', $log['id'])->update($watchLog);
              }
              $contents = contents::where('sid', $source['sid'])->where('is_check', 1)->orderBy('cid', 'desc')->get()->ToArray();
              if ($is_white) {
                $contents = contents::where('sid', $source['sid'])->orderBy('cid', 'desc')->get()->ToArray();
              }
            }
          } else {
            foreach($orders as $order) {
              $contentQuery = contents::where('sid', $source['sid'])->where('is_check', 1)->orderBy('cid', 'desc');
              if ($is_white) {
                $contentQuery = contents::where('sid', $source['sid'])->orderBy('cid', 'desc');
              }
              if ($order['pack_type'] == 1) {
                $start_time = date('Y-m-d H:i:s', strtotime($order['start_time']) - 86400);
                if (($order['orderstatus']) != 0) {
                  $end_time = date('Y-m-d H:i:s', strtotime($order['createtime']) + 86400 * ($source['pack_day'] + $source['delayed_day']));
                } else {
                  $end_time = date('Y-m-d H:i:s', time());
                }
                $contentQuery->where('createtime', '>=', $start_time)->where('createtime', '<', $end_time);
              }
              //$contentsList = $contentQuery->limit(3)->get()->ToArray();
              $contentsList = $contentQuery->get()->ToArray();

              if (count($contentsList) > 0) {
                foreach ($contentsList as $index => $content) {
                  $contentsList[$index]['oid'] = $order['id'];
                }
              }

              if ($source['pack_type'] == 3) {
                if ($source['play_start'] == 1) {
                  if (time() >= $source['play_time'] - 60 * 60) {
                    $watch = 1;   //可看信息
                  } else {
                    $contentsList = array();
                    $watch = 0;   //不可看信息
                  }
                }
              }
              $contents = array_merge($contents, $contentsList);
            }
          }
        }

        //contents
        $r_con_key = $this->r_content_prefix . $source['sid'];
        $apply_contents = Redis::get($r_con_key);
        if ($apply_contents) {
            $contents = json_decode($apply_contents, 1);
        } else {
            $contents = $this->getResourcesInfo($source, $contents);
            $contents = source_sensitives::apply($contents, 1, $is_white);
            Redis::set($r_con_key, json_encode($contents));
        }
        $sourceInfo = array(
          'sid' => $source['sid'],
          'title' => $source['title'],
          //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
          'pack_type' => $source['pack_type'],
          'pack_day' => $source['pack_day'],
          'delayed_day' => $source['delayed_day'],
          'order_status' => $source['order_status'],
          'watch' => $watch,
          'watch_time' => date("Y-m-d H:i:s", $source['play_time'] - 60 * 60),
          'contents' => $contents
        );
        $sourceInfo = source_sensitives::apply($sourceInfo);

        $batchSources[] = $sourceInfo;
      }

      //增加发布料用户信息
      $followInfo = follow::where('star', $sourceUserInfo['id'])->where('fans', $uid)->first();
      $sourceUserInfo['is_follow'] = (isset($followInfo['status']) && $followInfo['status'] == 1) ? 1 : 0;

      $ret = array(
        'sources' => $batchSources,
        'pushUser' => $sourceUserInfo
      );
    //是否关注消息助手
      $appid = config("wxxcx.wechat_subscribe_appid");
      $subscribeInfo = client_subscribe::where('user_id', $uid)->where('appid', $appid)->first();
      $subscribe = $subscribeInfo['subscribe'];
      if (!$subscribe) {
          $openid = $subscribeInfo['openid'];
          $sub_obj = new subscribe();
          $subscribe = $sub_obj->check_subscribe($openid, $uid);
      }
      $ret['subscribe'] = $subscribe;

      return response()->json([
        'status_code' => 200,
        'data' => $ret
      ]);
    }

    private function getOrdersInfo($sid, $oid, $direct, $uid) {
        $ordersQuery = order::where('sid', $sid)->where('buyerid', $uid)->whereRaw('orderstatus & 1');
        if (!$oid) {
            $ordersQuery->orderBy('createtime', 'desc');
        }
        if ($oid && $direct != 1) {
            $ordersQuery->where('id', '>=', $oid)->orderBy('createtime', 'asc');
        }
        if ($oid && $direct == 1) {
            $ordersQuery->where('id', '<=', $oid)->orderBy('createtime', 'desc');
        }
        $orders = $ordersQuery->limit(10)->get();
        if ($orders->isEmpty()) {
            return '';
        }
        return $orders;
    }

    private function getResourcesInfo($source, $contents) {
        $resourceQuery = resource::where('sid', $source['sid']);
        if (!empty($contents)) {
            $cids = [];
            foreach ($contents as $ind => $content) {
                $cids[] = $content['cid'];
            }
            $resourceQuery->whereRaw('cid in (' . implode(',', $cids) . ')');
        } else if ($source['pack_type'] == 0) {
            $contents = [
                [
                    'oid' => '-1',
                    'cid' => '-1',
                    'description' => $source['description'],
                    'createtime' => $source['createtime']
                ]
            ];
        }
        $resources = $resourceQuery->get()->ToArray();

        $resourceMap = [];
        foreach ($resources as $resource) {
            $cidKey = $resource['cid'] < 1 ? '-1' : $resource['cid'];
            if (!isset($resourceMap[$cidKey])) {
                $resourceMap[$cidKey] = [];
            }
            $resourceMap[$cidKey][] = $resource;
        }
        foreach ($contents as $ind => $content) {
            if (!isset($resourceMap[$content['cid']])) {
                $contents[$ind]['resource'] = ['rid' => '-1'];
                continue;
            }
            $pics = [];
            $voice = '';
            $video = '';
            $rid = 0;
            foreach ($resourceMap[$content['cid']] as $resource) {
                $rid = $resource['id'];
                if ($resource['stype'] == 3) {
                    $pics[$resource['sindex']] = $resource['url'];
                } else if ($resource['stype'] == 8) {
                    $video = $resource['url'];
                } else if ($resource['stype'] == 2) {
                    $voice = $resource['url'];
                }
            }
            ksort($pics, SORT_NUMERIC);
            $contents[$ind]['resource'] = [
                'rid' => $rid,
                'pics' => array_values($pics),
                'video' => $video,
                'voice' => $voice
            ];
        }
        return $contents;
    }

    /**
     * 获取料的简要信息
     */
    public function getSourceBrief(Request $request, $sid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($clients['openid']) || $clients['openid'] != $clients['serviceid']) {
            return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
        }
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
            }
        }

        $scene = $request->input('scene', '');
        $rid = $request->input('rid', '');
        $recordPrice = -1;
        //if ($scene == 'qr') { // 扫码进入
        $prefix = substr($sid, 0, 2);
        if ($prefix == 's.') {
            $sid = substr($sid, 2);
            $recordPrice = -2;
        } else if ($prefix == 'r.') {
            $rid = substr($sid, 2);
            $sid = '';
            $record = source_update_record::where('rid', $rid)->first();
            if ($record) {
                $sid = $record['sid'];
                if ($record['rkey'] == 'price') {
                    $recordPrice = $record['rvalue'];
                }
            }
        } else { // 旧的sourceid
            $oldsourceid = $sid;
            $sid = '';
            $recordPrice = -2;
            $source = source::where('id', $oldsourceid)->first();
            if ($source) {
                $sid = $source['sid'];
            }
        }
        //} else {
        //    if ($rid) {
        //        $record = source_update_record::where('rid', $rid)->first();
        //        if ($record['rkey'] == 'price') {
        //            $recordPrice = $record['rvalue'];
        //        }
        //}
        //}
        if (empty($sid)) {
            return $this->errorReturn('10003', "料不存在");
        }

        $selectQuery = source::select('source.sid as sid', 'source.pack_type as pack_type', 'source.pack_day as pack_day', 'source.delayed_day as delayed_day', 'title', 'sub_title', 'source.status', 'price', 'order_status', 'source.primary_price as primary_price', 'thresh', 'play_time', 'play_end', 'free_watch', 'uid', 'source.createtime', 'source.url as url', 'source.is_check as is_check', 'source.is_notice as is_notice', 'is_recommend');

        $data = $selectQuery->where('source.sid', $sid)->first();

        $userInfo = client::select('nickname', 'avatarurl', 'is_white')->where('id', $data['uid'])->first();
        //定时下架
        $extra_info  = source_extra::where('sid', $data['sid'])->first();
        if ($extra_info['is_sold_out'] && $extra_info['sold_out_time']){
            if ($data['status'] == 3) {
                source_extra::where('sid', $data['sid'])->update(['is_sold_out' => 0]);
            }
            if ($data['status'] == 0) {
                if (strtotime($extra_info['sold_out_time']) < time()) {
                    source_extra::where('sid', $data['sid'])->update(['is_sold_out' => 0]);
                    source::where('sid', $data['sid'])->update(['status' => 3]);
                    $data['status'] = 3;
                }
            }
        }
        //定时下架
        //定时公开
        if ($extra_info['open_time'] && $extra_info['is_open']) {
            if ($data['free_watch']) {
                source_extra::where('sid', $data['sid'])->update(['is_open' => 0]);
            } else {
                if (strtotime($extra_info['open_time']) < time()) {
                    source_extra::where('sid', $data['sid'])->update(['is_open' => 0]);
                    source::where('sid', $data['sid'])->update(['free_watch' => 1]);
                    $data['free_watch'] = 1;
                }
            }
        } 
        //定时公开

        $soldNumber = $extra_info['soldnumber'];
        $data['nickname'] = $userInfo['nickname'];
        $data['avatarurl'] = $userInfo['avatarurl'];
        $data['is_white'] = $userInfo['is_white'];
        $data['is_white'] = $data['is_check'] == 1 ? 1 : 0;
        $data['soldnumber'] = $soldNumber;
        if (!$data) {
            return $this->errorReturn('10003', "料不存在");
        }
        $data['soldout'] = $data['thresh'] > 0 && ($data['thresh'] - $data['soldnumber'] <= 0);
        if ($recordPrice == -2) {
            $recordPrice = $data['primary_price'];
        }


        //检测用户是否拉入黑名单
        $data['buyer_black_status'] = 1;
        $buyerStatus = buyer::checkBuyerStatus($data['uid'], $clients['id']);
        if(!$buyerStatus){
            $data['buyer_black_status'] = 0;
	    return $this->errorReturn('10007', "没有购买权限");
        }

        //比赛一小时后不可买，检查是否可买（1可买，0不可买）
	$disable_recommend = 0;
        $buy = 1;
        if ($data['pack_type'] == 3) {
            if ($data['play_end'] == 1) {
                $now = time();
                $getInfoTime = $data['play_time'] + 60 * 60;
                if ($now >= $getInfoTime) {
                    //不可买
                    $buy = 0;
		$disable_recommend = 1;
                } else {
                    //可买
                    $buy = 1;
                }
            }
        }
        //设置红黑单的料不允许再次购买
        if ($data['pack_type'] == 2) {
            if ($data['order_status'] != 0) {
                //不可买
                $buy = 0;
		$disable_recommend = 1;
            } else {
                //可买
                $buy = 1;
            }
        }

	if (time() - strtotime($data['createtime']) >= 24 * 60 * 60) {
          if ($data['pack_type'] != 1) { 
	    $disable_recommend = 1;
	  }
        }

	 if ($data['order_status'] != 0 || $data['is_recommend'] == 1) {
              $disable_recommend = 1;
            }


        $permission = 4;
        // 所有者
        if (isset($clients['id']) && $data['uid'] == $clients['id']) {
            $permission = 1;
        }

        // 是否已经发起支付
        $order = order::where('buyerid', $clients['id'])->where('sid', $sid)->whereRaw('orderstatus in (1,3)')->first();

        if (!empty($order)) {
            if ($order['orderstatus'] == 3) {
                order::where('buyerid', $clients['id'])->where('sid', $sid)->where('orderstatus', 3)->update(['orderstatus' => 1]);
            }
            $status = decbin($order['orderstatus']);
            $oldStatus = sprintf('%08d', $status);
            $newstatus = substr($oldStatus, -1, 1);
            if ($newstatus == 1) {
                $permission = 2;
            } else if ($newstatus == 0) {
                if ($permission != 1) {
                    $permission = 3;
                }
            }
        }
        if (($permission == 1 || $permission == 2) && ($data['pack_type'] == 2 || $data['pack_type'] == 3 || $data['pack_type'] == 0)) {
            $buy = 0;
        }

        $data['buy'] = $buy;
	    $data['disable_recommend'] = $disable_recommend;
        $data['free'] = $this->checkSourceFreeWatch($sid);
        $data['permission'] = $permission;
        $data['price'] = floatval($data['price']);

        $del = $this->decAnalysis($data['status'], '2');
        if ($del == 1) {
            //return $this->errorReturn('10002', "用户已删除");
        }

        $shelf = $this->decAnalysis($data['status'], 4);
        if ($shelf == 1) {
            //return $this->errorReturn('10003', "系统已下架");
        }
        $handleQueue = 1;
        if ($handleQueue) {
            $data['pic_status'] = 1;        //handled
        } else {
            $data['pic_status'] = 0;        //handle
        }

        //$data['url'] = $this->processSourceUrl($data);
        $data['url'] = (strpos($data['url'], 'https') === false) ? $this->processSourceUrl($data) : $data['url'];
	$data['tips'] = 'tips料状态文案';
        $data['rid'] = $rid;

        $return['status_code'] = '200';
        $priceChanged = '';
        if ($recordPrice != -1 && $recordPrice != $data['price']) {
            $priceChanged = "卖家已调整价格为 " . $data['price'] . " 元";
        }
        $data['price_changed'] = $priceChanged;
        $needPay = false;
        if ($permission == 2 && $this->checkOrderExpired($order, $data)) {
            $data['expired'] = '你购买的包时段料已过期，请续费';
            $needPay = true;
        } else {
            $data['expired'] = '';
        }
        if ($data['price'] <= 0) {
            $needPay = false;
        }
        if ($permission > 2) {
            $needPay = true;
        }

        //记录用户操作步骤
        if ($needPay) {
            purchase_record::setPurchaseRecord($sid, $clients['id'], $data['uid'], 1);
        }

        //用户下架的料已购买者可以查看
        if ($data['status'] == 3 && !$needPay && in_array($permission, [2])) {
            $data['status'] = 0;
        }

        //内容公开
        if ($data['free_watch'] && $data['status'] == 0) {
            $needPay = false;
        }
        $data['need_pay'] = $needPay;

        $data['status'] = strrev(sprintf('%08d', decbin($data['status'])));

        if ($data['order_status'] == 2) {
            $data['balance'] = 0;

        } else {
            $data['balance'] = order::where('sid', $sid)->whereRaw('orderstatus & 1')->sum('price');;
        }
        //$data['soldnum'] = $data['soldnumber'];
	$sold_num = order::select()->where('sid', $sid)->whereRaw("orderstatus & 1")->count();
        $data['soldnum'] = $sold_num;
        //if ($data['pack_type'] == 0 && $data['price'] > 0) {
        //    $data['pack_type'] = 5;
        //}
        $data = source_sensitives::apply($data);
        $return['data'] = $data;
        return response()->json($return);
    }

    private function processSourceUrl($source) {
        $url = $source['url'];
        if (strpos($url, 'http') !== 0) {
            $url = 'https://zy.qiudashi.com/' . $url;
        }
        return $url;
    }

    /**
     * 创建料
     */
    public function postSourcesAdd(Request $request) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);

        $data['uid'] = $request->input('uid', '');
        $data['title'] = $request->input('title', '');
        $data['sub_title'] = $request->input('sub_title', '');
        $data['price'] = $request->input('price', '0');
        $data['thresh'] = $request->input('thresh', '0');
        $data['free_watch'] = $request->input('free_watch', '0');
        $data['resources'] = $request->input('resources', "");
        if (!is_array($data['resources'])) {
            $data['resources'] = json_decode($data['resources'], true);
        }
        $data['desc'] = $request->input('desc', "");   // 文字资源
        $data['section_id'] = $request->input('section_id', '');
        $data['pack_type'] = $request->input('pack_type', '0');
        $data['play_time'] = $request->input('date', '0');
        $data['form_id'] = $request->input('formId', '');
        $data['play_start'] = $request->input('date_before', '0');
        $data['play_end'] = $request->input('date_after', '0');
        $data['pack_day'] = $request->input('pack_day', '0');
        $data['delayed_day'] = $request->input('delayed_day', '0');
        $data['sid'] = $request->input('sid', '');

        $errorMsg = '';

        //获取当前支付渠道
        $payment = config('pay.payment');

        if ($data['price'] != 0 && $data['price'] < 1 && $payment == 'hypay') {
            //华移支付  不能低于1元
            $errorMsg = '定价不能低于1元';
        }

        if (mb_strlen($data['sub_title']) > 40) {
            $errorMsg = "副标题过长";
        }

        if ($data['pack_type'] == 1) {
            if ($data['pack_day'] > 90) {
                $errorMsg = '包时段不能超过90天';
            }
            // if ($data['price'] > 3000) {
            // 	$errorMsg = '时段包定价不能超过3000元';
            // }

        } else {
            // if ($data['price'] > 2000) {
            // 	$errorMsg = '定价不能超过2000元';
            // }
        }
        if (!empty($errorMsg)) {
            $return['status_code'] = "-10000";
            $return['error_message'] = $errorMsg;
            return response()->json($return);
        }

        if (empty($token) || $clients['id'] != $data['uid']) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $user_status = $this->decAnalysis($clients['status'], 8);
        if ($user_status == 1) {
            $return['status_code'] = '10004';
            $return['error_message'] = '用户被禁用， 请联系管理员';
            return response()->json($return);
        }

        if (!$data['sid'] && empty($data['title'])) {
            $return['status_code'] = "10002";
            $return['error_message'] = "效验参数，缺少参数";
            return response()->json($return);
        }

        if (!$data['sid'] && empty($data['resources']) && empty($data['desc'])) {
            $return['status_code'] = "10003";
            $return['error_message'] = "资源不可为空";
            return response()->json($return);
        }
        if ($data['sid']) {
            //更新
            $res = $this->updateSource($data, $clients);
        } else {
            //新建
            $res = $this->createSource($data, $clients);
        }
        return response()->json($res);
    }

    private function createSource($data, $clients) {
        // 添加料信息， 修改resources表 sid
        try {
            DB::beginTransaction();             //事务开始
            $source['sid'] = Redis::incr('source_id');
            $source['id'] = $source['sid'];
            $source['uid'] = $clients['id'];
            $source['title'] = $data['title'];
            $source['sub_title'] = $data['sub_title'];
            $source['price'] = $data['price'];
            $source['primary_price'] = $data['price'];
            $source['free_watch'] = $data['free_watch'];
            $source['thresh'] = $data['thresh'];
            $source['pack_type'] = $data['pack_type'];
            $source['pack_day'] = $data['pack_day'];
            $source['play_time'] = $data['play_time'] / 1000;
            $source['play_start'] = $data['play_start'];
            $source['form_id'] = $data['form_id'];
            $source['play_end'] = $data['play_end'];
            $currenttime = time();
            $source['createtime'] = date('Y-m-d H:i:s');
            $source['modifytime'] = $source['createtime'];
            $source['section_id'] = $data['section_id'];
            $uuid = Uuid::uuid1();
            $url = 'qrcode/s.' . $source['sid'] . '.' . $uuid->getHex() . '.jpg';
            $source['url'] = $url;
            if ($data['pack_type'] == 1) { // 时段包优先排序
                $source['score'] = '1' . "$currenttime";
            } else {
                $source['score'] = $currenttime;
            }

            if ($clients['is_white'] == 1) {
                //白名单用户
                $source['is_check'] = 1;
                $content['is_check'] = 1;
            }

            DB::table('source')->insert($source);

            // 添加附表
            $source_extra['id'] = $source['id'];
            $source_extra['sid'] = $source['sid'];
            $source_extra['modifiedtime'] = date('Y-m-d H:i:s');
            DB::table('source_extra')->insert($source_extra);

            $content['cid'] = Redis::incr('content_id');
            $content['sid'] = $source['sid'];
            $content['uid'] = $clients['id'];
            $content['description'] = $data['desc'];
            $content['createtime'] = date('Y-m-d H:i:s');
            $content['modifytime'] = $content['createtime'];
            DB::table('contents')->insert($content);

            //用户的发布料的数量加1
            DB::table('client_extra')->where('id', $clients['id'])->increment('publishednum');

            if (!empty($data['resources'])) {
                foreach ($data['resources'] as $key => $value) {
                    $update['cid'] = $content['cid'];
                    $update['sid'] = $source['sid'];
                    $update['sindex'] = $value['index'];
                    DB::table('resource')->where('id', $value['rid'])->update($update);
                }
            }

            // 修改用户身份为卖家
            $this->userRoles($clients['id'], '1');

            DB::commit();                   //事务提交
            $resources = resource::where('cid', $content['cid'])->where('stype', 3)->get();
            // 生成资源图
            $imgdata = [
                'title' => $clients['nickname'],
                'price' => $source['price'],
                'content' => $source['title'],
                'sub_title' => $source['sub_title'],
                'description' => $content['description'],
                'resources' => $resources,
                'avatarUrl' => $clients['avatarurl'],
                'sid' => $source['sid'],
                'uid' => $clients['id'],
                'url' => $url,
                'pack_day' => $source['pack_day'],
                //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
                'pack_type' => $source['pack_type'],
                'wx_scene' => 's.' . $source['sid']
            ];

//			$this->getQrcode($imgdata);
            $this->getNewQrCode($imgdata);

            $return['status_code'] = '200';
            $source['url'] = "https://zy.qiudashi.com/$url";
            unset($source['id']);
            $return['data'] = $source;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10008';
            $return['error_message'] = '创建料失败';
        }
        return $return;
    }

    private function updateSource($data, $clients) {
        try {
            DB::beginTransaction();             //事务开始
            $source = source::where('sid', $data['sid'])->first();
            if (empty($data['desc']) && empty($data['resources']) && empty($data['delayed_day']) && $data['price'] == $source['price']) {
                return $source;
            }

            $ret = array(
                'sid' => $source['sid'],
                //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price'])
                'pack_type' => $source['pack_type']
            );
            $insertData['cid'] = Redis::incr('content_id');
            $insertData['sid'] = $data['sid'];
            $insertData['uid'] = $clients['id'];
            $insertData['description'] = $data['desc'];
            $insertData['createtime'] = date('Y-m-d H:i:s');
            $insertData['modifytime'] = $insertData['createtime'];

            if ($clients['is_white'] == 1) {
                //白名单用户
                $insertData['is_check'] = 1;
            } else {
                DB::table('source')->where('sid', $data['sid'])->update(['is_check' => 0]);

            }


            //resource
            if (!empty($data['resources'])) {
                foreach ($data['resources'] as $key => $value) {
                    $update['cid'] = $insertData['cid'];
                    $update['sid'] = $insertData['sid'];
                    $update['sindex'] = $value['index'];
                    DB::table('resource')->where('id', $value['rid'])->update($update);
                }
            }
            if (!empty($data['desc']) || !empty($data['resources'])) {
                DB::table('contents')->insert($insertData);
            }

            if (!empty($data['delayed_day'] && $data['delayed_day'] != $source['delayed_day'])) {
                DB::table('source')->where('sid', $data['sid'])->update(['delayed_day' => $data['delayed_day']]);
                DB::table('source_update_record')->insert([
                    'rid' => Redis::incr('source_record_id'),
                    'sid' => $data['sid'],
                    'uid' => $clients['id'],
                    'rkey' => 'delayed_day',
                    'rvalue' => $data['delayed_day'],
                    'createtime' => date('Y-m-d H:i:s')
                ]);
            }
            if (!empty($data['price'] && $data['price'] != $source['price'])) {
                $recordId = Redis::incr('source_record_id');
                $uuid = Uuid::uuid1();

                $url = 'qrcode/r.' . $recordId . '.' . $uuid->getHex() . '.jpg';
                $ret['url'] = $url;
                DB::table('source')->where('sid', $data['sid'])->update([
                    'price' => $data['price'],
                    'url' => $url
                ]);
                DB::table('source_update_record')->insertGetId([
                    'rid' => $recordId,
                    'sid' => $data['sid'],
                    'uid' => $clients['id'],
                    'rkey' => 'price',
                    'rvalue' => $data['price'],
                    'createtime' => date('Y-m-d H:i:s')
                ]);
                $ret['rid'] = $recordId;      //返回新旧二维码标识
                $resources = resource::where('cid', $insertData['cid'])->where('stype', 3)->get();
                $imgdata = [
                    'title' => $clients['nickname'],
                    'price' => $data['price'],
                    'content' => $source['title'],
                    'sub_title' => $source['sub_title'],
                    'avatarUrl' => $clients['avatarurl'],
                    'description' => $data['desc'],
                    'resources' => $resources,
                    'sid' => $data['sid'] . '_' . $recordId,
                    'uid' => $clients['id'],
                    'url' => $url,
                    'pack_day' => $source['pack_day'],
                    //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
                    'pack_type' => $source['pack_type'],
                    'wx_scene' => "r.$recordId"
                ];
//				$this->getQrcode($imgdata);
                $this->getNewQrCode($imgdata);
                $ret['url'] = "https://zy.qiudashi.com/$url";
            }

            DB::commit();                   //事务提交

            $return['status_code'] = '200';
            $return['data'] = $ret;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10008';
            $return['error_message'] = '更新料失败';
        }
        return $return;
    }


    /**
     * 创建料
     */
    public function postSourcesAddNew(Request $request) { //yes
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);

        $data['uid'] = $request->input('uid', '');
        $data['title'] = $request->input('title', '');
        $data['sub_title'] = $request->input('sub_title', '');
        $data['price'] = $request->input('price', '0');
        $data['thresh'] = $request->input('thresh', '0');
        $data['free_watch'] = $request->input('free_watch', '0');
        $data['resources'] = $request->input('resources', "");
        if (!is_array($data['resources'])) {
            $data['resources'] = json_decode($data['resources'], true);
        }
        $data['desc'] = $request->input('desc', "");   // 文字资源
        $data['section_id'] = $request->input('section_id', '');
        $data['pack_type'] = $request->input('pack_type', '0');
        //$data['play_time'] = $request->input('date', '0');
        $data['form_id'] = $request->input('formId', '');
        $data['play_start'] = $request->input('date_before', '0');
        $data['play_end'] = $request->input('date_after', '0');
        $data['pack_day'] = $request->input('pack_day', '0');
        $data['delayed_day'] = $request->input('delayed_day', '0');
        $data['sid'] = $request->input('sid', '');
        $data['share_type'] = $request->input('share_type', 1);
        $data['sold_out_time'] = $request->input('sold_out_time', '');
        $data['open_time'] = $request->input('open_time', '');
        //foreach ($data as $k => $item) {
        //    \Log::info($k);
        //    \Log::info($item);
        //}
        //\Log::info($data['sold_out_time']);
        //\Log::info($data['open_time']);

        $errorMsg = '';

        //获取当前支付渠道
        $payment = config('pay.payment');

        if ($data['price'] != 0 && $data['price'] < 1 && $payment == 'hypay') {
            //华移支付  不能低于1元
            $errorMsg = '定价不能低于1元';
        }
        if ($data['price'] > 5000) {
            $errorMsg = '定价不能高于5000元';
        }

        if (mb_strlen($data['sub_title']) > 40) {
            $errorMsg = "副标题过长";
        }

        if ($data['pack_type'] == 1) {
            if ($data['pack_day'] > 90) {
                $errorMsg = '包时段不能超过90天';
            }
//			if ($data['price'] > 3000) {
//				$errorMsg = '时段包定价不能超过3000元';
//			}

        } else {
//			if ($data['price'] > 2000) {
//				$errorMsg = '定价不能超过2000元';
//			}
        }
        if (!empty($errorMsg)) {
            $return['status_code'] = "-10000";
            $return['error_message'] = $errorMsg;
            return response()->json($return);
        }

        if (empty($token) || $clients['id'] != $data['uid']) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $user_status = $this->decAnalysis($clients['status'], 8);
        if ($user_status == 1) {
            $return['status_code'] = '10004';
            $return['error_message'] = '用户被禁用， 请联系管理员';
            return response()->json($return);
        }

        if (!$data['sid'] && empty($data['title'])) {
            $return['status_code'] = "10002";
            $return['error_message'] = "效验参数，缺少参数";
            return response()->json($return);
        }

        if (!$data['sid'] && empty($data['resources']) && empty($data['desc'])) {
            $return['status_code'] = "10003";
            $return['error_message'] = "资源不可为空";
            return response()->json($return);
        }
        if ($data['sid']) {
            //更新
            unset($data['share_type']);
            $res = $this->updateSourceNew($data, $clients);
        } else {
            //新建
            $res = $this->createSourceNew($data, $clients);
        }
        return response()->json($res);
    }

    private function createSourceNew($data, $clients) {//yes
        // 添加料信息， 修改resources表 sid
        try {
            $share_type = $data['share_type'];

            DB::beginTransaction();             //事务开始
            $source['sid'] = Redis::incr('source_id');
            $source['id'] = $source['sid'];
            $source['uid'] = $clients['id'];
            $source['title'] = $data['title'];
            $source['sub_title'] = $data['sub_title'];
            $source['price'] = $data['price'];
            $source['primary_price'] = $data['price'];
            $source['free_watch'] = $data['free_watch'];
            $source['thresh'] = $data['thresh'];
            $source['pack_type'] = $data['pack_type'];
            $source['pack_day'] = $data['pack_day'];
            //$source['play_time'] = $data['play_time'] / 1000;
            $source['play_start'] = $data['play_start'];
            $source['form_id'] = $data['form_id'];
            $source['play_end'] = $data['play_end'];
            $currenttime = time();
            $source['createtime'] = date('Y-m-d H:i:s');
            $source['modifytime'] = $source['createtime'];
            $source['section_id'] = $data['section_id'];
            $source['share_type'] = $data['share_type'];
            $uuid = Uuid::uuid1();
            $url = 'qrcode/s.' . $source['sid'] . '.' . $uuid->getHex() . '.jpg';
            $source['url'] = config('qiniu.host') . '/' . $url;
            if ($data['pack_type'] == 1) { // 时段包优先排序
                $source['score'] = '1' . "$currenttime";
            } else {
                $source['score'] = $currenttime;
            }

            $apply_title = '';
            $apply_sub_title = '';
            if ($clients['is_white'] == 1) {
                //白名单用户
                $filter_data = [];
                $filter_data['title'] = $source['title'];
                $filter_data['sub_title'] = $source['sub_title'];
                $filter_data['content'] = $data['desc'];
                $is_civil = source_sensitives::white_filter($filter_data, 1);
                if ($is_civil === true) {
                    $source['is_check'] = 1;
                    $content['is_check'] = 1;
                } elseif ($is_civil) {
                    $source['is_check'] = 1;
                    $content['is_check'] = 1;
                    $apply_title = $is_civil['title'];
                    $apply_sub_title = $is_civil['sub_title'];
                }
            }

            DB::table('source')->insert($source);

            // 添加附表
            $source_extra['id'] = $source['id'];
            $source_extra['sid'] = $source['sid'];
            $source_extra['modifiedtime'] = date('Y-m-d H:i:s');
            //自动下架
            if ($data['sold_out_time']) {
                $sold_out_time = $data['sold_out_time'] / 1000;
                $source_extra['sold_out_time'] = date('Y-m-d H:i:s', $sold_out_time);
                $source_extra['is_sold_out'] = 1;
            }
            //自动公开
            if ($data['open_time']) {
                $open_time = $data['open_time'] / 1000;
                $source_extra['open_time'] = date('Y-m-d H:i:s', $open_time);
                $source_extra['is_open'] = 1;
            }
            DB::table('source_extra')->insert($source_extra);

            $content['cid'] = Redis::incr('content_id');
            $content['sid'] = $source['sid'];
            $content['uid'] = $clients['id'];
            $content['description'] = $data['desc'];
            $content['createtime'] = date('Y-m-d H:i:s');
            $content['modifytime'] = $content['createtime'];
            DB::table('contents')->insert($content);

            //用户的发布料的数量加1
            DB::table('client_extra')->where('id', $clients['id'])->increment('publishednum');

            if (!empty($data['resources'])) {
                foreach ($data['resources'] as $key => $value) {

                    $uuid1 = Uuid::uuid1();
                    $resource_id = $uuid1->getHex();
                    $update = [];
                    $update['id'] = $resource_id;
                    $update['uid'] = $clients['id'];
                    $update['stype'] = 3;
                    $update['cid'] = $content['cid'];
                    $update['sid'] = $source['sid'];
                    $update['url'] = $value['rid'];
                    $update['sindex'] = $value['index'];
//					DB::table('resource')->where('id', $value['rid'])->update($update);
                    DB::table('resource')->insert($update);
                }
            }

            // 修改用户身份为卖家
            $this->userRoles($clients['id'], '1');

            DB::commit();                   //事务提交
            $resources = resource::where('cid', $content['cid'])->where('stype', 3)->get();
            // 生成资源图
            $imgdata = [
                'title' => $clients['nickname'],
                'price' => $source['price'],
                'content' => $apply_title ?: $source['title'],
                'sub_title' => $apply_sub_title ?: $source['sub_title'],
                'description' => $content['description'],
                'resources' => $resources,
                'avatarUrl' => $clients['avatarurl'],
                'sid' => $source['sid'],
                'uid' => $clients['id'],
                'url' => $url,
                'pack_day' => $source['pack_day'],
                //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
                'pack_type' => $source['pack_type'],
                'wx_scene' => 's.' . $source['sid']
            ];

//			$this->getQrcode($imgdata);
            $this->getNewQrCode($imgdata, $share_type);

            //敏感词
            source_sensitives::push_source($source['id']);
            $return['status_code'] = '200';
            //$source['url'] = "https://zy.qiudashi.com/$url";
            unset($source['id']);
            $return['data'] = $source;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10008';
            $return['error_message'] = '创建料失败';
        }
        return $return;
    }

    public function apply_picture(Request $request) {
        $data = [];
        $data['sid'] = $request->input('sid', 0);
        $data['cid'] = $request->input('cid', 0);
        $data['uid'] = $request->input('uid', 0);
        $source = source::where('sid', $data['sid'])->first();
        $source = $source->toArray();
        $share_type = $source['share_type'];
        $clients = client::where('id', $data['uid'])->first();
        $clients = $clients->toArray();
        $content = contents::where('sid', $data['sid'])->first();
        $content = $content->toArray();
        $uuid = Uuid::uuid1();
        $url = 'qrcode/s.' . $source['sid'] . '.' . $uuid->getHex() . '.jpg';
        $source['url'] = config('qiniu.host') . '/' . $url;
        $resources = resource::where('cid', $content['cid'])->where('stype', 3)->get();
        $resources = $resources->toArray();
        // 生成资源图
        $source =  source_sensitives::apply($source);
        $imgdata = [
            'title' => $clients['nickname'],
            'price' => $source['price'],
            'content' => $source['title'],
            'sub_title' => $source['sub_title'],
            'description' => $content['description'],
            'resources' => $resources,
            'avatarUrl' => $clients['avatarurl'],
            'sid' => $source['sid'],
            'uid' => $clients['id'],
            'url' => $url,
            'pack_day' => $source['pack_day'],
            'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
            'wx_scene' => 's.' . $source['sid']
        ];

        $this->getNewQrCode($imgdata, $share_type);
        return DB::table('source')->where('sid', $data['sid'])->update(['url' => $source['url']]);
    }

    private function updateSourceNew($data, $clients) {//yes
        try {
            DB::beginTransaction();             //事务开始
            $source = source::where('sid', $data['sid'])->first();
            if (empty($data['desc']) && empty($data['resources']) && empty($data['delayed_day']) && $data['price'] == $source['price']) {
                return $source;
            }

            // 判断料的用户和提交的用户是否一致
            if ($source['uid'] != $clients['id']) {
                $return['status_code'] = '-1';
                $return['error_message'] = '您不能修改改料';
                return response()->json($return);
            }

            $ret = array(
                'sid' => $source['sid'],
                //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price'])
                'pack_type' => $source['pack_type']
            );
            $insertData['cid'] = Redis::incr('content_id');
            $insertData['sid'] = $data['sid'];
            $insertData['uid'] = $clients['id'];
            $insertData['description'] = $data['desc'];
            $insertData['createtime'] = date('Y-m-d H:i:s');
            $insertData['modifytime'] = $insertData['createtime'];

            if ($clients['is_white'] == 1) {
                //白名单用户
                $insertData['is_check'] = 1;
                $is_civil = source_sensitives::white_filter(['content' => $insertData['description']], 0);
                if ($is_civil === false) {
                    $insertData['is_check'] = 0;
                    DB::table('source')->where('sid', $data['sid'])->update(['is_check' => 0]);
                }
            } else {
                DB::table('source')->where('sid', $data['sid'])->update(['is_check' => 0]);

            }


            //resource
            if (!empty($data['resources'])) {
                foreach ($data['resources'] as $key => $value) {
//					$update['cid'] = $insertData['cid'];
//					$update['sid'] = $insertData['sid'];
//					$update['sindex'] = $value['index'];
//					DB::table('resource')->where('id', $value['rid'])->update($update);

                    $uuid1 = Uuid::uuid1();
                    $resource_id = $uuid1->getHex();
                    $update = [];
                    $update['id'] = $resource_id;
                    $update['uid'] = $clients['id'];
                    $update['stype'] = 3;
                    $update['cid'] = $insertData['cid'];
                    $update['sid'] = $insertData['sid'];
                    $update['url'] = $value['rid'];
                    $update['sindex'] = $value['index'];
//					DB::table('resource')->where('id', $value['rid'])->update($update);
                    DB::table('resource')->insert($update);


                }
            }
            if (!empty($data['desc']) || !empty($data['resources'])) {
                DB::table('contents')->insert($insertData);
            }

            if (!empty($data['delayed_day'] && $data['delayed_day'] != $source['delayed_day'])) {

                //写redis
                Redis::lpush("pack_source_delayed_notice_list", $data['sid']);

                DB::table('source')->where('sid', $data['sid'])->update(['delayed_day' => $data['delayed_day']]);
                DB::table('source_update_record')->insert([
                    'rid' => Redis::incr('source_record_id'),
                    'sid' => $data['sid'],
                    'uid' => $clients['id'],
                    'rkey' => 'delayed_day',
                    'rvalue' => $data['delayed_day'],
                    'createtime' => date('Y-m-d H:i:s')
                ]);
            }
            if (!empty($data['price'] && $data['price'] != $source['price'])) {
                $recordId = Redis::incr('source_record_id');
                $uuid = Uuid::uuid1();

                $url = 'qrcode/r.' . $recordId . '.' . $uuid->getHex() . '.jpg';
                $ret['url'] = config('qiniu.host') . '/' . $url;
                DB::table('source')->where('sid', $data['sid'])->update([
                    'price' => $data['price'],
                    'url' => config('qiniu.host') . '/' . $url
                ]);
                DB::table('source_update_record')->insertGetId([
                    'rid' => $recordId,
                    'sid' => $data['sid'],
                    'uid' => $clients['id'],
                    'rkey' => 'price',
                    'rvalue' => $data['price'],
                    'createtime' => date('Y-m-d H:i:s')
                ]);
                $ret['rid'] = $recordId;      //返回新旧二维码标识
                $resources = resource::where('cid', $insertData['cid'])->where('stype', 3)->get();
                $imgdata = [
                    'title' => $clients['nickname'],
                    'price' => $data['price'],
                    'content' => $source['title'],
                    'sub_title' => $source['sub_title'],
                    'avatarUrl' => $clients['avatarurl'],
                    'description' => $data['desc'],
                    'resources' => $resources,
                    'sid' => $data['sid'] . '_' . $recordId,
                    'uid' => $clients['id'],
                    'url' => $url,
                    'pack_day' => $source['pack_day'],
                    //'pack_type' => $this->dealPackType5($source['pack_type'], $source['price']),
                    'pack_type' => $source['pack_type'],
                    'wx_scene' => "r.$recordId"
                ];
//				$this->getQrcode($imgdata);
                $this->getNewQrCode($imgdata, $source['share_type']);
                //$ret['url'] = "https://zy.qiudashi.com/$url";
            }

            DB::commit();                   //事务提交

            //写redis
            $redisKey = "pack_source_update_notice_list";
            Redis::lpush($redisKey, $data['sid']);
            source_sensitives::push_source_content($data['sid'], $insertData['cid']);
            Redis::del($this->r_content_prefix . $data['sid']);

            $return['status_code'] = '200';
            $return['data'] = $ret;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10008';
            $return['error_message'] = '更新料失败';
        }
        return $return;
    }

    /**
     * 加入/取消精选列表
     */
    public function updateRecommendList (Request $request, $sid) {
      $token = JWTAuth::getToken();
      $clients = $this->UserInfo($token);
      if (array_key_exists('status_code', $clients)) {
        if ($clients['status_code'] == '401') {
            $return['status_code'] = "10001";
            $return['error_message'] = "用户登录失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
      }

      $source = source::select('sid', 'uid', 'price', 'status', 'pack_type', 'order_status', 'createtime', 'is_recommend')->where('sid', $sid)->first();
      if ($source['uid'] != $clients['id']) {
        return response()->json(['status_code' => 10002, 'error_message' => '不是料的原创作者']);
      }
      if ($source['status'] == 3) {
        return response()->json(['status_code' => 10003, 'error_message' => '料已下架']);
      }

      $updateInfo = array();
      $is_recommend = $request->input('recommend_status', '-1');
      if ($is_recommend != '-1') {
        $updateInfo['is_recommend'] = $is_recommend;
        $updateInfo['recommend_sort'] = $is_recommend ? strtotime($source['createtime']) : 0;
      }

      $sort_status = $request->input('sort_status', '-1');
      if ($sort_status != '-1') {
        //查询最大sort值+1
        $recommend_sort = strtotime($source['createtime']);
        if ($sort_status) {
          $maxSortSource = source::select('sid', 'uid', 'createtime', 'is_recommend', 'recommend_sort')
            ->where('uid', $clients['id'])->where('is_recommend', 1)->where('status', 0)
            ->orderBy('recommend_sort', 'desc')->first();
          $sort_flag = floor($maxSortSource['recommend_sort'] / 10000000000) + 1;
          $recommend_sort = $sort_flag . strtotime($source['createtime']);
        }
        $updateInfo['recommend_sort'] = $recommend_sort;
      }

      DB::table('source')->where('sid', $sid)->update($updateInfo);

      return response()->json(['status_code' => 200]);
    }

    /**
     * 卖家的精选料列表
     */
    public function getRecommendList(Request $request, $uid)
    {
      $token = JWTAuth::getToken();
      $clients = $this->UserInfo($token);
      if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
        $return['status_code'] = 10001;
        $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
        return response()->json($return);
      }

      $suid = $request->input('suid', 0);
      $query = source::select('sid', 'id', 'title', 'sub_title', 'price', 'thresh', 'pack_type', 'pack_day', 'delayed_day', 'order_status', 'play_end', 'play_time', 'createtime', 'status', 'is_check', 'is_recommend', 'recommend_sort', 'free_watch');
      $query->where('uid', $suid)->where('is_recommend', 1)->where('status', 0);
      $query->orderby('recommend_sort', 'desc');
      $list = $query->get()->ToArray();
      $sids = array_column($list, 'sid');

      $buyedSources = order::select('sid')->where('buyerid', $uid)->whereRaw('orderstatus&1')->whereIn('sid', $sids)->orderBy('createtime', 'desc')->get()->ToArray();
      $buyedSources = array_column($buyedSources, 'sid');
      //是否已被拉黑
      $black_info = buyer::where('selledid', $suid)->where('buyerid', $uid)->first();
      $is_black = 1;
      if ($black_info) {
        $is_black = $black_info['status'];
      }

      $not_buyed = array();
      $buyed = array();
      foreach($list as $k => $val) {
          if (!$is_black) {
              break;
          }
        $val = source_sensitives::apply($list[$k]);

        if($val['pack_type'] == 1) {
          $val['is_buyed'] = 0;
          $latelyOrder = order::where('sid', $val['sid'])->where('buyerid', $uid)->whereRaw('orderstatus in (1,3)')->orderBy('createtime', 'desc')->first();
          if (!empty($latelyOrder)) {
            if (strtotime($latelyOrder['createtime']) + ($val['pack_day'] + $val['delayed_day']) * 86400 < time()) {
              $val['is_expired'] = 1;
              $val['expire'] = '你购买的包时段料已过期，请续费';
              $not_buyed[] = $val;
            } else {
              $val['is_buyed'] = 1;
              $val['is_expired'] = 0;
              $buyed[] = $val;
            }
          } else {
            $not_buyed[] = $val;
          }
        } else {
          //if ($val['pack_type'] == 0 && $val['price'] > 0) {
          //  $val['pack_type'] = 5;
          //}
          //比赛一小时后不可买，检查是否可买
          if ($val['pack_type'] == 3) {
            if ($val['play_end'] == 1) {
              if (time() >= $val['play_time'] + 60 * 60) {
                continue;   //不可买
              }
            }
          }
          //设置红黑单的料不允许再次购买
          if ($val['pack_type'] == 2) {
            if ($val['order_status'] != 0) {
              continue;   //不可买
            }
          }

          if (in_array($val['sid'], $buyedSources)) {
            $val['is_buyed'] = 1;
            $buyed[] = $val;
          } else {
            $val['is_buyed'] = 0;
            $not_buyed[] = $val;
          }
        }
        //if ($val['pack_type'] == 0 && $val['price'] > 0) {
        //    $list[$k]['pack_type'] = 5;
        //}
      }
      $res = array_merge($not_buyed, $buyed);
      return response()->json(['status_code' => 200, 'data' => $res]);
    }

    /**
     * 删除料（未销售与免费的料可以删除）(伪删除)
     */
    public function deleteSource(Request $request, $sid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        // 判断是否为料作者
        $sources = source::select('source.sid', 'uid', 'price', 'soldnumber', 'status', 'pack_type', 'order_status')->LeftJoin('source_extra', 'source_extra.sid', 'source.sid')->where('source.sid', $sid)->first();
        if ($sources['uid'] != $clients['id']) {
            $return['status_code'] = '10002';
            $return['error_message'] = '不是料的原创作者';
            return response()->json($return);
        }

        // 判断是否为免费或者销售为零
        if ($sources['price'] == 0 || $sources['soldnumber'] == 0) {
            source::where('sid', $sid)->update(['status' => '1']);
        } else {
            $return['status_code'] = '10003';
            $return['error_message'] = '仅能删除未销售与免费的料';
            return response()->json($return);
        }

        //$status = decbin($sources['status']);
        //$oldStatus = sprintf('%08d', $status);
        //$newStatus = substr_replace($oldStatus, 1, -2, 1);
        $newStatusChange = 2;

        $upData = [];
        if ($sources['pack_type'] == 2 && $sources['order_status'] == 0) {
            $upData['order_status'] = 2;
        }
        $upData['status'] = $newStatusChange;

        source::where('sid', $sid)->update($upData);
//        source::where('sid', $sid)->update(['status' => $newStatusChange]);

        $return['status_code'] = '200';
        return response()->json($return);
    }


    /**
     * 下架料
     */
    public function putSourceOffshelf(Request $request, $sid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $enbale = $request->input('enable', '1');

        // 判断是否为料作者
        $sources = source::where('source.sid', $sid)->first();
        $del = $this->decAnalysis($sources['status'], 2);
        if ($del == 1 && $sources['status'] != 3) {
            $return['status_code'] = '10003';
            $return['error_message'] = '料已被删除';
            return response()->json($return);
        }
        $shelf = $this->decAnalysis($sources['status'], 4);
        if ($shelf == 1) {
            $return['status_code'] = '10004';
            $return['error_message'] = '料已被系统下架';
            return response()->json($return);
        }

        if ($sources['uid'] != $clients['id']) {
            $return['status_code'] = '10002';
            $return['error_message'] = '不是料的原创作者';
            return response()->json($return);
        }
        $status = $sources['status'];
        //$oldStatus = sprintf('%08d', $status);
        //$newStatus = substr_replace($oldStatus, $enbale, -3, 1);
        //$newStatusChange = bindec((int)$newStatus);
        $newStatusChange = 3;
        if ($status == 3) {
            $newStatusChange = 0;
        }
        $update_data = [];
        $update_data['is_recommend'] = 0;
        $update_data['status'] = $newStatusChange;

        source::where('sid', $sid)->update($update_data);

        $return['status_code'] = '200';

        return response()->json($return);
    }

    /**
     * 内容公开
     */
    public function openSource(Request $request, $sid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $enbale = $request->input('enable', '1');

        // 判断是否为料作者
        $sources = source::where('source.sid', $sid)->first();
        $del = $this->decAnalysis($sources['status'], 2);
        if ($del == 1 && $sources['status'] != 3) {
            $return['status_code'] = '10003';
            $return['error_message'] = '料已被删除';
            return response()->json($return);
        }
        $shelf = $this->decAnalysis($sources['status'], 4);
        if ($shelf == 1) {
            $return['status_code'] = '10004';
            $return['error_message'] = '料已被系统下架';
            return response()->json($return);
        }

        if ($sources['uid'] != $clients['id']) {
            $return['status_code'] = '10002';
            $return['error_message'] = '不是料的原创作者';
            return response()->json($return);
        }
        //if ($sources['status'] == 3) {
        //    $return['status_code'] = '10005';
        //    $return['error_message'] = '料已被下架';
        //    return response()->json($return);
        //}
        $free_watch = $sources['free_watch'];
        //$oldStatus = sprintf('%08d', $status);
        //$newStatus = substr_replace($oldStatus, $enbale, -3, 1);
        //$newStatusChange = bindec((int)$newStatus);
        $new_free_watch = 1;
        if ($free_watch == 1) {
            $new_free_watch = 0;
        }

        source::where('sid', $sid)->update(['free_watch' => $new_free_watch]);

        $return['status_code'] = '200';

        return response()->json($return);
    }


    /**
     * 给料详情页接口
     */
    public function getSourceDetails(Request $request, $sid) {
        $sorts = [
            '0' => 'desc',
            '1' => 'asc',
            '2' => 'desc'
        ];
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $lastid = $request->input('lastid', '-1');
        $page = $request->input('page', 0);
        $numberpage = $request->input('numberpage', 50);
        $sort = $request->input('sort', 0);
        $offset = $page * $numberpage;

        $order = order::select('order.id', 'buyerid', 'ordernum', 'price', 'order.createtime', 'order.modifytime');
        $order->where('sid', $sid);
        //$order->whereRaw("substring(bin(orderstatus), -1, 1) = 1");
        $order->whereRaw("orderstatus & 1");
        $order->orderBy('order.createtime', $sorts[$sort]);
        if ($lastid == '-1') {
            $order->offset($offset);
        }
        if ($lastid != '-1' && !empty($lastid)) {
            if ($sort != 2) { // desc
                $order->where('order.createtime', '<', $lastid);
            } else {
                $order->where('order.createtime', '>', $lastid);
            }
        }
        $order->limit($numberpage);
        $data = $order->get();

        foreach ($data as $key => $value) {
            $userInfo = client::select('nickname', 'avatarurl')->where('id', $value['buyerid'])->first();
            $data[$key]['nickname'] = $userInfo['nickname'];
            $data[$key]['avatarurl'] = $userInfo['avatarurl'];
        }

        $balance0 = order::where('sid', $sid)->whereRaw('orderstatus & 1')->sum('price');

        $balance = 0;

        $count = 0;
        if ($lastid == '-1') {
            $order = order::select();
            $order->where('sid', $sid);
            //$order->whereRaw("substring(bin(orderstatus), -1, 1) = 1");
            $order->whereRaw("orderstatus & 1");
            $count = $order->count();
        }

        if ($count == 0) {
            $pagenum = 0;
        } else {
            $pagenum = ceil($count / $numberpage);
        }

        foreach ($data as $key => $value) {
            $balance += $value['price'];
        }
        $source_extra = source_extra::where('sid', $sid)->first();
        $source = source::where('sid', $sid)->first();

        $return['status_code'] = '200';
        $return['balance'] = $balance0;
        //$return['soldnum'] = $source_extra['soldnumber'];
        $return['soldnum'] = $count;
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        //$return['url'] = $this->processSourceUrl($source);
        $return['url'] = (strpos($source['url'], 'https') === false) ? $this->processSourceUrl($source) : $source['url'];
	$return['data'] = $data->ToArray();

        return response()->json($return);

    }

    public function h5SourceDetails(Request $request, $sid) {
        $sorts = [
            '0' => 'desc',
            '1' => 'asc',
            '2' => 'desc'
        ];
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
            }
        }
        $lastid = $request->input('lastid', '-1');
        $page = $request->input('page', 0);
        $numberpage = $request->input('numberpage', 50);
        $sort = $request->input('sort', 0);
        $offset = $page * $numberpage;

        $order = order::select('order.id', 'nickname', 'avatarurl', 'ordernum', 'price', 'order.createtime', 'order.modifytime');
        $order->LeftJoin('client', 'client.id', 'order.buyerid');
        $order->where('sid', $sid);
        $order->whereRaw("orderstatus & 1");
        $order->orderBy('order.createtime', $sorts[$sort]);
        if ($lastid == '-1') {
            $order->offset($offset);
        }
        if ($lastid != '-1' && !empty($lastid)) {
            if ($sort != 2) { // desc
                $order->where('order.createtime', '<', $lastid);
            } else {
                $order->where('order.createtime', '>', $lastid);
            }
        }
        $order->limit($numberpage);
        $data = $order->get();

        $return['status_code'] = '200';
        $return['data'] = $data->ToArray();

        return response()->json($return);
    }


    /**
     * 根据资源信息生成二维码添加至文件服务器 https://zy.qiudashi.com/qrcode/***.jpg
     * @param array $data
     * @param array $data ['title'] 用户名称
     * @param array $data ['price'] 金额
     * @param array $data ['content'] 标题
     * @param array $data ['avatarUrl'] 用户头像
     * @param array $data ['sid'] 资源(sources) id 用于保存二维码资源名称
     *
     * @return array $return['status'] 200 成功
     */
    public function getImageQrcode($data) {
//        $data = [
//            'title' => 'asdf',
//            'price' => '50',
//            'content' => 'test',
//            'avatarUrl' => "https://wx.qlogo.cn/mmopen/vi_32/Q0j4TwGTfTLswSuauA37L4cp6vVcCL1hia8dxt6CT2aywXNnHazJZbCI2juI7yJialnQYdcNofYSm8ptWjMPkuWg/0",
//            'sid' => 'e9b09cb0cf3211e79bb200ff61e7d73e',
//            'uid' => '35d0b1a6cab611e7bee800ff61e7d73e',
//        ];

        $title = $data['title'];    // 名称
        $money = $data['price'];    // 金额
        $content = $data['content'];  // 名称

        // 背景图片
        $img = Image::make('image/ground.jpg');

        // 将用户头像转换成圆形
        $avatar = $this->circular($data['avatarUrl']);  // 头像

        $avatar = Image::make($avatar);

        $img->insert($avatar, 'bottom-top', 318, 36, function ($top) {
            $top->align("center");
        });

        // 查看是否有emoji并返回位置以及unified码
        $position = $this->have_emoji($title);

        // 将昵称字符串中emoji替换为空格
        $text = $this->str_emoji_empty($title);

        // 用户昵称
        $img->text($text, 375, 210, function ($font) {
            $font->file('ht.ttf');
            $font->size(24);
            $font->color("#363636");
            $font->align("center");
        });

        if (!empty($position)) {
            foreach ($position as $key => $value) {
                $emoji_img_address = 'image/emoji/' . $value['str'] . '.png';
                if (file_exists($emoji_img_address)) {
                    $emoji_img = Image::make($emoji_img_address)->resize(24, 24);
                    $img->insert($emoji_img, "bottom-top", (750 - (24 * mb_strlen($title))) / 2 + (24 * ($value['num'])), 175);
                }
            }
        }

        $ground_len = round((mb_strlen($content) * 35) + 40);
        $top_len = round(375 - ($ground_len / 2));
        if ($ground_len < 250) {
            $ground_len = 250;
            $top_len = 250;
        }
        if ($ground_len > 670) {
            $top_len = 40;
            $ground_len = 670;
        }

        // 昵称背景
        $ground = Image::canvas($ground_len, 74, '#fe4426');
        $img->insert($ground, "bottom-top", $top_len, 250);

        // 资源名称
        $img->text($content, 375, 300, function ($font) {
            $font->file('ht.ttf');
            $font->size(30);
            $font->color("#fefefe");
            $font->align("center");
        });

        $money_len = strlen($money);
        if ($money_len == 3) {
            $unit = "296";
            $sum = "255";
        } else if ($money_len == 2) {
            $unit = "274";
            $sum = "233";
        } else if ($money_len == 1) {
            $unit = "256";
            $sum = "200";
        } else if ($money_len == 4) {
            $unit = "319";
            $sum = "278";
        } else if ($money_len == 5) {
            $unit = "340";
            $sum = "299";
        } else {
            $unit = "296";
            $sum = "255";
        }

        $day = $data['pack_day'];
        if ($day > 0) {
            if ($day == 7) {
                $day = '周';
            } else if ($day == 30) {
                $day = '月';
            } else {
                $day .= '天';
            }
            $img->text('【包' . $day . '】', 210, 415, function ($font) {
                $font->file('ht.ttf');
                $font->size(36);
                $font->color("#fe4426");
                $font->align("center");
            });
        }

        if ($money != 0) {
            // 金额单位
            $img->text('元', $unit, 510, function ($font) {
                $font->file('ht.ttf');
                $font->size(30);
                $font->color("#363636");
                $font->align("right");
            });

            // 金额
            $img->text($money, $sum, 526, function ($font) {
                $font->file('ht.ttf');
                $font->size(80);
                $font->color("#fe4426");
                $font->align("right");
            });
        } else {
            $img->text("免费", 260, 526, function ($font) {
                $font->file('ht.ttf');
                $font->size(60);
                $font->color("#fe4426");
                $font->align("right");
            });
        }

        // 二维码添加
        $qrcode = $this->qrcode($data['wx_scene'], $data['uid']);  // 二维码图片获取， $data 参数需添加 path , width
        $qrcode = Image::make($qrcode);
        $img->insert($qrcode, "bottom-top", 424, 378);

        $img->save($data['url']);
        $objUfile = new Ufile();
        $bucket = "qiudashizy";
        $re = $objUfile->put($bucket, $data['url'], $data['url']);
        if ($re) {
            $return['status'] = 200;
        } else {
            $return['status'] = 400;
        }
        return $return;
    }

    public function getQrcode($data) {
        $nickname = $data['title'];    // 昵称
        $sub_title = $data['sub_title'];    // 昵称
        $money = $data['price'];    // 金额
        $content = $data['content'];  // 名称
        $description = $data['description'];
        if ($data['resources'] && count($data['resources']) > 0) {
            $resource = $data['resources'][0]['url'];
        }

        // 背景图片
        $img = Image::make('image/bg.jpg');
        $downHeight = 0;
        $day = $data['pack_day'];
        if ($day > 0) {
            if ($day == 7) {
                $day = '周';
            } else if ($day == 30) {
                $day = '月';
            } else {
                $day .= '天';
            }
            $img->text('【包' . $day . '】', 375, 125, function ($font) {
                $font->file('ht.ttf');
                $font->size(36);
                $font->color("#ffffff");
                $font->align("center");
            });
            $downHeight = 30;
        }
        if ($data['pack_type'] == 2) {
            $img->text('【不中退款】', 375, 125, function ($font) {
                $font->file('ht.ttf');
                $font->size(36);
                $font->color("#ffffff");
                $font->align("center");
            });
            $downHeight = 30;

        }
        if ($data['pack_type'] == 3) {
            $img->text('【限时料】', 375, 125, function ($font) {
                $font->file('ht.ttf');
                $font->size(36);
                $font->color("#ffffff");
                $font->align("center");
            });
            $downHeight = 30;
        }
        // 资源
        $img->text($content, 375, 145 + $downHeight, function ($font) {
            $font->file('ht.ttf');
            $font->size(36);
            $font->color("#ffffff");
            $font->align("center");
        });

        $downHeight = $downHeight + 50;
        // 副标题
        if (!empty($sub_title)) {

            $img->text($sub_title, 375, 150 + $downHeight, function ($font) {
                $font->file('ht.ttf');
                $font->size(28);
                $font->color("#ffffff");
                $font->align("center");
            });
        }

        //=============模糊==============

        $blurImg = Image::canvas(650, 324, '#dd3317');
        $resourceHeight = 0;
        if (!empty($description)) {
            $wrap = $this->autowrap($description, 40);
            foreach ($wrap as $line) {
                $blurImg->text($line, 20, 30 + $resourceHeight, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(30);
                    $font->color("#ffffff");
                });
                $resourceHeight += 50;
            }
        }
        if (isset($resource) && !empty($resource)) {
            $resourceImg = Image::make($resource)->widen(500, function ($constraint) {
                $constraint->upsize();
            })->blur(40);
            $blurImg->insert($resourceImg, 'top', 325, $resourceHeight + 20, function ($res) {
                $res->align("center");
            });
        }
        $blurImg->blur(20);
        $img->insert($blurImg, 'top', 375, 176 + $downHeight, function ($res) {
            $res->align("center");
        });
        $img->insert('image/down.png', 'bottom-left', 0, 0);
        $avatar = $this->circular($data['avatarUrl']);
        $avatar = Image::make($avatar);
        $img->insert($avatar, 'bottom-left', 92, 68, function ($top) {
            $top->align("center");
        });
        $qrcode = $this->generateQrcode($data['wx_scene'], $data['uid']);
        $qrcode = Image::make($qrcode)->widen(150, function ($constraint) {
            $constraint->upsize();
        });
        $qrBg = new Imagick();
        $qrBg->newImage(170, 170, new ImagickPixel('transparent'));
        $qrBg->setImageFormat('png');
        // $qrBg->roundCorners(4, 4);

        $draw = new ImagickDraw();
        $draw->setStrokeColor('#ffffff');
        $draw->setFillColor('#ffffff');
        $draw->roundRectangle(0, 0, 170, 170, 10, 10);
        $qrBg->drawImage($draw);

        $qrBg = Image::make($qrBg);
        $qrBg->insert($qrcode, 'top-left', 10, 10);
        $img->insert($qrBg, 'bottom-left', 290, 220);
        $img->text('长按扫码 立即获取', 290, 590, function ($font) {
            $font->file('ht.ttf');
            $font->size(20);
            $font->color('#333333');
        });
        $nickname = rtrim($nickname);
        //if(mb_strlen($nickname)<8){
        //$this->handleNickName($img,$nickname,673);
        //}else{
        $nickArr = [];
        $nick_len = strlen($nickname);
        while (true) {
            $nickArr[] = mb_substr($nickname, 0, 8);
            $nickname = mb_substr($nickname, 8);
            if (mb_strlen($nickname) == 0) {
                break;
            }
        }
        $nickHeight = 653;
        foreach ($nickArr as $nickStr) {
            $nickHeight = $this->handleNickName($img, $nickStr, $nickHeight, 653);
        }
        //}
        /*$position = $this->have_emoji($nickname);
            $nick = $this->str_emoji_empty($nickname);
        $img->text($nick,200,673,function($font){
                    $font->file('ht.ttf');
                    $font->size(28);
                    $font->color('#ffffff');
            });
        if (!empty($position)) {
                foreach ($position as $key => $value) {
                    $emoji_img_address = 'image/emoji/' . $value['str'] . '.png';
                    if (file_exists($emoji_img_address)) {
                        $emoji_img = Image::make($emoji_img_address)->resize(28, 28);
                        $img->insert($emoji_img, "top-left", (200 + (28 * ($value['num']))), 648);
                    }
                }
            }*/
        if ($money > 0) {
            $img->text('元', 640, 675, function ($font) {
                $font->file('ht.ttf');
                $font->size(30);
                $font->color('#000000');
                $font->align('right');
                $font->valign('bottom');
            });
            $img->text($money, 600, 690, function ($font) {
                $font->file('ht.ttf');
                $font->size(80);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('bottom');
            });
        } else {
            $img->text('免费', 620, 690, function ($font) {
                $font->file('ht.ttf');
                $font->size(80);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('bottom');
            });
        }
        $basePath = $this->checkPathExist();
        $onlinePathInfo = explode("/", $data['url']);
        $localPath = $basePath . "/" . $onlinePathInfo[1];


//	$img->save($data['url']);
        $img->save($localPath);
        $objUfile = new Ufile();
        $bucket = "qiudashizy";
        $re = $objUfile->put($bucket, $data['url'], $localPath);
        if ($re) {
            $return['status'] = 200;
        } else {
            $return['status'] = 400;
        }
        return $return;
    }

    /**
     * 检查今日目录是否存在
     */
    public function checkPathExist() {
        $time = date("Ymd", time());
        $pathString = public_path() . "/newqrcode/" . $time;
        if (!is_dir($pathString)) {
            mkdir($pathString, 0777, true);
        }
        return $pathString;
    }

    private function autowrap($str, $width, $break = "\n") {
        $arr = [];
        $brstr = nl2br($str);
        $brArr = explode("<br />", $brstr);
        foreach ($brArr as $brline) {
            $brline = str_replace(array(
                "\r\n",
                "\r",
                "\n"
            ), "", $brline);
            $strlen = mb_strlen($brline, 'UTF-8');
            $currentLine = '';
            $currentLen = 0;
            $pos = 0;
            while (true) {
                if ($currentLen >= $width) {
                    $arr[] = $currentLine;
                    $currentLine = '';
                    $currentLen = 0;
                }
                $char = mb_substr($brline, $pos, 1, 'UTF-8');
                $pos++;
                $currentLen++;
                if (strlen($char) > 1) {
                    $currentLen++;
                }
                $currentLine .= $char;
                if ($pos >= $strlen) {
                    $arr[] = $currentLine;
                    break;
                }
            }
        }
        return $arr;
    }

    /**
     * 将昵称写入二维码图
     * @param $img
     * @param $nickname
     * @param $height
     * @param $startHeight
     * @return int
     */
    private function handleNickName($img, $nickname, $height, $startHeight) {
        $position = $this->have_emoji($nickname);
        $nick = $this->str_emoji_empty($nickname);
        $nickLen0 = mb_strlen(rtrim($nickname));
        $nickLen = mb_strlen(rtrim($nick));
        if (($nickLen0 < 8 || !empty($position) && $nickLen < 9) && $startHeight == $height) {
            $height = $height + 26;
        }
        $img->text($nick, 120 + 77 + 10, $height, function ($font) {
            $font->file('ht.ttf');
            $font->size(26);
            $font->color('#ffffff');
        });
        if (!empty($position)) {
            foreach ($position as $key => $value) {
                $emoji_img_address = 'image/emoji/' . $value['str'] . '.png';
                if (file_exists($emoji_img_address)) {
                    $emoji_img = Image::make($emoji_img_address)->resize(24, 24);
                    $img->insert($emoji_img, "top-left", (200 + (32 * ($value['num']))), $height - 22);
                }
            }
        }
        $height += 33;
        return $height;
    }

    /**
     * emoji 图片处理
     * 获取emoji在图片中的位置，以及emoji unified码
     */
    public function have_emoji($str) {
        $emoji = new \Emoji();
//        echo $emoji->emoji_unified_to_html($str);
        $return = [];
        $mat = [];
        preg_match_all('/./u', $str, $mat);
        foreach ($mat[0] as $k => $v) {
            if (strlen($v) > 3) {
                $demo['str'] = $emoji->emoji_unified_to_html($v);
                $demo['num'] = $k;
                array_push($return, $demo);
            }
        }
        return $return;
    }


    /**
     * 把emoji在字符中的位置替换为空格
     */
    public function str_emoji_empty($str) {
        $str = preg_replace_callback('/./u', function (array $match) {
            return strlen($match[0]) >= 4 ? '  ' : $match[0];
        }, $str);
        return $str;
    }


    /**
     * Imagick 图像替换
     */
    public function circular($avatar) {
        //$img = file_get_contents($avatar);
        $avatar = $this->curlImg($avatar);
        if (!$avatar || empty($avatar)) {
            $avatar = file_get_contents('image/avatar.jpg');
        }
        header("Content-Type: image/jpeg");
        $image = new Imagick();
        $image->readImageBlob($avatar);
        $image->thumbnailImage(94, 94, true);
        $image->setImageFormat('png');
        // $image->roundCorners($image->getImageWidth() / 2, $image->getImageHeight() / 2);

        $mask = $this->circularMask(94, 94, $image->getImageWidth() / 2, $image->getImageHeight() / 2);

        // apply mask
        $image->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

        return $image;
    }

    public function circularMask($width, $height, $xRound, $yRound) {
        // example values
        // create mask image
        $mask = new Imagick();
        $mask->newImage($width, $height, new ImagickPixel('transparent'), 'png');
        // create the rounded rectangle
        $shape = new ImagickDraw();
        $shape->setFillColor(new ImagickPixel('black'));
        $shape->roundRectangle(0, 0, $width, $height, $xRound, $yRound);
        // draw the rectangle
        $mask->drawImage($shape);

        return $mask;
    }

    public function curlImg($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $info = curl_exec($ch);
        curl_close($ch);
        return $info;
    }

    /**
     * 用户头像切换成圆形返回（未使用）
     *
     */
    public function yuan_img($avatarUrl) {
        // 获取头像图片编辑为画布
        $starttime = explode(' ', microtime());

        $img = Image::make($avatarUrl);

        // 创建一个新画布
        $new = Image::canvas(122, 122);

        $r = $img->width() / 2;

        for ($x = 0; $x < $img->width(); $x++) {
            for ($y = 0; $y < $img->height(); $y++) {
                if (((($x - $r) * ($x - $r) + ($y - $r) * ($y - $r)) < ($r * $r))) {
                    $c = $img->pickColor($x, $y, 'array');    // 获取颜色
                    $new->pixel($c, $x, $y);  // 对空白图片进行上色
                }
            }
        }

        return $new;
    }


    /**
     * 微信小程序二维码生成
     *
     * @param string $sid 资源id
     * @param string $uid 用户id
     * @return Image
     */
    public function qrcode($scene, $uid) {
        // 获取微信access_token
        $access_token = $this->access_token();

        // 使用access_token拼接链接进行请求
        //$action_url = "https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token={$access_token}";
        $action_url = "https://api.weixin.qq.com/wxa/getwxacode?access_token={$access_token}";
        //$data['page'] = "pages/index/index";
        $data['path'] = "pages/index/index";
        $data['width'] = '235';
        //$data['scene'] = $scene;
        $data = json_encode($data);
        $opts = array(
            'http' => array(
                'method' => 'POST',
                'header' => "Content-Type: application/json",
                'content' => $data,
            )
        );

        $content = stream_context_create($opts);
        $result = file_get_contents($action_url, false, $content);
//        $width = 230;
//        $height = 240;
        $img = Image::make($result);
        return $img;
    }

    public function generateQrcode($scene, $uid) {
        //测试地址
//        $url = 'https://mtest.qiudashi.com/pay/payment?scene='.$scene.'&uid='.$uid;
        //正式地址
        //$domain = 'https://glm5.qiudashi.com';
        //$uid = (int)$uid;
        $new_sellers = config('constants.new_sellers');
        //$new_sellers = [1011];
        $url_prefix = config('constants.tbd_domain1');
        if (in_array($uid, $new_sellers)) {
                $url_prefix = config('constants.tbd_domain_new');
        }
        \Log::info('czh_prefix' . $url_prefix);
        $url = $url_prefix . '?sid=' . $scene . '&uid=' . $uid;
        //$url = $domain . '/pay/payment?scene=' . $scene . '&uid=' . $uid;
        $qrCode = new \Endroid\QrCode\QrCode($url);
        $qrCode->setSize(200);
        $qrCode->setWriterByName('png');
        if ($uid > 9999) {
                $qrCode->setMargin(10);
        } else {
                $qrCode->setMargin(0);
        }
        $qrCode->setEncoding('UTF-8');
        //$qrCode->setErrorCorrectionLevel('low');
        $qrCode->setForegroundColor([
            'r' => 0,
            'g' => 0,
            'b' => 0,
            'a' => 0
        ]);
        $qrCode->setBackgroundColor([
            'r' => 255,
            'g' => 255,
            'b' => 255,
            'a' => 0
        ]);
        //$qrCode->setLogoPath('image/logo.jpg');
        $qrCode->setLogoWidth(30);
        $qrCode->setRoundBlockSize(true);
        $qrCode->setValidateResult(false);

        header('Content-Type: ' . $qrCode->getContentType());
        //$qrCode->writeFile('qrcode_czh.png');
        return $qrCode->writeString();
        //return $img;

    }


    /**
     * 微信access_token 添加文件缓存。
     *
     */
    public function access_token() {
//        $re = Redis::exists("access_token");
//        if ($re) {
//            return Redis::get("access_token");
//        } else {
        $appid = config("wxxcx.appid");
        $appsecret = config("wxxcx.secret");
        $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
        $outopt = file_get_contents($action_url);
        $data = json_decode($outopt, True);
//        Redis::set("access_token", $data['access_token'], "7100");
        return $data['access_token'];
//        }
    }

    private function sourceIsExpire($orderId) {
        $order = DB::table('order')->where('id', $orderId)->first();
        $source = DB::table('source')->where('sid', $order->sid)->first();
        $packDay = $source->pack_day + $source->delayed_day;
        $packEndTime = date('Y-m-d H:i:s', strtotime('+' . $packDay . ' day'));
        if (strtotime($packEndTime) < time()) {
            //已经过期
            return true;
        } else {
            return false;
        }
    }

    private function checkOrderExpired($order, $source) {
        if ($source['pack_type'] == 0 || $source['pack_type'] == 2 || $source['pack_type'] == 3) {
            return false;
        }
        $packDay = $source['pack_day'] + $source['delayed_day'];
        $startTime = strtotime($order['createtime']);
        if ($startTime < 1483228801) { // 2017-01-01 00:00:01
            return true;
        }
        $endTime = $startTime + $packDay * 86400;
        return time() >= $endTime;
    }

    public function getSourceList4Dev(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
        }
        $orderQuery = order::select('sid');
        $orderQuery->where('buyerid', $uid);
        $orderQuery->whereRaw('orderstatus & 1');
        $orderQuery->limit(50);
        $orderQuery->orderby('score', 'desc');
        $mysids = $orderQuery->get()->toArray();
        $sids = [];
        foreach ($mysids as $sid) {
            $sids[] = $sid['sid'];
        }

        $sourceQuery = source::select('source.sid', 'source.title', 'source.createtime', 'source.pack_day', 'source.pack_type');
        if (!empty($sids)) {
            $sourceQuery->whereRaw("sid not in (" . implode(",", $sids) . ")");
        }
        $sourceQuery->whereRaw('uid != ' . $uid);
        $sourceQuery->orderby('score', 'desc');
        $sourceQuery->limit(50);

        return response()->json(array(
            'status_code' => '200',
            'data' => $sourceQuery->get()->ToArray()
        ));
    }


    /**
     * 检测用户是否有红黑单未处理
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkBet(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($clients['openid']) || $clients['openid'] != $clients['serviceid']) {
            return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
        }
        if (empty($token) || $clients['id'] != $uid) {
            return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
        }

        // 获取用户未处理红黑单
        $bet = source::where('uid', $uid)->where('pack_type', 2)->where('order_status', 0)->get()->toArray();

        $is_bet = 0;

        foreach ($bet AS $key => $val) {
            if ($val['play_time'] != 0 && ($val['play_time']) < time() - 24 * 3600) {
                $is_bet = 1;
            }
            if ($val['play_time'] == 0 && strtotime($val['createtime']) < time() - 24 * 3600) {
                $is_bet = 1;
            }
        }

        $res = [
            'status_code' => 200,
            'data' => ['is_bet' => $is_bet]
        ];
        return response()->json($res);
    }


    /**
     * 通知粉丝
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendNotice(Request $request, $sid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        // 判断是否为料作者
        $sources = source::select('sid', 'uid', 'is_notice')->where('sid', $sid)->first();

        if ($sources['uid'] != $clients['id']) {
            $return['status_code'] = '10002';
            $return['error_message'] = '不是料的原创作者';
            return response()->json($return);
        }

        if ($sources['is_notice'] == 1) {
            $return['status_code'] = '10003';
            $return['error_message'] = '您已经通知过粉丝了';
            return response()->json($return);
        }

        //写通知次数redis
        $noticeTimesKey = "source_notice_times_list";
        $noticeTimes = Redis::zscore($noticeTimesKey, $sources['uid']);
        if (intval($noticeTimes) <= 0) {
            $times = 1;
        } else {
            $times = intval($noticeTimes) + 1;
        }
        //每日只允许通知50次
        if ($times > 10) {
            $return['status_code'] = '1001';
            $return['error_message'] = '超过每日通知上限';
            return response()->json($return);
        }
        //写redis
        $redisKey = "source_notice_list";
        Redis::lpush($redisKey, $sid);
        Redis::zadd($noticeTimesKey, $times, $sources['uid']);
        Redis::expireat($noticeTimesKey, strtotime(date("Y-m-d 23:59:59", time())));

        source::where('sid', $sid)->update(['is_notice' => 1]);

        $return['status_code'] = '200';
        $return['error_message'] = '通知粉丝成功';
        return response()->json($return);

    }


    /**
     * 通知次数
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function noticeTime(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists('status_code', $clients)) {
            if ($clients['status_code'] == '401') {
                $return['status_code'] = "10001";
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $noticeTimesKey = "source_notice_times_list";

        $noticeTimes = Redis::zscore($noticeTimesKey, $uid);
        $return['status_code'] = '200';
        $return['data']['times'] = intval($noticeTimes);
        return response()->json($return);
    }

    /**
     * 检查当前是否可以免费看
     * @param $sid
     * @return int
     */
    private function checkSourceFreeWatch($sid) {
        $selectQuery = source::select('sid', 'pack_type', 'pack_day', 'delayed_day', 'title', 'status', 'price', 'order_status', 'primary_price', 'thresh', 'play_time', 'play_end', 'free_watch', 'uid', 'createtime', 'url', 'is_check');
        $data = $selectQuery->where('sid', $sid)->first();
        //正常料和不对返还内容公开
        if ($data['pack_type'] == 0 || $data['pack_type'] == 2) {
            if ($data['free_watch'] == 1 && $data['status'] == 0) {
                return 1;
            }
        }
        //非不中退款料和限时料不允许免费看
        if ($data['pack_type'] != 2 && $data['pack_type'] != 3) {
            return 0;
        }
        //else {
        //    if ($data['free_watch'] == 1 && time() >= $data['play_time'] + 3 * 3600) {
        //        return 1;
        //    } else {
        //        return 0;
        //    }
        //}
    }


    /**
     * 料内容修改接口
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function editSourceContent(Request $request, $uid) {//yes
        $data['resources'] = $request->input('resources', "");
        $data['oldResources'] = $request->input('oldResources', "");
        if (!is_array($data['resources'])) {
            $data['resources'] = json_decode($data['resources'], true);
        }
        if (!is_array($data['oldResources'])) {
            $data['oldResources'] = json_decode($data['oldResources'], true);
        }
        $data['desc'] = $request->input('desc', "");   // 文字资源
	    $data['desc'] = ($data['desc'] == 'undefined') ? '' : $data['desc'];
        $sid = $request->input("sid", "");
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            return $this->errorReturn(10001, "token 失效或异常， 以正常渠道获取重试");
        }
        if (empty($sid)) {
            return $this->errorReturn('10002', "效验参数，缺少参数");
        }

        $source = source::where('sid', $sid)->first();

        //非发料人
        if ($source['uid'] != $uid) {
            return response()->json([
                'status_code' => '10005',
                'error_message' => '非本人料，不允许修改'
            ]);
        }
        try {
            DB::beginTransaction();             //事务开始

            $sourceUpdate['is_check'] = 0;
            $insertData['is_check'] = 0;
            $insertData['cid'] = Redis::incr('content_id');
            if ($clients['is_white'] == 1) {
                $is_civil = source_sensitives::white_filter(['content' => $data['desc']], 0);
                //白名单用户
                if ($is_civil === true) {
                    $sourceUpdate['is_check'] = 1;
                    $insertData['is_check'] = 1;
                    $this->sendNoticeMsg($sid);
                }
            }

            //原有内容置位修改
            $contentUpdate['edit'] = 1;
            DB::table('contents')->where('sid', $sid)->update($contentUpdate);
            //更新为未通知用户
            $sourceUpdate['is_notice'] = 0;
            DB::table('source')->where('id', $sid)->update($sourceUpdate);

            //加入新内容
            $ret = array(
                'sid' => $source['sid'],
            );
            $insertData['sid'] = $sid;
            $insertData['uid'] = $clients['id'];
            $insertData['description'] = $data['desc'];
            $insertData['createtime'] = date('Y-m-d H:i:s');
            $insertData['modifytime'] = $insertData['createtime'];
            //resource
            if (!empty($data['resources'])) {
                foreach ($data['resources'] as $key => $value) {
//					$update['cid'] = $insertData['cid'];
//					$update['sid'] = $insertData['sid'];
//					$update['sindex'] = $value['index'];
//					DB::table('resource')->where('id', $value['rid'])->update($update);
//
                    $uuid1 = Uuid::uuid1();
                    $resource_id = $uuid1->getHex();
                    $update = [];
                    $update['id'] = $resource_id;
                    $update['uid'] = $uid;
                    $update['stype'] = 3;
                    $update['cid'] = $insertData['cid'];
                    $update['sid'] = $insertData['sid'];
                    $update['url'] = $value['rid'];
                    $update['sindex'] = $value['index'];
                    DB::table('resource')->insert($update);
                }
            }
            if (!empty($data['oldResources']) && is_array($data['oldResources'])) {
                foreach ($data['oldResources'] as $key => $value) {
                    $model = new resource();
                    $uuid1 = Uuid::uuid1();
                    $resource_id = $uuid1->getHex();
                    $model->id = $resource_id;
                    $model->cid = $insertData['cid'];
                    $model->sid = $insertData['sid'];
                    $model->sindex = $value['index'];
                    $model->uid = $uid;
                    $model->stype = 3;
                    $model->url = $value['rid'];
                    $model->save();
                }
            }
            if (!empty($data['desc']) || !empty($data['resources'])) {
                DB::table('contents')->insert($insertData);
            }
            DB::commit();                   //事务提交

            //写redis
            $redisKey = "source_update_notice_list";
            Redis::lpush($redisKey, $sid);
            $res = source_sensitives::push_source_content($sid, $insertData['cid']);
            Redis::del($this->r_content_prefix . $sid);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::INFO($e->getMessage());
            return response()->json([
                'status_code' => 200,
                'error_message' => '更新料失败'
            ]);
        }

        return response()->json([
            'status_code' => 200,
            'data' => $ret
        ]);
    }


    public function getNewQrCodeNew($data) {
        $nickname = $data['title'];    // 昵称
        $sub_title = $data['sub_title'];    // 昵称
        $money = $data['price'];    // 金额
        $content = $data['content'];  // 名称
        $description = $data['description'];
        if ($data['resources'] && count($data['resources']) > 0) {
            $resource = $data['resources'][0]['url'];
            // $resource = str_replace('https://zy.qiudashi.com/','',$resource);
        }

        // 背景图片
        $img = Image::make('image/bgglm.png');
        $day = $data['pack_day'];
        if ($day > 0) {
            $img->insert('image/slant.png', 'top-right');
            if ($day == 7) {
                $day = '周';
            } else if ($day == 30) {
                $day = '月';
            } else {
                $day .= '天';
            }
            $img->text('包' . $day . '', 695, 52, function ($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#ffffff");
                $font->align("center");
                $font->angle(315);
            });
        }
        if ($data['pack_type'] == 2) {
            $img->insert('image/slant.png', 'top-right');
            $img->text('不对返还', 695, 52, function ($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#ffffff");
                $font->align("center");
                $font->angle(315);
            });

        }
        if ($data['pack_type'] == 3) {
            $img->insert('image/slant.png', 'top-right');
            $img->text('限时料', 695, 52, function ($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#ffffff");
                $font->align("center");
                $font->angle(315);
            });
        }
        // 资源
        if (empty($sub_title)) {
            $yIndex = 154 + 36;
        } else {
            $yIndex = 114 + 36;
        }

        $img->text($content, 375, $yIndex, function ($font) {
            $font->file('ht.ttf');
            $font->size(36);
            $font->color("#fefefe");
            $font->align("center");
        });

        // 副标题
        if (!empty($sub_title)) {
            $yIndex = $yIndex + 38 + 26;
            //副标题换行，每行20个字
            $subTitleArray = $this->autowrap($sub_title, 40);
            foreach ($subTitleArray as $line) {
                $img->text($line, 375, $yIndex, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(26);
                    $font->color("#fefefe");
                    $font->align("center");
                });
                $yIndex += 18 + 26;
            }
        } else {
            $yIndex = $yIndex + 32;
        }

        //=============模糊==============

        $blurImg = Image::canvas(650, 324, '#c19d71');//新模糊色号
//		$blurImg = Image::canvas(650, 324, '#dd3317');//原红色模糊色号
        $resourceHeight = 0;
        if (!empty($description)) {
            $wrap = $this->autowrap($description, 40);
            foreach ($wrap as $line) {
                $blurImg->text($line, 20, 30 + $resourceHeight, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(30);
                    $font->color("#ffffff");
                });
                $resourceHeight += 50;
            }
        }
        if (isset($resource) && !empty($resource)) {
            $resourceImg = Image::make($resource)->widen(500, function ($constraint) {
                $constraint->upsize();
            })->blur(40);
            $blurImg->insert($resourceImg, 'top', 325, $resourceHeight + 20, function ($res) {
                $res->align("center");
            });
        }
        $blurImg->blur(20);
        $img->insert($blurImg, 'top', 375, $yIndex, function ($res) {
            $res->align("center");
        });
        $img->insert('image/downglm.png', 'bottom-left', 0, 0);
        $avatar = $this->circular($data['avatarUrl']);
        $avatar = Image::make($avatar);
        $img->insert($avatar, 'bottom-left', 77, 58, function ($top) {
            $top->align("center");
        });
        $qrcode = $this->generateQrcode($data['wx_scene'], $data['uid']);
        $qrcode = Image::make($qrcode)->widen(160, function ($constraint) {
            $constraint->upsize();
        });
        $qrBg = new Imagick();
        $qrBg->newImage(170, 170, new ImagickPixel('transparent'));
        $qrBg->setImageFormat('png');
        // $qrBg->roundCorners(4, 4);

        $draw = new ImagickDraw();
        $draw->setStrokeColor('#ffffff');
        $draw->setFillColor('#ffffff');
        $draw->roundRectangle(0, 0, 170, 170, 10, 10);
        $qrBg->drawImage($draw);

        $qrBg = Image::make($qrBg);
        $qrBg->insert($qrcode, 'top-left', 5, 5);
        $img->insert($qrBg, 'bottom-left', 290, 220);
        $img->text('长按扫码 立即获取', 290, 590, function ($font) {
            $font->file('ht.ttf');
            $font->size(20);
            $font->color('#333333');
        });
        //微信昵称
        $nickname = rtrim($nickname);
        $nickArr = [];
        while (true) {
            $nickArr[] = mb_substr($nickname, 0, 8);
            $nickname = mb_substr($nickname, 8);
            if (mb_strlen($nickname) == 0) {
                break;
            }
        }
        $nickHeight = 648;
        foreach ($nickArr as $nickStr) {
            $nickHeight = $this->handleNickName($img, $nickStr, $nickHeight, 648);
        }

        if ($money > 0) {
            $img->text('', 646, 674, function ($font) {
                $font->file('ht.ttf');
                $font->size(30);
                $font->color('#000000');
                $font->align('right');
                $font->valign('bottom');
            });
            $img->text($money, 601, 690, function ($font) {
                $font->file('ht.ttf');
                $font->size(80);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('bottom');
            });
        } else {
            $img->text('免费', 640, 670, function ($font) {
                $font->file('ht.ttf');
                $font->size(60);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('bottom');
            });
        }
        $basePath = $this->checkPathExist();
        $onlinePathInfo = explode("/", $data['url']);
        $localPath = $basePath . "/" . $onlinePathInfo[1];

        $img->save($localPath);
        $objUfile = new Ufile();
        $bucket = "qiudashizy";
        $re = $objUfile->put($bucket, $data['url'], $localPath);
        if ($re) {
            $return['status'] = 200;
        } else {
            $return['status'] = 400;
        }
        return $return;
    }


    public function test(){
        // 生成资源图
        $imgdata = [
            'title' => 'baby  giraffe💕',
            'price' => '189',
            'content' => '标题标题标题标题标题',
            'sub_title' => '副标题副标题副标题副标题副标题副标题副标题副标题',
            'description' => '内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容内容',
            'resources' => '',
            'avatarUrl' => 'http://thirdwx.qlogo.cn/mmopen/vi_32/hFS5ztyjwoYjYaJC2McnTQBVfhtUozlhnKjsxE0Xiby7BC3JcREfwDPFnBaMzDjKstibkbXBzCdgyx6Eg29oapPg/132',
            'sid' => '108141',
            'uid' => '1000',
            'url' => 'qrcode/s.108141.932a5822163f11e9915952540055077e.jpg',
            'pack_day' => '7',
            'pack_type' => '1',
            'wx_scene' => 's.108141',
        ];

//			$this->getQrcode($imgdata);
        $res = $this->getNewQrCode($imgdata, 2);
        var_dump($res);exit;
    }

    public function getNewQrCode($data, $share_type = 1) { //yes
        $nickname = $data['title'];    // 昵称
        $sub_title = $data['sub_title'];    // 昵称
        $money = $data['price'];    // 金额
        $content = $data['content'];  // 名称
        $description = $data['description'];
        if ($data['resources'] && count($data['resources']) > 0) {
            $resource = $data['resources'][0]['url'];
            //$resource = str_replace('https://zy.qiudashi.com/','',$resource);
        }
        $new_eye = 'image/new_eye.png';

        switch($share_type){
            case 1:
                $bg_img_path = 'image/bg.png';
                $down_img_path = 'image/down.png';
                $slant_img_path = 'image/slant.png';
                break;
            case 2:
                $bg_img_path = 'image/bg2.png';
                $down_img_path = 'image/down2.png';
                $slant_img_path = 'image/slant2.png';
                break;
            case 3:
                $bg_img_path = 'image/bg3.png';
                $down_img_path = 'image/down3.png';
                $slant_img_path = 'image/slant3.png';
                break;
            default:
                $bg_img_path = 'image/bg.png';
                $down_img_path = 'image/down.png';
                $slant_img_path = 'image/slant.png';
                break;
        }

        // 背景图片
        $img = Image::make($bg_img_path);
        $downHeight = 0;
        $day = $data['pack_day'];
        if ($day > 0) {
            $img->insert($slant_img_path, 'top-right');
            if ($day == 7) {
                $day = '周';
            } else if ($day == 30) {
                $day = '月';
            } else {
                $day .= '天';
            }
            $img->text('包' . $day . '', 695, 52, function ($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#ffffff");
                $font->align("center");
                $font->angle(315);
            });
//			$downHeight = 30;
        }
        if ($data['pack_type'] == 2) {
            $img->insert($slant_img_path, 'top-right');
            $img->text('不对返还', 695, 52, function ($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#ffffff");
                $font->align("center");
                $font->angle(315);
            });
//			$downHeight = 30;

        }
        if ($data['pack_type'] == 3) {
            $img->insert($slant_img_path, 'top-right');
            $img->text('限时料', 695, 52, function ($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#ffffff");
                $font->align("center");
                $font->angle(315);
            });
//			$downHeight = 30;
        }
        // 资源
        //36号字体占24像素
        if (empty($sub_title)) {
            $yIndex = 154 + 36;
        } else {
            $yIndex = 114 + 36;
        }
        $img->text($content, 375, $yIndex, function ($font) {
            $font->file('ht.ttf');
            $font->size(36);
            $font->color("#fefefe");
            $font->align("center");
        });

        // 副标题
        if (!empty($sub_title)) {
            $yIndex = $yIndex + 38 + 26;
            //副标题换行，每行20个字
//			if (mb_strlen($sub_title > 20)) {
////				var_dump(mb_substr($sub_title,0,19));
////				var_dump(mb_substr($sub_title,19));
//				$sub_title = mb_substr($sub_title, 0, 19) . "\n" . mb_substr($sub_title, 19);
////				var_dump($sub_title);
////				var_dump($description);
//			}

//			$img->text($sub_title, 375, $yIndex, function ($font) {
//				$font->file('ht.ttf');
//				$font->size(26);
//				$font->color("#fefefe");
//				$font->align("center");
//			});


            $subTitleArray = $this->autowrap($sub_title, 40);
            foreach ($subTitleArray as $line) {
                $img->text($line, 375, $yIndex, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(26);
                    $font->color("#fefefe");
                    $font->align("center");
                });
                $yIndex += 18 + 26;
            }
        } else {
            $yIndex = $yIndex + 32;
        }

        //=============模糊==============


        if($share_type == 1){
            $blurImg = Image::canvas(650, 324, '#dd3317');
        } elseif($share_type == 2){
            $blurImg = Image::canvas(650, 324, '#f13735');
        } elseif($share_type == 3){
            $blurImg = Image::canvas(650, 324, '#0ead73');
        }

        $resourceHeight = 0;
        if (!empty($description)) {
            $wrap = $this->autowrap($description, 40);
            foreach ($wrap as $line) {
                $blurImg->text($line, 20, 40 + $resourceHeight, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(30);
                    $font->color("#ffffff");
                });
                $resourceHeight += 50;
            }
        }
        /*if (isset($resource) && !empty($resource)) {
            $resourceImg = Image::make($resource)->widen(500, function ($constraint) {
                $constraint->upsize();
            })->blur(40);
            $blurImg->insert($resourceImg, 'top', 325, $resourceHeight + 20, function ($res) {
                $res->align("center");
            });
        }*/
        $blurImg->blur(20);
//		$img->insert($blurImg, 'top', 375, 176 + $downHeight, function ($res) {
        $img->insert($blurImg, 'top', 375, $yIndex, function ($res) {
            $res->align("center");
        });
        $img->insert($down_img_path, 'bottom-left', 0, 0);
        $avatar = $this->circular($data['avatarUrl']);
        $avatar = Image::make($avatar);
        $img->insert($avatar, 'bottom-left', 78, 66, function ($top) {
            $top->align("center");
        });
        $qrcode = $this->generateQrcode($data['wx_scene'], $data['uid']);
        $qrcode = Image::make($qrcode)->widen(160, function ($constraint) {
            $constraint->upsize();
        });
        $qrBg = new Imagick();
        $qrBg->newImage(170, 170, new ImagickPixel('transparent'));
        $qrBg->setImageFormat('png');
        // $qrBg->roundCorners(4, 4);

        $draw = new ImagickDraw();
        $draw->setStrokeColor('#ffffff');
        $draw->setFillColor('#ffffff');
        $draw->roundRectangle(0, 0, 170, 170, 10, 10);
        $qrBg->drawImage($draw);

        $qrBg = Image::make($qrBg);
        $qrBg->insert($qrcode, 'top-left', 5, 5);
        $img->insert($qrBg, 'bottom-left', 290, 220);
        $img->text('长按扫码 立即获取', 290, 590, function ($font) {
            $font->file('ht.ttf');
            $font->size(20);
            $font->color('#fff');
        });
        $nickname = rtrim($nickname);
        //if(mb_strlen($nickname)<8){
        //$this->handleNickName($img,$nickname,673);
        //}else{
        $nickArr = [];
        $nick_len = strlen($nickname);
        while (true) {
            $nickArr[] = mb_substr($nickname, 0, 8);
            $nickname = mb_substr($nickname, 8);
            if (mb_strlen($nickname) == 0) {
                break;
            }
        }
        $nickHeight = 648;
        foreach ($nickArr as $nickStr) {
            $nickHeight = $this->handleNickName($img, $nickStr, $nickHeight, 648);
        }

        if ($money > 0) {
            //$img->text('', 646, 674, function ($font) {
            //    $font->file('ht.ttf');
            //    $font->size(30);
            //    $font->color('#fff');
            //    $font->align('right');
            //    $font->valign('bottom');
            //});
            if($share_type == 1){
                $img->text($money, 601, 680, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(40);
                    $font->color('#fff');
                    $font->align('right');
                    $font->valign('bottom');
                });
            } elseif($share_type == 2) {
                $img->text($money, 601, 680, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(40);
                    $font->color('#ffe3a5');
                    $font->align('right');
                    $font->valign('bottom');
                });
            } elseif($share_type == 3) {
                $img->text($money, 601, 680, function ($font) {
                    $font->file('ht.ttf');
                    $font->size(40);
                    $font->color('#fff');
                    $font->align('right');
                    $font->valign('bottom');
                });
            }
            $money = $money * 1;
            if (is_int($money)) {
                if ($money > 100) {
                    $img->insert($new_eye, 'bottom-left', 478, 95);
                } elseif ($money > 9) {
                    $img->insert($new_eye, 'bottom-left', 492, 95);
                } else {
                    $img->insert($new_eye, 'bottom-left', 506, 95);
                }
            } else {
                if ($money > 100) {
                    $img->insert($new_eye, 'bottom-left', 420, 95);
                } else {
                    $img->insert($new_eye, 'bottom-left', 460, 95);
                }
            }

        } else {
            $img->text('免费', 640, 670, function ($font) {
                $font->file('ht.ttf');
                $font->size(40);
                $font->color('#ffffff');
                $font->align('right');
                $font->valign('bottom');
            });
        }
        $basePath = $this->checkPathExist();
        $onlinePathInfo = explode("/", $data['url']);
        $localPath = $basePath . "/" . $onlinePathInfo[1];

        $img->save($localPath);
	      $re = $this->upload2Qiniu($data['url'], $localPath);
        if (!empty($re)) {
            $return['status'] = 200;
        } else {
            $return['status'] = 400;
        }
        return $return;
    }

    public function upload2Qiniu($key, $filePath) {
      $upToken = $this->getUploadToken();
      $res = $this->qiniuUploadFile($upToken, $key, $filePath);
      return $res;
    }

    private function dealPackType5($pack_type, $price) {
        if ($pack_type == 0 && $price > 0) {
            return 5;
        } else {
            return $pack_type;
        }
    }

}
