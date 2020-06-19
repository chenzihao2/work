<?php

namespace App\Respository;

//use JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Respository\FaceUtility;

class Jwt
{

    //生成用户信息的令牌
    public static function setUserToken($user) {
        try{
            return $token = JWTAuth::fromUser($user);
            //$expire = config('jwt.ttl') * 60;
            //return ['token' => $token, 'expire' => $expire];
        } catch (JWTException $e) {
            return $e;
        }
    }

    //校验令牌
    public static function checkToken($request) {
        $token = $request->header('token');
        if (!$token) {
            throw new \Exception('Token 缺少');
        }
        JWTAuth::setToken($token);
        $user = JWTAuth::authenticate();
        if (!$user || !$token) {
            throw new \Exception('Token 异常');
        }
        //response()->withHeaders(['ttttttoken' => $token]);
        return $user;
    }

    //校验令牌 兼容旧项目
    public function checkOldToken($request) {
        $token = $request->header('token');
        if (!$token) {
            throw new \Exception('Token 缺少');
        }
        $utility = new FaceUtility();
        $domain = config('app.old_domain');
        $url = $domain . 'index.php?user_id=&token=&platform=1&p=user&c=login&do=checkTokenForNew&v=2&p=user&token=' . $token;
        $res = $utility->httpRequestOnce($url);
        $result = json_decode($res, 1);
        if ($result['code'] != 200) {
            throw new \Exception($result['message'], $result['code']);
        }
        return $result['data'];
    }
}
