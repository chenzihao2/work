<?php

namespace App\Http\Controllers\Api\V1;

use App\models\client_account;
use App\models\client_log;
use App\models\client_ip_log;
use App\models\client_extra;
use App\models\client_rate;
use App\models\client_subscribe;
use App\models\client_withdraw;
use App\models\client_signature_log;
use App\models\follow;
use App\models\order;
use App\models\source;
use App\models\source_extra;
use App\models\source_sensitives;
use App\models\profile;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\models\client;
use App\models\sms_log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;
use \QKPHP\Common\Config\Config;
use \QKPHP\Common\Utils\Url;
use \QKPHP\SNS\Consts\Platform;
use \QKPHP\SNS\Weixin as WeixinService;
use App\Http\Controllers\Api\V1\SourcesController;
use Intervention\Image\Facades\Image;
use Imagick;
use ImagickDraw;
use ImagickPixel;

use Iwanli\Wxxcx\Wxxcx;

class ClientsController extends BaseController
{
    protected $wxxcx;
    private   $weixinService;
    private   $default_signature = '记得扫码关注我哦～';

    //function __construct(Wxxcx $wxxcx)
    //{
    //  $this->wxxcx = $wxxcx;
    // }

    private function getWeixinService () {
        if (empty($this->weixinService)) {
            Config::setConfigDir(dirname(getcwd()). DIRECTORY_SEPARATOR . 'config');
            $conf = Config::getConf('wxxcx');
            $this->weixinService = new WeixinService($conf['wechat_appid'], $conf['wechat_appsecret']);
        }
        return $this->weixinService;
    }

    public function wxAuth() {
        $redirect = request('r', '');
        $code = request('code', '');

        $appid = config("wxxcx.wechat_appid");
        $appsecret = config("wxxcx.wechat_appsecret");
        $weixinService = new WeixinService($appid, $appsecret, ['platform' => Platform::H5, 'scope' => 'user']);

        if (!empty($code)) {
            //var_dump($_REQUEST);
            //$weixinService = $this->getWeixinService();
            //$weixinService->getSessionAccessTokenByAuth($code);
            //$wxUser = $weixinService->getUserInfoByAuth();
            $wxUser = $weixinService->getUserInfo($code);
            $user = $this->clientRegister($wxUser);
            if(empty($user)){
		        Url::redirect($redirect);
                //return $this->errorReturn(10003,'用户注册失败');
            } else {
                if (strpos($redirect, '?') === false) {
                  $redirect .= '?';
                } else {
                  $redirect .= '&';
                }
                $redirect.="user=".urlencode(json_encode($user));
	              $this->getSubscribeInfo($user['uid'], $redirect);
                client_ip_log::record_ip($user['uid']);
            	  //Url::redirect($redirect);
	          }
        } else {
          $redirect = config('constants.backend_domain') . '/pub/user/wxauth?r=' . urlencode($redirect);
          Url::redirect($weixinService->toAuth($redirect, true, 'geiliao'));
          /*$callback = config('constants.backend_domain') . '/pub/user/wxauth?r=' . urlencode($redirect);
          $authUrl = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='. config('wxxcx.wechat_appid') .
            '&redirect_uri=' . urlencode($callback) . 
            '&response_type=code&scope=snsapi_userinfo&state=geiliao#wechat_redirect';
          Url::redirect($authUrl);*/
        }
    }

    private function getSubscribeInfo($uid, $redirect) {
      $appid = config("wxxcx.wechat_subscribe_appid");
      $appsecret = config("wxxcx.wechat_subscribe_appsecret");

      $subscribeInfo = client_subscribe::where('user_id', $uid)->where('appid', $appid)->first();
      if(empty($subscribeInfo)){
        $redirect_uri = config('constants.backend_domain') . '/pub/user/subscribeAuth?uid='.$uid.'&r=' . urlencode($redirect);
        $weixinService = new WeixinService($appid, $appsecret, ['platform' => Platform::H5]);
        Url::redirect($weixinService->toAuth($redirect_uri, false, 'STATE'));
        /*$url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".urlencode($redirect_uri)."&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect";
        Header("Location:".$url);*/
      } else {
        //$subscribe = $subscribeInfo['subscribe'];
        //$redirect .= "&subscribe=" . $subscribe;
        Url::redirect($redirect);
      }
    }

    public function subscribeAuth() {
      $appid = config("wxxcx.wechat_subscribe_appid");
      $appsecret = config("wxxcx.wechat_subscribe_appsecret");

      $code = request('code', '');
      $uid = request('uid', 0);
      $redirect = request('r', '');
      if (!empty($code)) {
        /*$weixinService = new WeixinService($appid, $appsecret);
        $accessToken = $weixinService->getSessionAccessTokenByAuth($code);
        //$wxUser = $weixinService->getUserInfoByAuth();
        $openId = $weixinService->getOpenId();*/

        $weixinService = new WeixinService($appid, $appsecret, ['platform' => Platform::H5]);
        $accessToken = $weixinService->getSessionAccessTokenByAuth($code);
        $openId = $weixinService->openId;

        $data = [];
        $data['user_id'] = $uid;
        $data['appid'] = $appid;
        $data['openid'] = $openId;
        $data['create_time'] = time();
        $subscribeInfo = client_subscribe::where('user_id', $uid)->where('appid', $appid)->first();
        if (empty($subscribeInfo)) {
          client_subscribe::create($data);
        }
      }
        Url::redirect($redirect);
    }

    /**
     * 微信订阅公众号授权登录
     * @return \Illuminate\Http\JsonResponse
     */
    public function wxSubscribe() {
        $redirect = request('r', '');
        $code = request('code', '');
        $appid = config("wxxcx.wechat_subscribe_appid");
        $appsecret = config("wxxcx.wechat_subscribe_appsecret");
        $weixinService = new WeixinService($appid, $appsecret, ['platform' => Platform::H5, 'scope' => 'user']);
        if (!empty($code)) {
            /*$weixinService = new WeixinService($appid, $appsecret);
            $accessToken = $weixinService->getSessionAccessTokenByAuth($code);
            $wxUser = $weixinService->getUserInfoByAuth();*/

            $wxUser = $weixinService->getUserInfo($code);
            if(isset($wxUser['errcode'])){
                return view('wechat/subscribe');
            }

            $openId = $wxUser['openId'];

            $subscribeInfo = client_subscribe::where('openid', $openId)->where('appid', $appid)->first();
            if(empty($subscribeInfo)){
                $userInfo = client::where('unionid',$wxUser['unionId'])->first();
                if(!empty($userInfo)){
                    $data = [];
                    $data['user_id'] = $userInfo['id'];
                    $data['appid'] = $appid;
                    $data['openid'] = $openId;
                    $data['create_time'] = time();
                    client_subscribe::create($data);
                }
            }
            return view('wechat/subscribe');
        } else {
          $redirect_uri = urlencode(config('constants.backend_domain') . "/pub/user/wxsubscribe");
          Url::redirect($weixinService->toAuth($redirect_uri, true, 'STATE'));
            /*$redirect_uri = urlencode(config('constants.backend_domain') . "/pub/user/wxsubscribe");
            $response_type = 'code';
            $scope = 'snsapi_userinfo';
            $state = 'STATE';
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_uri."&response_type=".$response_type."&scope=".$scope."&state=".$state."#wechat_redirect";
            Header("Location:".$url);*/
        }
    }

