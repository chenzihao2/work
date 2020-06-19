<?php

namespace App\Http\Middleware\clients;

use Closure;

class change
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
        config(['jwt.user' => '\App\models\client']);    // 生成token model
        config(['auth.providers.users.model' => \App\models\client::class]);// token 查询model

        return $next($request);
    }
}
