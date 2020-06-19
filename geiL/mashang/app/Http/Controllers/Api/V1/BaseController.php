<?php

namespace App\Http\Controllers\Api\V1;

use App\Clients;
use App\models\client_extra;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Iwanli\Wxxcx\Wxxcx;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use App\models\ksyun_sms;

include_once(app_path() . '/sms/sdk.php');

class BaseController extends Controller
{

    protected $wxxcx;

    function __construct(Wxxcx $wxxcx)
    {
        $this->wxxcx = $wxxcx;
    }

    // 根据token获取用户详情
    public function UserInfo($token){
        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
	    if (isset($user['id']) && $user['auth_refresh'] == 0) {
                $user['id'] = -101;
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

    /**
     * 根据encryptedData iv 获取用户信息
     * param array $data
    */
    public function GetUserInfo($data)
    {
        //encryptedData, iv, sessionKey, code, openid
        if ( empty($data['code']) ) {
            $info = $this->wxxcx->getUserInfo($data['encryptedData'], $data['iv'], $data['sessionKey']);
        } else {
            $userInfo = $this->wxxcx->getLoginInfo($data['code']);
            $info = $this->wxxcx->getUserInfo($data['encryptedData'], $data['iv'], $data['sessionKey']);
        }
        $info = json_decode($info, True);

        $users['nickname'] = $info['nickName'];
        $users['sex'] = $info['gender'];
        $users['province'] = $info['province'];
        $users['country'] = $info['country'];
        $users['city'] = $info['city'];
        $users['avatarurl'] = $info['avatarUrl'];

        if (empty($users)) {
            return response()->json(['message' => '获取信息失败，请重新登陆尝试'], 500);
        }

        Clients::where("openid", $data['openid'])->update($users);

        return True;

    }


    /**
     * 解析二进制
     */
    public function decAnalysis($status, $len)
    {
        $status = decbin($status);
        $status = sprintf('%08d', $status);
        return substr($status, -$len, 1);
    }


    /**
     * 短信接口
     * zzq 11-09
     * @param string $mobile 手机号
     * @param string $content 发送内容
     * @return string rrid 返回的唯一标示
     */
    //public function seed_sms($mobile, $content)
    //{
    //    if( empty($mobile) or empty($content)) {
    //        return "参数不可为空";
    //    }
    //    $sn = "SDK-666-010-03413";
    //    $pwd = strtoupper(md5("SDK-666-010-03413392325"));
    //    $url = 'http://sdk.entinfo.cn:8061/webservice.asmx/mdsmssend?sn='.$sn.'&pwd='.$pwd.'&mobile='.$mobile.'&content='.$content.'&ext=10&stime=&rrid=&msgfmt=';

    //    $opts = [
    //        'http' => [
    //            'method' => 'GET',
    //            'timeout' => 1,
    //        ]
    //    ];
    //    $cnt = 0;
    //    $rrid = False;
    //    while( $cnt < 3 && ($rrid = file_get_contents($url, false, stream_context_create($opts))) === False ) {
    //        $cnt++;
    //    }
    //    return $rrid;
    //}

    public function seed_sms($mobile, $uid) {
	    $ksyun_sms = new ksyun_sms();	
	    $res = $ksyun_sms->send($mobile, $uid);
	    return $res;
    }

    /*public function seed_sms($mobile, $code) {
      $public_key = "rX/3xdXXpvOHLyRB8hMx5IV4/usfv/ogeEHEro60V/C8pXMMKgOOYQ==";
      $private_key = "kbb6zzMiBjxuhijDD8CgkSd+Xv8tc3QdlVa7Z9c2Wbxz/yX4etnHei4B48tHWQJu";
      $productId = "org-4bnymp";
      $conn = new \UcloudApiClient("http://api.ucloud.cn", $public_key, $private_key, $productId);
      $params['Action'] = "SendUSMSMessage";
      $params['SigContent'] = "给料";
      $params['TemplateId'] = "UTA19101097A3A0";
      $params["PhoneNumbers.0"] = $mobile;
      $params["TemplateParams.0"] = $code;
      $response = $conn->get("/", $params);
      return $response;
    }*/

    /**
     * 修改用户身份
     * 1, 修改为卖家
     * 2， 修改为买家
     */
    public function userRoles($uid, $role)
    {
        $extras = client_extra::select()->where("id", $uid)->first();
        $status = $this->decAnalysis($extras['role'], $role);
        if ( $status == 1) {
            return True;
        }
        $status = decbin($extras['status']);
        $oldStatus = sprintf('%08d', $status);
        $newStatus = substr_replace($oldStatus, 1, -$role, 1);
        $newStatusChange = bindec((int)$newStatus);
        client_extra::where("id", $uid)->update(['role' => $newStatusChange]);
        return True;
    }


    /**
     * 发送请求
     */
    public function seedRequest($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public function successReturn($code = 200,$data){
        $return['status_code'] = '200';
        $return['data'] = $data;

        return response()->json($return);
    }

    public function errorReturn($error_code,$error_message){
        $return['status_code'] = $error_code;
        $return['error_message'] = $error_message;

        return response()->json($return);
    }

    public function uniqueID(){
        $currentTime = time();
        $uniqueID = rand(0,99999).$currentTime;
        return $uniqueID;
    }

    public function object2Array($data){
        if(is_object($data)) {
            $data = (array)$data;
            return $data;
        }
        if(is_array($data)) {
            foreach($data as $key=>$value) {
                $data[$key] = object2Array($value);
            }
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
        $param['timestamp'] = 	time()*1000;  //统一给参数添加时间戳参数
        // $reqUrl = config('wxxcx.hy_reqUrl');
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
     * signCheck 接口数据返回验签
     * 对返回的数据继续进行验签方法/并真正返回数据给前端使用
     * 当请求接口的数据中带有result 有返回sign字段时使用验签
     */
    public function  signCheck($response)
    {
        $responses = json_decode($response, true);
        if (!isset($responses['result'])) {
            return $response;
        } else {
            $result = $responses['result']; //得到result部分进行验签
            $resSign = $result['sign'];  //获取接口返回的sign
            unset($result['sign']);   //去除result的sign 将其加密生成签名
            $sign = $this->createSign($result);
            if ($sign != $resSign) { //判断接口传回的签名的是否和该签名一直、保证数据传输时不被篡改
                return json_encode(array("msg" => "sign 验签失败", 'code' => 5));
            } else {
                return $response;
            }
        }
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
        // $secret = config('wxxcx.hy_secret');
        $secret = config('pay.hypay.secret');
        $sign = md5($signPars . $secret); //sign签名生成
        return $sign;
    }

    public function getUploadToken() {
      $accessKey = config('qiniu.ak');
      $secretKey = config('qiniu.sk');
      // 初始化签权对象
      $auth = new Auth($accessKey, $secretKey);
      $bucket = config('qiniu.bucket');
      // 生成上传Token
      $token = $auth->uploadToken($bucket);
      return $token;
    }

    public function qiniuUploadFile($upToken, $key, $filePath){
      $uploadMgr = new UploadManager();
      list($ret, $err) = $uploadMgr->putFile($upToken, $key, $filePath);
      if($err !== null){
        return null;
      }else{
        return $key;
      }
    }

    public function rs_fetch($url, $key){
      $auth = new Auth(config('qiniu.ak'), config('qiniu.sk'));
      $bucket = config('qiniu.bucket');
      $bucketManager = new BucketManager($auth);

      list($ret, $err) = $bucketManager->fetch($url, $bucket, $key);
      if ($err !== null) {
        return null;
      } else {
        $res = config('qiniu.host') . DIRECTORY_SEPARATOR . $key;
        return $res;
      }
    }
}