    private function clientRegister($content)
    {
        if (!isset($content['unionId'])) {
	        return;
	      }
	      $users = client::where('unionid',$content['unionId'])->first();
        if( $users ) {
            $we_update = [];
            if(isset($content['name']) && $content['name'] != $users['nickname']){
                $we_update['nickname'] = $content['name'];
            }
            if(isset($content['sex']) && $content['sex'] != $users['sex']){
                $we_update['sex'] = $content['sex'];
            }
            if(isset($content['city']) && $content['city'] != $users['city']){
                $we_update['city'] = $content['city'];
            }
            if(isset($content['country']) && $content['country'] != $users['country']){
                $we_update['country'] = $content['country'];
            }
            if(isset($content['province']) && $content['province'] != $users['province']){
                $we_update['province'] = $content['province'];
            }
            if(isset($content['avatar']) && $content['avatar'] != $users['avatarurl']){
                $we_update['avatarurl'] = $content['avatar'];
            }
            //if(empty($users['serviceid'])){
                //如果没有公众号openid则更新
            $we_update['serviceid'] = $content['openId'];
            $we_update['openid'] = $content['openId'];
	          $we_update['auth_refresh'] = 1;
            //}
            if($we_update && !empty($we_update)){
                $we_update['modifytime'] = date("Y-m-d H:i:s");
                client::where('id',$users['id'])->update($we_update);
            }
            $firstLogin = 0;
            $uid = $users['id'];
            // 进行用户副表记录添加
            $extra['lastlogin'] = date("Y-m-d H:i:s");
            client_extra::where('id', $uid)->update($extra);
        } else {
            // 首次登陆
            //$insert['openid'] = '';     //小程序的openid
            $insert['openid'] = $content['openId'];     //小程序的openid
            $insert['serviceid'] = $content['openId'];      //公众号的openid
            $insert['unionid'] = $content['unionId'];
            $insert['nickname'] = $content['name'];
            $insert['sex'] = $content['sex'];
            $insert['city'] = $content['city'];
            $insert['country'] = $content['country'];
            $insert['province'] = $content['province'];
            $insert['avatarurl'] = $content['avatar'];
            $insert['createtime'] = date("Y-m-d H:i:s");
            $insert['modifytime'] = $insert['createtime'];
	          $insert['auth_refresh'] = 1;
            $uid = client::insertGetId($insert);
            $firstLogin = 1;
            // 进行用户副表记录添加
            $extra['id'] = $uid;
            $extra['lastlogin'] = date("Y-m-d H:i:s");
            client_extra::create($extra);

            // 用户子表记录添加
            $rate['uid'] = $uid;
            $rate['rate'] = 5;
            $rate['effecttime'] = date("Y-m-d H:i:s");
            $rate['createtime'] = date("Y-m-d H:i:s");
            client_rate::create($rate);
        }

        $users = client::where('id', $uid)->first();
        if(empty($users)){
            return '';
        }
        $token = JWTAuth::fromUser($users);

        $log['uid'] = $uid;
        $log['description'] = $firstLogin;
        $this->log($log);

        $data['uid'] = $uid;
        $data['name'] = $users['nickname'];
        $data['avatarurl'] = $users['avatarurl'];
        $data['sex'] = $users['sex'];
        $data['token'] = $token;
        $expireTime = time()+config('jwt.ttl')*60;
        $data['expire'] = $expireTime;

        $banUid = [133546,97254, 144627, 84298, 124371, 94836, 94777, 86518, 70640, 87254, 81694, 61834,110243,612,132108];
        if(in_array($uid,$banUid)){
            $data=array();
        }
        if($users['status'] >= 100){
            $data = [];
        }
        return $data;
    }

    /**
     * 微信入口
     * url /pub/user/wxbind
     * @param code
     * @return json
     */
    public function xcxWxbind(Request $request){
        $code = $request->input('code', '');
        $encryptedData = $request->input('encryptedData', '');
        $iv = $request->input('iv', '');
        $info = $request->input('userinfo', '');
        if(!is_array($info)){
            $info = json_decode($info,true);
        }
        if(empty($code)){
            return $this->errorReturn(10001,'code不可为空');
        }
        $wxxcx = new \Iwanli\Wxxcx\Wxxcx();
        $loginInfo = $wxxcx->getLoginInfo($code);
        if( array_key_exists('code', $loginInfo)) {
            $error['status_code'] = "10002";
            $error['error_message'] = "获取 session_key 失败";
            return response()->json($error);
        }
        $userInfo = $wxxcx->getUserInfo($encryptedData, $iv, $loginInfo['session_key']);
        if ( !is_array($userInfo) ) {
            $userInfo = json_decode($userInfo, True);
        }
        $users = client::where('unionid', $userInfo['unionId'])->first();
        if(!$users){
            $insert['id'] = Redis::incr('client_id');
            $insert['openid'] = $loginInfo['openid'];
            $insert['sessionkey'] = $loginInfo['session_key'];
            $insert['unionid'] = $userInfo['unionId'];
            $insert['nickname'] = $userInfo['nickName'];
            $insert['sex'] = $userInfo['gender'];
            $insert['city'] = $userInfo['city'];
            $insert['province'] = $userInfo['province'];
            $insert['country'] = $userInfo['country'];
            $insert['avatarUrl'] = $userInfo['avatarUrl'];
            $insert['createtime'] = date("Y-m-d H:i:s");
            $insert['modifytime'] = $insert['createtime'];
            client::create($insert);
            $extra['id'] = $insert['id'];
            $extra['lastlogin'] = $insert['createtime'];
            client_extra::create($extra);
            $rate['uid'] = $insert['id'];
            $rate['rate'] = 5;
            $rate['effecttime'] = $insert['createtime'];
            $rate['createtime'] = $insert['createtime'];
            client_rate::create($rate);

            $uid = $insert['id'];
            $firstLogin = 1;
        }else{
            $edit['sessionkey'] = $loginInfo['session_key'];
            $edit['openid'] = $loginInfo['openid'];
            $edit['modifytime'] = date("Y-m-d H:i:s");
            $edit['nickname'] = $userInfo['nickName'];
            client::where("id", $users['id'])->update($edit);
            $extre_update['lastlogin'] = date('Y-m-d H:i:s');
            client_extra::where('id',$users['id'])->update($extre_update);
            $uid = $users['id'];
            $firstLogin = 0;
        }

        $client = client::where("id", $uid)->first();
        $token = JWTAuth::fromUser($client);

        if($client['status'] >= 100){
            $client = [];
        }

        if(empty($token) || empty($client)){
            $errorReturn['status_code'] = 10003;
            $errorReturn['error_message'] = '用户登录失败';
            return response()->json($errorReturn);
        }

        $log['uid'] = $uid;
        $log['description'] = $firstLogin;
        $this->log($log);

        $return['status_code'] = 200;
        $data['token'] = $token;
        $data['uid'] = $uid;
        $data['firstLogin'] = $firstLogin;
        $data['config']['debug'] = false;
        $return['data'] = $data;

        $banUid = [133546,97254, 144627, 84298, 124371, 94836, 94777, 86518, 70640, 87254, 81694, 61834,110243,612,132108];
        if(in_array($uid,$banUid)){
            $return=array();
        }
        return response()->json($return);

    }

