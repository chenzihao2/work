<?php

use Illuminate\Routing\Router;

Admin::registerHelpersRoutes();

Route::group([
    'prefix'        => config('admin.prefix'),
    'namespace'     => Admin::controllerNamespace(),
    'middleware'    => ['web', 'admin'],
], function (Router $router) {

    $router->get('/', 'HomeController@index');
    $router->get('extract/status/', 'ExtractController@status'); // 提现
    $router->resource('clients', ClientsController::class); // 用户
    $router->resource('sources', SourcesController::class); // 资源
    $router->resource('extract', ExtractController::class); // 提现
    $router->resource('orders', OrdersController::class);  // 订单

});
