<?php

namespace App\Http\Middleware;

use Closure;

class Chronography
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
        $requestTime = time();
	//$client_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
	//if ($client_ip == '113.110.147.153') {
		//return response()->json(['status_code' => 503, 'error_message' => 'no privileges']);
	//}
        $response = $next($request);
        $expendtime = time()-$requestTime;
        if($expendtime>=2){
          \Log::INFO("请求：".$request->path()."耗时>>>>>".$expendtime."s");
        }
        return $response;
    }
}
