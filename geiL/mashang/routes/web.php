<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('wechat/withdraw', function () {
    return view('wechat/withdraw');
});

Route::get('test', function () {
    return view('wechat/test');
});


// H5登陆授权
Route::get("wechat/login", 'WechatController@getLogin')->name('wechat.login');

// 提现
Route::get("extract", 'WechatController@getExtractList')->name('wechat.index');

// 短信发送
Route::post("sms", 'WechatController@postSeedSms')->name('wechat.sms');
Route::post("extract", 'WechatController@postExtractAdd')->name('wechat.extract');

Route::group(['prefix' => 'swagger'], function () {
    Route::get('json', 'SwaggerController@getJSON');
    Route::get('my-data', 'SwaggerController@getMyData');
});