    public function postWxbind()
    {
        $code = request('code', '');

        if ( empty($code) ) {
            $error['status_code'] = "10001";
            $error['error_message'] = "code 不能为空";
            return response()->json($error);
        }

        //根据 code 获取用户 session_key 等信息, 返回用户openid 和 session_key
        $wxxcx = new \Iwanli\Wxxcx\Wxxcx();
        $userInfo = $wxxcx->getLoginInfo($code);
        //$userInfo = $this->wxxcx->getLoginInfo($code);
        if( array_key_exists('code', $userInfo)) {
            $error['status_code'] = "10002";
            $error['error_message'] = "获取 session_key 失败";
            return response()->json($error);
        }

        $newUser = [
            'sessionkey' => $userInfo['session_key'],
            'openid' => $userInfo['openid'],
        ];

        // 判断是否存在unionid， 存在则进行添加
        if( array_key_exists('unionid', $userInfo)) {
            $newUser['unionid'] = $userInfo['unionid'];
        }

        // 查看该用户之前是否登陆过
        $users = client::where('openid', $userInfo['openid'])->first();
        if(!$users){
            $users = client::where('unionid', $userInfo['unionid'])->first();
        }

        if( $users ) {
            $edit['sessionkey'] = $newUser['sessionkey'];
            $edit['openid'] = $newUser['openid'];
            if( array_key_exists('unionid', $userInfo) && empty($users['uinonid'])) {
                $edit['unionid'] = $userInfo['unionid'];
            }

            // 登陆过则进行修改sessionKey,unionid
            client::where("id", $users['id'])->update($edit);
            //client::where("openid", $userInfo['openid'])->update($edit);
            $token = JWTAuth::fromUser($users);
            $firstLogin = 0;
            $uid = $users['id'];

            // 进行用户副表记录添加
            $extra['lastlogin'] = date("Y-m-d H:i:s");
            client_extra::where('id', $uid)->update($extra);
        } else {
            // 首次登陆
            $newUser['id'] = Redis::incr('client_id');
            $newUser['createtime'] = date("Y-m-d H:i:s");
            $newUser['modifytime'] = $newUser['createtime'];
            client::create($newUser);
            $uid = $newUser['id'];
            $user = client::where('id', $uid)->first();
            $token = JWTAuth::fromUser($user);
            $firstLogin = 1;


            // 进行用户副表记录添加
            $extra['id'] = $uid;
            $extra['lastlogin'] = date("Y-m-d H:i:s");
            client_extra::create($extra);

            // 用户子表记录添加
            $rate['uid'] = $uid;
            $rate['rate'] = 5;
            $rate['effecttime'] = date("Y-m-d H:i:s");
            $rate['createtime'] = date("Y-m-d H:i:s");
            client_rate::create($rate);
        }

        // 登陆记录添加
        $log['uid'] = $uid;
        $log['description'] = $firstLogin;
        // 是否为第一次登陆0 no 1 yes
        $this->log($log);

        $return['status_code'] = 200;
        $return['token'] = $token;
        $return['uid'] = $uid;
        $return['firstLogin'] = $firstLogin;
        $return['config']['debug'] = false;  // 配置应用
//      if($uid ==38481){
//          $return = array();
//      }
        return response()->json($return);
    }


    /**
     * 微信用户登陆（使用场景少）， 进行更新token
     * @param token string
     * @return string token
     */
    public function postLogin($id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);

        if (empty($token) || $clients['id'] != $id) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $token = JWTAuth::refresh();
        $return['status_code'] = "200";
        $return['token'] = $token;

