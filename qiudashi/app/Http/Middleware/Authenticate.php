<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Closure;
use App\Respository\Jwt;
//use Tymon\JWTAuth\Facades\JWTAuth;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        //if (! $request->expectsJson()) {
        //    return route('login');
        //}
    }

    public function handle_($request, Closure $next, ...$guards) {
        $return = ['code' => 100, 'message' => '', 'data' => []];
        try {
            $user = Jwt::checkToken($request);
        }
        catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            $return['message'] = $e->getMessage();
            return response($return);
        }
        catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            $return['message'] = $e->getMessage();
            return response($return);
        }
        catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            $return['message'] = $e->getMessage();
            return response($return);
        }
        catch (\Exception $e) {
            $return['code'] = 500;
            $return['message'] = $e->getMessage();
            return response($return);
        }
        $request->user_info = $user;
        return $next($request);
    }

    public function handle($request, Closure $next, ...$guards) {
        $return = ['code' => 100, 'message' => '', 'data' => []];
        $request->user_info = ['user_id' => 0, 'token' => ''];
        try {
            $token = $request->header('token');
            if (!$token) {
                return $next($request);
            }
            $user = (new Jwt())->checkOldToken($request);
        }
        catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            $return['message'] = $e->getMessage();
            return response($return);
        }
        catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            $return['message'] = $e->getMessage();
            return response($return);
        }
        catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            $return['message'] = $e->getMessage();
            return response($return);
        }
        catch (\Exception $e) {
            $return['code'] = 500;
            if ($e->getcode()) {
                $return['code'] = $e->getcode();
            }
            $return['message'] = $e->getMessage();
            return response($return);
        }
        $user['user_id'] = $user['uid'];
        $request->user_info = $user;
        return $next($request);
    }
}
