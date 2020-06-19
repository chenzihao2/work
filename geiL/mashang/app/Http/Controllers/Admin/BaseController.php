<?php

namespace App\Http\Controllers\Admin;

use App\models\client;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use App\Http\Controllers\Controller;
use App\models\admin;
include 'role.php';

class BaseController extends Controller
{
    // 获取用户详情
    public function getUserInfo($token)
    {
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            $return['status_code'] = "401";
            $return['message'] = "Expired";
            return $return;
        } catch (TokenInvalidException $e) {
            $return['status_code'] = "401";
            $return['message'] = "Invalid";
            return $return;
        } catch (JWTException $e) {
            $return['status_code'] = "401";
            $return['message'] = "Exception";
            return $return;
        }
        return $user;
    }


    // 查看用户是否拥有权限
    public function userRolePower($role, $power)
    {
        $roles = new \role();
        return $roles->power($role, $power);
    }


    /**
     * 状态转换，转换成所需类型并返回所需状态， 从右向左进行读取
     * 十进制转换为二进制， 然后进行截取读取返回。
     */
    public function statusChanger($status, $len)
    {
        $status = decbin($status);
        $status = sprintf('%08d', $status);
        return substr($status, -$len, 1);
    }

    /**
     * 数组转换为xml
     * param array $data
     * return xml
     */
    public function arraytoxml($data)
    {
        $str='<xml>';
        foreach($data as $k=>$v) {
            $str.='<'.$k.'>'.$v.'</'.$k.'>';
        }
        $str.='</xml>';
        return $str;
    }

    /**
     * curl 发送请求
     * param string $url 地址
     * param xml/array $data 参数
     * param string xml/json $type 所传类型
     * param string ca 是否需要ca证书 0 / 1
     */
    public function https_curl_json($url,$data,$type,$ca = ''){

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if($type=='json'){//json $_POST=json_decode(file_get_contents('php://input'), TRUE);
            $headers = array("Content-type: application/json;charset=UTF-8","Accept: application/json","Cache-Control: no-cache", "Pragma: no-cache");
            $data=json_encode($data);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
        } else if ($type == 'xml') {
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        }

        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
        }

        // 是否需要微信ca证书
        if($ca == 1) {
          curl_setopt($curl,CURLOPT_SSLCERTTYPE,'PEM');
          curl_setopt($curl,CURLOPT_SSLCERT, 'weChatCert6781/apiclient_cert.pem');
//          curl_setopt($curl,CURLOPT_SSLCERT, 'wechatnewcert/apiclient_cert.pem');
          curl_setopt($curl,CURLOPT_SSLKEYTYPE,'PEM');
          curl_setopt($curl,CURLOPT_SSLKEY, 'weChatCert6781/apiclient_key.pem');
//          curl_setopt($curl,CURLOPT_SSLKEY, 'wechatnewcert/apiclient_key.pem');
            //curl_setopt($curl, CURLOPT_CAINFO, "wechatcert/DigiCert_Global_Root_CA.pem");
            //curl_setopt($curl, CURLOPT_SSLCERT, "wechatcert/apiclient_cert.pem");
            //curl_setopt($curl, CURLOPT_SSLKEY, "wechatcert/apiclient_key.pem");
        }

        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        return $output;
    }

    /**
     * 用户token认证和权限认证
     * @param $clients
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkToken($clients){
        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $roles = ['root', 'admin', 'audit1', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }
    }

    /**
     * 推荐料对应的score值转str
     * @param $score
     * @return string
     * score默认长度18位
     * 第一位：是否免费（1：免费；0：付费）
     * 第二位：是否推荐（1：推荐；0：非推荐）
     * 第(3-8)位：排序占位
     * 第（9-18）位：时间戳
     */
    public function score2str($score){
        return sprintf("%018d",$score);
    }
    /**
     * 将推荐料队列做排序
     * @param $uppersort
     * @param $lowersort
     * @return string
     */
    public function sort($uppersort,$lowersort){
        //总共6位循环从最高位依次比较插入sort值（如果两个都是0,则取最高位的中间值5）
        if($uppersort == 0){
            return '500000';
        }
        $is_sort = false;
        for($i = 0;$i<6;$i++){
            if($is_sort){
                $score{$i} = 0;
            }else{
                if($uppersort{$i}-$lowersort{$i}>1){        //如果此位有间隔位，则插入，后面位数补0
                    $score{$i} = $uppersort{$i}-ceil(($uppersort{$i}-$lowersort{$i})/2);
                    $is_sort = true;        //如果此位已插入，设$is_sort为true，则后面无需判断，直接补0
                }else{                                      //否则比较下一位
                    $score{$i} = 0;
                }
            }
        }
        return $score;
    }

    /**
    把用户输入的文本转义（主要针对特殊符号和emoji表情）
     */
    function userTextEncode($str){
        if(!is_string($str))return $str;
        if(!$str || $str=='undefined')return '';

        $text = json_encode($str); //暴露出unicode
        $text = preg_replace_callback("/(\\\u[ed][0-9a-f]{3})/i",function($str){
            return addslashes($str[0]);
        },$text); //将emoji的unicode留下，其他不动，这里的正则比原答案增加了d，因为我发现我很多emoji实际上是\ud开头的，反而暂时没发现有\ue开头。
        return json_decode($text);
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
//		if (false) {

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
			Redis::setex($key,7000, $data['access_token']);
			return $data['access_token'];
		}
	}
}