        return response()->json($return);
    }

    public function autoRegister(Request $request)
    {
        $content = $request->all();
        $users = client::where('unionid',$content['unionid'])->first();
        if( $users ) {
            //如果没有公众号openid则更新
            $we_update['serviceid'] = $content['openid'];
            $we_update['modifytime'] = date("Y-m-d H:i:s");
            client::where('id',$users['id'])->update($we_update);
            $firstLogin = 0;
            $uid = $users['id'];
            // 进行用户副表记录添加
            $extra['lastlogin'] = date("Y-m-d H:i:s");
            client_extra::where('id', $uid)->update($extra);
        } else {
            // 首次登陆
            $insert['id'] = Redis::incr('client_id');
            $insert['openid'] = '';     //小程序的openid
            $insert['serviceid'] = $content['openid'];      //公众号的openid
            $insert['unionid'] = $content['unionid'];
            $insert['nickname'] = $content['nickname'];
            $insert['sex'] = $content['sex'];
            $insert['city'] = $content['city'];
            $insert['country'] = $content['country'];
            $insert['province'] = $content['province'];
            $insert['avatarurl'] = $content['headimgurl'];
            $insert['createtime'] = date("Y-m-d H:i:s");
            $insert['modifytime'] = $insert['createtime'];
            client::create($insert);
            $uid = $insert['id'];
            $firstLogin = 1;
            // 进行用户副表记录添加
            $extra['id'] = $uid;
            $extra['lastlogin'] = date("Y-m-d H:i:s");
            client_extra::create($extra);

            // 用户子表记录添加
            $rate['uid'] = $uid;
            $rate['rate'] = 5;
            $rate['effecttime'] = date("Y-m-d H:i:s");
            $rate['createtime'] = date("Y-m-d H:i:s");
            client_rate::create($rate);
        }

        $users = client::where('id', $uid)->first();
        $token = JWTAuth::fromUser($users);

        $log['uid'] = $uid;
        $log['description'] = $firstLogin;
        $this->log($log);

        $data['uid'] = $uid;
        $data['name'] = $users['nickname'];
        $data['avatarurl'] = $users['avatarurl'];
        $data['sex'] = $users['sex'];
        $data['token'] = $token;
        $expireTime = time()+config('jwt.ttl')*60;
        $data['expire'] = $expireTime;
        $return['status_code'] = 200;
        $return['data'] = $data;
        return response()->json($return);
    }

    public function devLogin($uid)
    {
        //获取用户信息
        $userInfo = client::where("id", $uid)->first();
        if(!$userInfo){
            $return['status_code'] = "-10001";
            $return['error_message'] = "用户不存在";
            return response()->json($return);
        }
        $token = JWTAuth::fromUser($userInfo);
        $data['uid'] = $uid;
        $data['name'] = $userInfo['nickname'];
        $data['avatarurl'] = $userInfo['avatarurl'];
        $data['sex'] = $userInfo['sex'];
        $data['token'] = $token;
        $expireTime = time()+config('jwt.ttl')*60;
        $data['expire'] = $expireTime;
        $return['status_code'] = 200;
        $return['data'] = $data;
        return response()->json($return);
    }


    /**
     * 微信用户信息更新
     * @param id string
     * @param token string
     * @return boolean
     */
    public function putUpdate(Request $request, $uid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $encryptedData = $request->input('encryptedData', '');
        $iv = $request->input('iv', '');
        $info = $request->input('userinfo', '');

        // 判断用户表中  unionid 是否为空
        if( empty($clients['unionid']) ) {
            $wxxcx = new \Iwanli\Wxxcx\Wxxcx();
            $userInfo = $wxxcx->getUserInfo($encryptedData, $iv, $clients['sessionkey']);
            if ( is_array($userInfo) ) {
                if( array_key_exists('code', $userInfo)) {
                    error_log($uid."\n", 3, '/tmp/geiliao');
                    error_log($encryptedData."\n", 3, '/tmp/geiliao');
                    error_log($iv."\n", 3, '/tmp/geiliao');
                    error_log($clients['sessionkey']."\n", 3, '/tmp/geiliao');
                    return response()->json(['status_code' => '200']);
                }
            }
            $info = json_decode($userInfo, True);
        }

        $data = [
            'nickname' => $info['nickName'],
            'sex' => $info['gender'],
            'city' => $info['city'],
            'province' => $info['province'],
            'country' => $info['country'],
            'avatarUrl' => $info['avatarUrl'],
        ];

        if( array_key_exists('unionId', $info) ) {
            $data['unionid'] = $info['unionId'];
        }

        client::where("id", $uid)->update($data);
        return response()->json(['status_code' => '200']);
    }


    /**
     * 微信公众号用户登陆
     */
    public function getWechatLogin(Request $request)
    {
        $code = $request->input("code", "");

        $appid = config('wxxcx.wechat_appid');
        $appsecret = config('wxxcx.wechat_appsecret');

        $weixinService = new WeixinService($appid, $appsecret, ['platform' => Platform::H5, 'scope' => 'user']);

        if($code == "") {
          /*$redirect_uri = urlencode(env('APP_URL')."/pub/user/wechat");
          Url::redirect($weixinService->toAuth($redirect_uri, true, 'STATE'));*/
            $redirect_uri = urlencode(env('APP_URL')."/pub/user/wechat");
            $response_type = 'code';
            $scope = 'snsapi_userinfo';
            $state = 'STATE';
            $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$appid."&redirect_uri=".$redirect_uri."&response_type=".$response_type."&scope=".$scope."&state=".$state."#wechat_redirect";
            Header("Location:".$url);
        } else {
            /*$action_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$appsecret}&code={$code}&grant_type=authorization_code";

            $res = $this->seedRequest($action_url);
            $json_obj = json_decode($res, true);
            $access_token = $json_obj['access_token'];
            $openid = $json_obj['openid'];

            $get_user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token.'&openid='.$openid.'&lang=zh_CN';

            $data = $this->seedRequest($get_user_info_url);
            $data_obj = json_decode($data, True);

            // 微信错误返回信息
            if(array_key_exists('errcode', $data_obj)) {
                if($data_obj['errcode'] == '40003') {
                    $return['status'] = "10001";
                    $return['message'] = "openid参数失效";
                    return response()->json($return);
                }
            }*/
            
            $data_obj = $weixinService->getUserInfo($code);
		
	          $user = $this->clientRegister($data_obj);
            $return['token'] = $user['token'];
            $return['uid'] = $user['uid'];
            return redirect(config('constants.backend_domain') . '/pub/user/withdraws?token='.$user['token'].'&uid='.$user['uid']);

            /*$unionid = $data_obj['unionid'];

            $exists = client::where("unionid", $unionid)->first();

            if( empty($exists) ) {
//                $uuid= Redis::incr('client_id');
                $uuid = -101;
                $client = new client();
                $client->id = $uuid;
                $client->serviceid = $data_obj['openid'];
                $client->nickname = $data_obj['nickname'];
                $client->sex = $data_obj['sex'];
                $client->city = $data_obj['city'];
                $client->country = $data_obj['country'];
                $client->avatarurl = $data_obj['headimgurl'];
                $client->unionid = $unionid;*/

                /*$client['id'] = $uuid;
                $client['serviceid'] = $data_obj['openid'];
                $client['nickname'] = $data_obj['nickname'];
                $client['sex'] = $data_obj['sex'];
                $client['city'] = $data_obj['city'];
                $client['country'] = $data_obj['country'];
                $client['avatarurl'] = $data_obj['headimgurl'];
                $client['unionid'] = $unionid;*/
//                $create = client::create($client);
//                $uid = $create->id;
                /*$create = $client;
                $uid = $uuid;
                $token = JWTAuth::fromUser($create);
            } else {
              $uid = $exists['id'];
              if (!empty($openid) && ($openid != $exists['serviceid'] || $openid != $exists['openid'])) {
                client::where("id", $uid)->update(['serviceid' => $openid, 'openid' => $openid]);
              }
                $token = JWTAuth::fromUser($exists);
            }

            $return['token'] = $token;
            $return['uid'] = $uid;
            return redirect(config('constants.backend_domain') . '/pub/user/withdraws?token='.$token.'&uid='.$uid);*/
        }
    }


    private function checkSignature(){
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        $token = '';
        if(isset($_GET["echostr"])){
            $tmpArr = array($token, $timestamp, $nonce);
            sort($tmpArr);
            $tmpStr = implode( $tmpArr );
            $tmpStr = sha1( $tmpStr );

            if( $tmpStr == $signature ){
                return true;
            }else{
                return false;
            }
        }
        return true;
    }


    public function wxJsConfig(Request $request) {
        $url = $request->input('url','');
        $ticket = $this->getJSTicket('wxJsTicket');
        if (empty($ticket)) {
            return $this->errorReturn(10002,'获取ticket失败');
        }
        $timestamp = time();
        $nonceStr = $this->createNonceStr();
        $signature = array(
            "appId"     => config('wxxcx.wechat_appid'),
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "jsapi_ticket" => $ticket,
            "signature" => sha1("jsapi_ticket=$ticket&noncestr=$nonceStr&timestamp=$timestamp&url=$url"),
            "jsApiList" => array(
                'onMenuShareTimeline',
                'onMenuShareAppMessage',
                'onMenuShareQQ',
                'onMenuShareQZone',
                'getNetworkType',
                'openLocation',
                'getLocation',
                'hideOptionMenu',
                'showOptionMenu',
                'closeWindow',
                'hideMenuItems',
                'showMenuItems',
                'hideAllNonBaseMenuItem',
                'showAllNonBaseMenuItem',
                'scanQRCode',
                'chooseWXPay',
                'chooseImage',
                'previewImage',
                'uploadImage',
                'downloadImage'
            )
        );
        return $this->successReturn(200,$signature);
    }

    public function getJSTicket($type) {
        //通过缓存获取jsticket信息
        $ticket = Redis::get($type);
        if(!empty($ticket)) {
            return $ticket;
        }
        //微信接口获取jsticket信息
        $access_token = $this->getAccessToken('jsConfigAccessToken');
        if (empty($access_token)) {
            return '';
        }
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=${access_token}&type=jsapi";
        $content = $this->curl_get_https($url);
        if (empty($content)) {
            return '';
        }
        $content = json_decode($content, true);
        if (empty($content) || !isset($content['ticket'])) {
            return '';
        }
        //将新生成的jsticket加入到缓存中
        Redis::set($type,$content['ticket']);
        Redis::expire($type,$content['expires_in']-200);
        return $content['ticket'];
    }

    private function getAccessToken($key) {
        // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
        $access_token = Redis::get($key);
        if(!empty($access_token)) {
            return $access_token;
        }
        $appid = config('wxxcx.wechat_appid');
        $appSecret = config('wxxcx.wechat_appsecret');
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=${appid}&secret=${appSecret}";
        $res = json_decode($this->curl_get_https($url),true);
        if (empty($res) || !isset($res['access_token'])) {
            return '';
        }
        Redis::set($key,$res['access_token']);
        Redis::expire($key,$res['expires_in']-200);
        return $access_token;
    }

    function curl_get_https($url){
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);     //返回api的json对象
        //关闭URL请求
        curl_close($curl);
        return $res;    //返回json对象
    }

    public function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function getWithdraws(Request $request)
    {
        $token = $request->input('token', '');
        $uid = $request->input('uid', '');

        $account = client_account::where('uid', $uid)->first();
        if(!empty($account)){
            return view('wechat/withdraw')->with('token', $token)->with('uid', $uid);
        } else {
            return view('wechat/account')->with('token', $token)->with('uid', $uid);
        }
    }



    /**
     * 用户短信发送（验证手机号进行更新）
     * @param token string
     * @param telephone string
     * @return boolean
     */
    //public function postSmsSeed(Request $request, $uid)
    //{
    //    $telephone = $request->input('telephone', '');
    //    $ksyun_sms = new ksyun_sms();
    //    $telephone = 16601104706;
    //    $ksyun_sms->send($telephone);die;	
    //    $token = JWTAuth::getToken();

    //    if( !preg_match('/^1[3|4|5|6|7|8|9]\d{9}$/', $telephone)) {
    //        $return['status_code'] = "10002";
    //        $return['error_message'] = "手机号格式不正确";
    //        return response()->json($return);
    //    }

    //    $clients = $this->UserInfo($token);
    //    if (empty($token) || $clients['id'] != $uid) {
    //        $return['status_code'] = "10001";
    //        $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
    //        return response()->json($return);
    //    }

    //    // 短信验证码生成发送
    //    $code = mt_rand(100000,999999);
    //    Redis::set('code_'.$uid, $code);
    //    Redis::expire('code_'.$uid, 1800);
