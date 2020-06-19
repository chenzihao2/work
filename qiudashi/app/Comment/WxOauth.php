<?php

namespace App\Comment;

/**
 * 微信授权登录获取用户信息
 * @param $appid 微信应用appid
 * @param $appsecret 微信应用appsecret
 */
class WxOauth
{

    private $appid = "";          // appid
    private $appsecret = "";      // appsecret
    public  $error = [];          // 错误信息
    const GET_ACCESS_TOKEN_URL = 'https://api.weixin.qq.com/sns/oauth2/access_token'; // 获取access_token url
    const GET_USER_INFO_URL = 'https://api.weixin.qq.com/sns/userinfo';               // 获取用户信息url
    const GET_REFRESH_URL = 'https://api.weixin.qq.com/sns/oauth2/refresh_token';   //刷新access_token
    const GET_CODE = 'https://open.weixin.qq.com/connect/oauth2/authorize';  // 获取code(网页授权使用)
    public function __construct($appid, $appsecret) {
        if($appid && $appsecret){
               $this->appid = $appid;
            $this->appsecret = $appsecret;
        }
    }
    /**
     * 微信登录
     * @param  string $code 客户端传回的code(网页授权时调用getCode方法获取code,微信会把code返回给redirect_uri)
     * @return array 用户信息
     * @example  错误时微信会返回错误码等信息 eg:{"errcode":, "errmsg":""}
     */
    public function wxLogin($code){
        $token_info = $this->getToken($code);

        if (isset($token_info['errcode'])) {
            $this->error = $token_info;
            return false;
        }
        $user_info = $this->getUserinfo($token_info['openid'], $token_info['access_token']);
        if (isset($user_info['errcode'])) {
            $this->error = $user_info;
            return false;
        }
        return $user_info;
    }
    /**
     * 用户同意授权获取code
     * @param  string $redirect_uri 授权后重定向的回调链接地址，需要urlEncode处理
     * @return  redirect
     */
    public function getCode($redirect_uri){
        $uri = $this->combineURL(self::GET_CODE, [
            'appid'         => $this->appid,
            'scope'         => 'SCOPE',
            'response_type' => 'code',
            'redirect_uri'  => urlEncode($redirect_uri),
            'state'         => 'STATE#wechat_redirect',
        ]);
        header('Location: ' . $uri, true);
    }
    /**
     * 获取token和openid
     * @param  string $code 客户端传回的code
     * @return array 获取到的数据
     */
    public function getToken($code){
        $get_token_url = $this->combineURL(self::GET_ACCESS_TOKEN_URL, [
            'appid'      => $this->appid,
            'secret'  => $this->appsecret,
            'code'       => $code,
            'grant_type' => 'authorization_code'
        ]);
        $token_info = $this->httpsRequest($get_token_url);
        return json_decode($token_info, true);
    }
    /**
     * 刷新access token并续期
     * @param  string $refresh_token 用户刷新access_token
     * @return array
     */
    public function refreshToken($refresh_token){
        $refresh_token_url = $this->combineURL(self::GET_REFRESH_URL, [
            'appid'         => $this->appid,
            'refresh_token' => $refresh_token,
            'grant_type'    => 'refresh_token'
        ]);
        $refresh_info = $this->httpsRequest($refresh_token_url);
        return json_decode($refresh_info, true);        
    }
    /**
     * 获取用户信息
     * @param  string $openid       用户的标识
     * @param  string $access_token 调用接口凭证
     * @return array 用户信息
     */
    public function getUserinfo($openid, $access_token){
        $get_userinfo_url = $this->combineURL(self::GET_USER_INFO_URL, [
            'openid'          => $openid,
            'access_token'  => $access_token,
            'lang'             => 'zh_CN'
        ]);
        $user_info = $this->httpsRequest($get_userinfo_url);
        return json_decode($user_info, true);
    }
    /**
     * 拼接url
     * @param string $baseURL   请求的url
     * @param array  $keysArr   参数列表数组
     * @return string           返回拼接的url
     */
    public function combineURL($baseURL, $keysArr){
        $combined = $baseURL . "?";
        $valueArr = array();
        foreach($keysArr as $key => $val){
            $valueArr[] = "$key=$val";
        }
        $keyStr = implode("&", $valueArr);
        $combined .= ($keyStr);
        return $combined;
    }
    /**
     * 获取服务器数据
     * @param string $url  请求的url
     * @return  unknown    请求返回的内容
     */
    public function httpsRequest($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }
}