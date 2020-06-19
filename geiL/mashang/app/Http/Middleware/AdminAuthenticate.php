<?php

namespace App\Http\Middleware;

use Closure;

class AdminAuthenticate
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
        config(['jwt.user' => '\App\models\admin']);    // 生成token model
        config(['auth.providers.users.model' => \App\models\admin::class]);// token 查询model

        return $next($request);
    }
}