//  //      $content = '【给料小程序】提现验证码'.$code.'，首次提现免费！半小时内有效！为保障资金安全，请勿将验证码透露给他人。';
    //    $content = '提现验证码'.$code.'，半小时内有效！为保障资金安全，请勿将验证码透露给他人。【给料小程序】';
    //    $day = date("Y-m-d");

    //    $count = sms_log::where("uid", $uid)->where("createtime", 'like', $day.'%')->count();
    //    if( $count >= 3 ) {
    //        $return['status_code'] = "10003";
    //        $return['error_message'] = "每天最多发送三次短信验证";
    //        return response()->json($return);
    //    }

    //    $rrid = $this->seed_sms($telephone, $content);

    //    // 短信记录添加
    //    $smsLog['uid'] = $clients['id'];
    //    $smsLog['telephone'] = $telephone;
    //    $smsLog['description'] = $content;
    //    $smsLog['createtime'] = date('Y-m-d H:i:s');
    //    sms_log::create($smsLog);

    //    return response()->json(['status_code' => '200']);

    //}


    public function postSmsSeed(Request $request, $uid)
    {
        $telephone = $request->input('telephone', '');
        $token = JWTAuth::getToken();

        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

	      //$telephone = '16601104706';
        //$uid = '1234';
        return $this->seed_sms($telephone, $uid);
        
        //=========Ucloud短信实现==============
        /*$code = mt_rand(100000,999999);
        $res = $this->seed_sms($telephone, $code);
        if ($res['RetCode'] == 0) {
          $return = ['status_code' => 200, 'error_message' => ''];
        } else {
          \Log::INFO("短信发送失败:" . json_encode($res));
          $return = ['status_code' => 10051, 'error_message' => '短信发送失败'];
        }
        return response()->json($return);*/
    }


    /**
     * 短信验证
     * @param token string
     * @param sms string
     * @param telephone string
     * @return boolean
     */
    public function postSmsVerify(Request $request, $uid)
    {
        $token = JWTAuth::getToken();
        $sms = $request->input('sms', '');
        $telephone = $request->input('telephone', '');

        if( empty($sms) || empty($telephone) ) {
            $return['status_code'] = "10002";
            $return['error_message'] = "验证码或手机号为空";
            return response()->json($return);
        }

        //$n = preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $telephone) ? true : false;
        if( !preg_match('/^1[3|4|5|6|7|8|9]\d{9}$/', $telephone)) {
            //if( !$n ) {
            $return['status_code'] = "10003";
            $return['error_message'] = "请填写正确的手机号格式";
            return response()->json($return);
        }

        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $code = Redis::get("code_".$uid);
        if($sms != $code) {
            $return['status_code'] = "10004";
            $return['error_message'] = "验证码错误";
            return response()->json($return);
        }
        $status = decbin($clients['status']);
        $oldStatus = sprintf('%08d', $status);
        $newStatus = substr_replace($oldStatus, 1, -3, 1);
        $newStatusChange = bindec((int)$newStatus);

        client::where("id", $uid)->update(['telephone' => $telephone, 'status' => $newStatusChange]);

        return response()->json(['status_code' => '200']);
    }


    /**
     * 获取一个用户的所有料接口
     */
    public function getSourceList(Request $request, $uid)
    {
        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $lastid = $request->input('lastid', '-1');

        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '20');
        $title = $request->input('title', '');
        $sort = $request->input('sort', '');
        $sort = json_decode($sort, True);
        if( empty($sort['soldnum']) ) {
            $sort['soldnum'] = 0;
        }
        if( empty($sort['createtime']) ) {
            $sort['createtime'] = 0;
        }
        $offset = $page * $numberpage;

        $query = source::select('source.sid', 'source.id', 'source.title', 'source.thresh', 'source.pack_type', 'source.pack_day', 'source.play_end', 'source.play_time', 'source.free_watch',  'source.createtime', 'source.status', 'source.order_status', 'source.is_check', 'source.is_notice', 'source.is_recommend', 'source.price');

        if($title){
            $query->where('source.title','like','%'.$title.'%');
        }

        if ($lastid == '-1') {
            $query->offset($offset);
        }
        $query->limit($numberpage);
        $query->where('uid', $uid);
        $query->whereRaw("((substring(bin(status), -2, 1)) <> 1 or (substring(bin(status), -2, 2)) = '11'  or (status = 9) )");
        $query->whereRaw('((substring(bin(status), -4, 1)) <> 1 or (status = 9))');

        if( $sort['createtime'] != 0)
            if ($lastid != '-1' && !empty($lastid)) {
                if ($sort['createtime'] == 1) { // asc
                    $query->where('createtime', '>', $lastid);
                } else {
                    $query->where('createtime', '<', $lastid);
                }
            }
        $query->orderby('createtime', $sorts[$sort['createtime']]);
        if( $sort['soldnum'] != 0)
            $query->orderby('soldnumber', $sorts[$sort['soldnum']]);
        $list = $query->get();
        $list = $list->ToArray();

        foreach ( $list as $key => $value ) {
            //$soldNumber = source_extra::where('id',$value['id'])->value('soldnumber');
	    $soldNumber = order::select()->where('sid', $value['id'])->whereRaw("orderstatus & 1")->count();
            $list[$key]['soldnumber'] = $soldNumber;

            //定时下架
            $extra_info  = source_extra::where('sid', $value['id'])->first();
            if ($extra_info['is_sold_out'] && $extra_info['sold_out_time']){
                if ($value['status'] == 3) {
                    source_extra::where('sid', $value['sid'])->update(['is_sold_out' => 0]);
                }
                if ($value['status'] == 0) {
                    if (strtotime($extra_info['sold_out_time']) < time()) {
                        source_extra::where('sid', $value['sid'])->update(['is_sold_out' => 0]);
                        source::where('sid', $value['sid'])->update(['status' => 3]);
                        $list[$key]['status'] = 3;
                    }
                }
            }
            //定时下架
            //定时公开
            if ($extra_info['open_time'] && $extra_info['is_open']) {
                if ($value['free_watch']) {
                    source_extra::where('sid', $value['sid'])->update(['is_open' => 0]);
                } else {
                    if (strtotime($extra_info['open_time']) < time()) {
                        source_extra::where('sid', $value['sid'])->update(['is_open' => 0]);
                        source::where('sid', $value['sid'])->update(['free_watch' => 1]);
                        $list[$key]['free_watch'] = 1;
                    }
                }
            }
            //定时公开
            $status = decbin($value['status']);
            $newStatus = sprintf('%08d', $status);
            $list[$key]['status'] = strrev(strval("{$newStatus}"));
            $list[$key]['is_recommend'] = $value['is_recommend'] ? true : false;

            $list[$key]['disable_recommend'] = 0;
            if (time() - strtotime($value['createtime']) >= 24 * 60 * 60) {
	            if ($value['pack_type'] != 1) {
                    	$list[$key]['disable_recommend'] = 1;
	            }
            }

            if ($value['pack_type'] == 3) {
              if ($value['play_end'] == 1) {
                if (time() >= $value['play_time'] + 60 * 60) {
                  $list[$key]['disable_recommend'] = 1;
                }
              }
            }

            if ($value['status'] == 3) {
                  $list[$key]['disable_recommend'] = 1;
            }
            //if ($value['pack_type'] == 2 || $value['pack_type'] == 3) {
            //  if ($value['free_watch'] == 1 && time() >= $value['play_time'] + 3 * 3600) {
            //    $list[$key]['disable_recommend'] = 1;
            //  }
            //}

            if ($value['order_status'] != 0 || $value['is_check'] != 1) {
              $list[$key]['disable_recommend'] = 1;
            }
            $list[$key] = source_sensitives::apply($list[$key]);
            //if ($value['pack_type'] == 0 && $value['price'] > 0) {
            //    $list[$key]['pack_type'] = 5;
            //}
        }
        $list = array_values($list);

        $pagenum = 0;
        // -2 -4
        if ($lastid == '-1') {
            $query = source::select('source.sid', 'source.id', 'source.title', 'source.thresh',  'source.createtime', 'source.status');
            $query->where('uid', $uid);
            $query->whereRaw('substring(bin(status), -2, 1) <> 1');
            $query->whereRaw('substring(bin(status), -4, 1) <> 1');
            if( $sort['createtime'] != 0)
                $query->orderby('createtime', $sorts[$sort['createtime']]);
            if( $sort['soldnum'] != 0)
                $query->orderby('soldnumber', $sorts[$sort['soldnum']]);
            $count = $query->count();

            if ( $count == 0){
                $pagenum = 0;
            } else {
                $pagenum = ceil($count/$numberpage);
            }
        }

        $return['status_code'] = '200';
        $return['pagenum'] = $pagenum;
        $return['data'] = $list;
        return response()->json($return);
    }

    /**
     * 用户精选料
     */
    public function getRecommendSource(Request $request, $uid) 
    {
      $token = JWTAuth::getToken();
      $clients = $this->UserInfo($token);
      if (empty($token) || $clients['id'] != $uid) {
        $return['status_code'] = 10001;
        $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
        return response()->json($return);
      }

      $lastid = $request->input('lastid', '-1');
      $pageSize = $request->input('pagesize', '20');

      $query = source::select('sid', 'id', 'title', 'sub_title', 'thresh', 'pack_type', 'pack_day', 'play_end', 'play_time', 'free_watch', 'createtime', 'status', 'order_status', 'is_check', 'is_notice', 'is_recommend', 'recommend_sort', 'price');
      $query->where('uid', $uid)->where('is_recommend', 1)->where('status', 0);
      if ($lastid != '-1' && !empty($lastid)) {
        $query->where('recommend_sort', '<', $lastid);
      }
      $query->orderby('recommend_sort', 'desc');
      $query->limit($pageSize);
      $list = $query->get()->ToArray();

      foreach ( $list as $key => $value ) {
        //$soldNumber = source_extra::where('id',$value['id'])->value('soldnumber');
	$soldNumber = order::select()->where('sid', $value['id'])->whereRaw("orderstatus & 1")->count();
        $list[$key]['soldnumber'] = $soldNumber;
        $sort_flag = floor($value['recommend_sort'] / 10000000000);
        $list[$key]['is_top'] = $sort_flag ? 1 : 0;
        $list[$key]['is_recommend'] = $value['is_recommend'] ? true : false;

        $list[$key]['disable_recommend'] = 0;
        if (time() - strtotime($value['createtime']) >= 24 * 60 * 60) {
	        if ($value['pack_type'] != 1) {
                	$list[$key]['disable_recommend'] = 1;
	        }
        }

        if ($value['pack_type'] == 3) {
          if ($value['play_end'] == 1) {
            if (time() >= $value['play_time'] + 60 * 60) {
              $list[$key]['disable_recommend'] = 1;
            }
          }
        }

        //if ($value['pack_type'] == 2 || $value['pack_type'] == 3) {
        //  if ($value['free_watch'] == 1 && time() >= $value['play_time'] + 3 * 3600) {
        //    $list[$key]['disable_recommend'] = 1;
        //  }
        //}

        if ($value['order_status'] != 0) {
          $list[$key]['disable_recommend'] = 1;
        }
        $list[$key] = source_sensitives::apply($list[$key]);

        //if ($value['pack_type'] == 0 && $value['price'] > 0) {
        //    $list[$key]['pack_type'] = 5;
        //}

      }
      $list = array_values($list);
      
      return response()->json(['status_code' => 200, 'data' => $list]);
    }


    /**
     * 用户购买的料列表
     */
    public function getBuyedSource(Request $request, $uid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            return $this->errorReturn(10001,"token 失效或异常， 以正常渠道获取重试");
        }
        $lastid = $request->input('lastid', '-1');

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '20');
        $title = $request->input('title', '');
        $sort = $request->input('sort', '');
        $sort = json_decode($sort, True);
        if( empty($sort['buytime']) ) {
            $sort['buytime'] = 0;
        }
        $offset = $page * $numberpage;



        $query = order::select('order.sid',  'order.createtime as buytime', 'order.orderstatus','source.id','source.title','source.pack_day','source.pack_type','source.order_status', 'source.price')->LeftJoin('source', 'source.sid', 'order.sid');
        $query->where('order.buyerid', $uid);
        $query->where('order.orderstatus', 1);

        if($title){
            $query->where('source.title','like','%'.$title.'%');
        }

        if ($lastid == '-1') {
            $query->offset($offset);
        }
        $query->limit($numberpage);
        if( $sort['buytime'] != 0) {
            $query->orderby('order.createtime', $sorts[$sort['buytime']]);
            if ($lastid != '-1' && !empty($lastid)) {
                if ($sort['buytime'] == 1) { // asc
                    $query->where('order.createtime', '>', $lastid);
                } else {
                    $query->where('order.createtime', '<', $lastid);
                }
            }
        }
        $orders = $query->get();
        $orders = $orders->ToArray();
        $orders = source_sensitives::apply($orders);


        /*

        $query = order::select('sid',  'order.createtime as buytime', 'order.orderstatus');
        $query->where('buyerid', $uid);
        $query->where('orderstatus', 1);
        //$query->whereRaw("substring(bin(orderstatus), -2, 1)= 0");    //未删除状态
        //$query->whereRaw("substring(bin(orderstatus), -1, 1) = 1");   //已支付状态
        if ($lastid == '-1') {
            $query->offset($offset);
        }
        $query->limit($numberpage);
        if( $sort['buytime'] != 0) {
            $query->orderby('order.createtime', $sorts[$sort['buytime']]);
            if ($lastid != '-1' && !empty($lastid)) {
                if ($sort['buytime'] == 1) { // asc
                    $query->where('order.createtime', '>', $lastid);
                } else {
                    $query->where('order.createtime', '<', $lastid);
                }
            }
        }
        $orders = $query->get();
        $orders = $orders->ToArray();


        */
        //foreach ( $orders as $key => $value ) {
        //    if ($value['pack_type'] == 0 && $value['price'] > 0) {
        //        $orders[$key]['pack_type'] = 5;
        //    }
        //}


        $pagenum = 0;
        if ($lastid == '-1') {
            $query = order::select('createtime');
            $query->where('buyerid', $uid);
            $query->where('orderstatus', 1);
            //$query->whereRaw("substring(bin(orderstatus), -2, 1) = 0");    //未删除状态
            //$query->whereRaw("substring(bin(orderstatus), -1, 1) = 1");   //已支付状态
            $count = $query->count();

            if ( $count == 0 ) {
                $pagenum = 0;
            } else {
                $pagenum = ceil($count/$numberpage);
            }
        }

        $return['status_code'] = '200';
        $return['pagenum'] = $pagenum;
        $return['data'] = $orders;

        return response()->json($return);
    }


    /**
     * 获得一个用户的财务等基本信息
     */
    public function getBrief($uid)
    {
        if($uid == '-101'){
            $data['todayEarnings'] = 0;
            $data['withdrawing'] = floatval(0);
            $data['withdrawed'] = floatval(0);
            $data['rate'] = 0.05;
            $data['balance'] = 0.00;

            $return['status_code'] = '200';
            $return['data'] = $data;
            return response()->json($return);
        }
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }


        $data = client::select('client.status', 'client.id', 'client.telephone')
            ->where('client.id', $uid)
            ->first();


        $userExtraInfo = client_extra::select('balance','withdrawing','total','withdrawed')->where('id',$data['id'])->first();
        $rate = client_rate::where('uid',$data['id'])->value('rate');
        $data['balance'] = $userExtraInfo['balance'];
        $data['withdrawing'] = $userExtraInfo['withdrawing'];
        $data['total'] = $userExtraInfo['total'];
        $data['withdrawed'] = $userExtraInfo['withdrawed'];
        $data['rate'] = $rate;

        $status = $this->decAnalysis($data['status'], 3);
        $data['status'] = $status;

        // 获取用户今日收益
        $date = date('Y-m-d', time());
        $profit = order::select('price')->where('selledid', $uid)
            ->where('pack_type', '<>', 2)
            ->where('createtime', 'like', $date.'%')
            ->whereRaw("orderstatus & 1")
            ->get();


        $today = 0;
        foreach ( $profit as $key => $value) {
            $today += $value['price'];
        }
        //获取今日红单
        $redOrder = order::select('sid','price')->where('selledid', $uid)
            ->where('pack_type', 2)
            ->where('createtime', 'like', $date.'%')
            ->whereRaw("orderstatus & 1")
            ->get();


        foreach ( $redOrder as $key => $value) {
            //检查资源是否为红单
            $sourceInfo=source::select('order_status')->where('sid',$value['sid'])->first();
            if($sourceInfo['order_status']==1){
                $today += $value['price'];
            }
        }


        $data['todayEarnings'] = sprintf("%0.2f", floatval($today));
        $data['withdrawing'] = floatval($data['withdrawing']);
        $data['withdrawed'] = floatval($data['withdrawed']);
        if( $data['rate'] != 0 ) {
            $data['rate'] = $data['rate'] / 100;
        }else{
            $data['rate'] = 0;
        }
        if(!isset($data['balance'])) {
            $data['balance'] = 0.00;
        }

        $data['rate'] = 0.01;

        //关注数，粉丝数
		$fansNum = follow::where('star', $uid)->where('status',1)->count();
		$followNum = follow::where('fans', $uid)->where('status',1)->count();
		$data['followNum'] = $followNum;
		$data['fansNum'] = $fansNum;
        $return['status_code'] = '200';
        $return['data'] = $data;

        $data['rate'] = 0.03;

        return response()->json($return);
    }


    public function complaints(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id'])) {
            return $this->errorReturn(10001,"token 失效或异常， 以正常渠道获取重试");
        }

        $sid = $request->input('sid','');
        $s_uid = $request->input('suid','');
        $com_type = $request->input('type','');
        if(!is_array($com_type)){
            $com_type = json_decode($com_type,true);
        }
        $telephone = $request->input('telephone','');
        $description = $request->input('description','');

        if(empty($com_type) || count($com_type) == 0){
            return $this->errorReturn(10003,"内容不可为空");
        }

        $type = implode(',',$com_type);

        $complaint['uid'] = $clients['id'];
        $complaint['sid'] = $sid;
        $complaint['suid'] = $s_uid;
        $complaint['telephone'] = $telephone;
        $complaint['status'] =0;
        $complaint['type'] = $type;
        $complaint['description'] = $description;
        $complaint['createtime'] = date('Y-m-d H:i:s');
        $complaint['modifytime'] = date('Y-m-d H:i:s');
        DB::table('complaints')->insert($complaint);

        $return['status_code'] = '200';
        return response()->json($return);
    }


    /**
     * 用户登陆记录添加
     * @param array $log uid 用户id  register 是否为第一次登陆 0 不是， 1 是
     * @return boolean
     */
    public function log($log)
    {
        $log['createtime'] = date("Y-m-d H:i:s", time());
        Client_log::create($log);
        return True;
    }

    /**
     * 用户提现信息填写
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function postAccount(Request $request)
    {
        //用户ID
        $uid = $request->input('uid', '');

        $token = JWTAuth::getToken();

        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = "10001";
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        //姓名
        $name = $request->input('name', '');
        if( empty($name)) {
            $return['status_code'] = "10002";
            $return['error_message'] = "姓名为空";
            return response()->json($return);
        }

        //身份证
        $idcard = $request->input('idcard', '');
        if( empty($idcard)) {
            $return['status_code'] = "10002";
            $return['error_message'] = "身份证号为空";
            return response()->json($return);
        }

        //校验身份证格式
        if(!$this->check_IdCard($idcard)){
            $return['status_code'] = "10002";
            $return['error_message'] = "身份证格式错误";
            return response()->json($return);
        }

        //开户银行
        $bank = $request->input('bank', '');
        if( empty($bank)) {
            $return['status_code'] = "10002";
            $return['error_message'] = "开户银行为空";
            return response()->json($return);
        }

        //银行卡号
        $bank_number = $request->input('bank_number', '');
        if( empty($bank_number)) {
            $return['status_code'] = "10002";
            $return['error_message'] = "银行卡号为空";
            return response()->json($return);
        }

        //银行卡号校验
        // if(!$this->check_bankCard($bank_number)){
        //     $return['status_code'] = "10002";
        //     $return['error_message'] = "银行卡号格式错误";
        //     return response()->json($return);
        // }

        //支付宝账号
        $alipay_number = $request->input('alipay_number', '');
        if( empty($alipay_number)) {
            $return['status_code'] = "10002";
            $return['error_message'] = "支付宝账号为空";
            return response()->json($return);
        }

        $accountList = client_account::where('uid', $uid)->get()->toArray();
        if(!empty($accountList)){
            $return['status_code'] = "10002";
            $return['error_message'] = "您已经录入过提现信息了";
            return response()->json($return);
        }

        $bank_account = [];
        $bank_account['uid'] = $uid;
        $bank_account['type'] = 1;
        $bank_account['name'] = $name;
        $bank_account['id_card'] = $idcard;
        $bank_account['account'] = $bank_number;
        $bank_account['bank'] = $bank;
        $bank_account['create_time'] = time();
        client_account::create($bank_account);

        $alipay_account = [];
        $alipay_account['uid'] = $uid;
        $alipay_account['type'] = 2;
        $alipay_account['name'] = $name;
        $alipay_account['id_card'] = $idcard;
        $alipay_account['account'] = $alipay_number;
        $alipay_account['bank'] = "支付宝";
        $alipay_account['create_time'] = time();
        client_account::create($alipay_account);

        $return['status_code'] = '200';
        $return['data'] = [];
        return response()->json($return);
    }

    private function check_IdCard($value)
    {
        if (!preg_match('/^\d{17}[0-9xX]$/', $value)) { //基本格式校验
            return false;
        }

        $parsed = date_parse(substr($value, 6, 8));
        if (!(isset($parsed['warning_count'])
            && $parsed['warning_count'] == 0)) { //年月日位校验
            return false;
        }

        $base = substr($value, 0, 17);

        $factor = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];

        $tokens = ['1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2'];

        $checkSum = 0;
        for ($i=0; $i<17; $i++) {
            $checkSum += intval(substr($base, $i, 1)) * $factor[$i];
        }

        $mod = $checkSum % 11;
        $token = $tokens[$mod];

        $lastChar = strtoupper(substr($value, 17, 1));

        return ($lastChar === $token); //最后一位校验位校验
    }

    private function check_bankCard($s){
        $n = 0;
        for ($i = strlen($s); $i >= 1; $i--) {
            $index=$i-1;
            //偶数位
            if ($i % 2==0) {
                $n += $s{$index};
            } else {//奇数位
                $t = $s{$index} * 2;
                if ($t > 9) {
                    $t = (int)($t/10)+ $t%10;
                }
                $n += $t;
            }
        }
        if(($n % 10) == 0){
            return true;
        }else{
            return false;
        }



        // $arr_no = str_split($card_number);
        // $last_n = $arr_no[count($arr_no)-1];
        // krsort($arr_no);
        // $i = 1;
        // $total = 0;
        // foreach ($arr_no as $n){
        //     if($i%2==0){
        //         $ix = $n*2;
        //         if($ix>=10){
        //             $nx = 1 + ($ix % 10);
        //             $total += $nx;
        //         }else{
        //             $total += $ix;
        //         }
        //     }else{
        //         $total += $n;
        //     }
        //     $i++;
        // }
        // $total -= $last_n;
        // $x = 10 - ($total % 10);
        // if($x == $last_n){
        //     return true;
        // }else{
        //     return false;
        // }
    }

    public function modifySignature(Request $request, $uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $signature = $request->input('signature', '');
        $signature = trim($signature);
        if (mb_strlen($signature) > 30) {
            $return['status_code'] = 10002;
            $return['error_message'] = "个性签名过长";
            return response()->json($return);
        }
        if (empty($signature)) {
            $return['status_code'] = 10003;
            $return['error_message'] = "个性签名不能为空";
            return response()->json($return);
        }
        $exists = client_signature_log::where('uid', $uid)->where('signature', $signature)->first();
        if ($exists) {
            if ($exists['status'] != 2) {
                client::where('id', $uid)->update(['signature' => $signature]);
            }
            $return['status_code'] = 200;
            return response()->json($return);
        }
        $insert_data = ['uid' => $uid, 'signature' => $signature, 'nickname' => $clients['nickname']];
        client_signature_log::insert($insert_data);
        client::where('id', $uid)->update(['signature' => $signature]);
        $return['status_code'] = 200;
        return response()->json($return);
    }

    public function getSignature($uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $data = client::where('id', $uid)->select('signature')->first();
        $signature = $data['signature'];
        if (empty($signature)) {
            $signature = $this->default_signature;
        }
        $return['status_code'] = 200;
        $return['data'] = $signature;
        return response()->json($return);
    }

    public function userHome($uid) {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (empty($token) || !isset($clients['id']) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }
        $data = client::select('nickname', 'avatarurl', 'signature', 'id')
            ->where('id', $uid)
            ->first();
        if (!$data) {
            $return['status_code'] = 10002;
            $return['error_message'] = "用户不存在";
            return response()->json($return);
            
        }
        $data = $data->toArray();
        if (empty($data['signature'])) {
            $data['signature'] = $this->default_signature;
        }
        $url = 'qrcode/homepage-' . $uid . '.jpg';
        $uuid = Uuid::uuid1();
        $only_url = 'qrcode/homepage-' . $uid . $uuid->getHex()  . '.jpg';
        $data['url'] = $url;
        $data['only_url'] = $only_url;
        $completed_url = $this->makeHomePicture($data);
        $return['status_code'] = 200;
        $return['data'] = ['img_url' => $completed_url];
        return response()->json($return);
    }

    private function generateQrcode($follow_id) {
        $url = config('constants.frontend_domain') . '/sellercode?followid=' . $follow_id;
        $qrCode = new \Endroid\QrCode\QrCode($url);
        $qrCode->setSize(260);
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(10);
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
        $qrCode->setLogoPath('image/logo.jpg');
        $qrCode->setLogoWidth(30);
        $qrCode->setRoundBlockSize(true);
        $qrCode->setValidateResult(false);

        //header('Content-Type: ' . $qrCode->getContentType());
        //$qrCode->writeFile('qrcode_czh.png');
        return $qrCode->writeString();
        //return $img;

    }

    private function makeHomePicture($data) {
        $follow_id = $data['id'];
        $nickname = $data['nickname'];
        $avatarurl = $data['avatarurl'];
        $signature = $data['signature'];
        $url = $data['url'];
        $only_url = $data['only_url'];
        $completed_url = config('qiniu.host') . '/' . $only_url;
        $img = Image::make('image/home_back.png');
        $wxxcx = new Wxxcx();
        $source_con = new SourcesController($wxxcx);
        $img->text($nickname, 375, 239, function($font) {
            $font->file('ht.ttf');
            $font->size(30);
            $font->color("#333333");
            $font->align('center');
            //$font->angle(315);
        });
        $sign_length = mb_strlen($signature);
        $next_line = '';

        if ($sign_length > 15) {
            $next_line = mb_substr($signature, 15, $sign_length);
            $signature = mb_substr($signature, 0, 15); 
        }
        $img->text($signature, 182, 682, function($font) {
                $font->file('ht.ttf');
                $font->size(26);
                $font->color("#666666");
                $font->align('left');
                $font->valign('bottom');
                //$font->angle(315);
        });
        if ($next_line) {
            $img->text($next_line, 182, 720, function($font) {
                    $font->file('ht.ttf');
                    $font->size(26);
                    $font->color("#666666");
                    $font->align('left');
                    $font->valign('bottom');
                    //$font->angle(315);
            });
        }


        $avatar = $this->circular($avatarurl, $source_con);
        $avatar = Image::make($avatar);
        $img->insert($avatar, 'top-left', 335, 115, function ($top) {
            $top->align("center");
        });

        $qrcode = $this->generateQrcode($follow_id);
        $qrcode = Image::make($qrcode)->widen(260, function ($constraint) {
            $constraint->upsize();
        });
        $qrBg = new Imagick();
        $qrBg->newImage(270, 270, new ImagickPixel('transparent'));
        $qrBg->setImageFormat('png');
        $draw = new ImagickDraw();
        $draw->setStrokeColor('#F2F2F2');
        $draw->setFillColor('#F2F2F2');
        $draw->roundRectangle(0, 0, 270, 270, 10, 10);
        $qrBg->drawImage($draw);
        $qrBg = Image::make($qrBg);
        $qrBg->insert($qrcode, 'top-left', 5, 5);
        $img->insert($qrBg, 'top-left', 240, 320);

        $base_path = $source_con->checkPathExist();
        $path_info = explode('/', $only_url);
        $local_path = $base_path . '/' . $path_info[1];
        $img->save($local_path);
        $source_con->upload2Qiniu($only_url, $local_path);
        unlink($local_path);
        return $completed_url;
    }

    private function circular($avatar, $source_con) {
        //$img = file_get_contents($avatar);
        $avatar = $source_con->curlImg($avatar);
        if (!$avatar || empty($avatar)) {
            $avatar = file_get_contents('image/avatar.jpg');
        }
        //header("Content-Type: image/jpeg");
        $image = new Imagick();
        $image->readImageBlob($avatar);
        $image->thumbnailImage(80, 80, true);
        $image->setImageFormat('png');
        // $image->roundCorners($image->getImageWidth() / 2, $image->getImageHeight() / 2);

        $mask = $source_con->circularMask(80, 80, $image->getImageWidth() / 2, $image->getImageHeight() / 2);

        // apply mask
        $image->compositeImage($mask, Imagick::COMPOSITE_DSTIN, 0, 0);

        return $image;
    }

}
