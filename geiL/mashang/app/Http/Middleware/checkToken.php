<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;

class checkToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = JWTAuth::getToken();
        try {
            if(!$clients = JWTAuth::parseToken()->authenticate()){
                $return['status_code'] = "404";
                $return['error_message'] = "user_not_found";
            }else{
                if (empty($token) || !isset($clients['id'])) {
                    $return['status_code'] = 10001;
                    $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                    return response()->json($return);
                }else{
                    $request->attributes->add($clients);
                    return $next($request);
                }
            }
        } catch (TokenExpiredException $e) {
            $return['status_code'] = "401";
            $return['error_message'] = "TOKEN Expired";
        } catch (TokenInvalidException $e) {
            $return['status_code'] = "401";
            $return['error_message'] = "TOKEN Invalid";
        } catch (JWTException $e) {
            $return['status_code'] = "401";
            $return['error_message'] = "TOKEN Exception";
        }
        return response()->json($return);
    }
}
