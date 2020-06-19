<?php

namespace App\Http\Middleware;

use Closure;
use App\Respository\Jwt;

class Authentication
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
        //$token = $request->header('token');
        $response = $next($request);
        $refresh_token = $request->user_info['token'];
        return $response->withHeaders(['authentication' => $refresh_token]);
    }
}
