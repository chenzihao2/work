<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) {

    // 用户接口
    $api->group(['namespace' => 'App\Http\Controllers\Api\V1', 'middleware' => ['\App\Http\Middleware\Cors','App\Http\Middleware\clients\change','App\Http\Middleware\Chronography']], function ($api) {

        // 用户相关
        $api->group(['prefix' => 'user'], function($user) {
            $user->post('xcxlogin', 'ClientsController@xcxWxbind');
            $user->get('wxauth', 'ClientsController@wxauth');   // 微信授权
            $user->get('wxsubscribe', 'ClientsController@wxSubscribe');   // 微信订阅消息公众号授权
            $user->post('wxbind', 'ClientsController@postWxbind');   // 绑定微信用户
            $user->get('wechat', 'ClientsController@getWechatLogin');   // 微信公众用户
            $user->get('withdraws', 'ClientsController@getWithdraws');   // 微信公众用户
            $user->post('{id}/login', 'ClientsController@postLogin');   // 微信用户登陆（使用场景少）
            $user->put('{id}', 'ClientsController@putUpdate');   // 微信用户更新
            $user->post('{id}/smssend', 'ClientsController@postSmsSeed');   // 短信发送
            $user->post('{id}/smsverify', 'ClientsController@postSmsVerify');   // 短信验证
            $user->get('{id}/source', 'ClientsController@getSourceList');   // 获取一个用户的所有料列表
            $user->get('{id}/buyedsource', 'ClientsController@getBuyedSource');   // 获取一个用户的购买的料列表
            $user->get('{id}/brief', 'ClientsController@getBrief');   // 获得一个用户的财务等基本信息
            $user->get('{id}/home', 'ClientsController@userHome');   // 获得一个用户的个人主页信息
            $user->get('{id}/modifysign', 'ClientsController@modifySignature');   // 修改个性签名
            $user->get('{id}/getsign', 'ClientsController@getSignature');   // 获取当前的个性签名

            $user->get('{id}/follow', 'FollowController@followList');   // 关注列表
            $user->get('{id}/setfollow', 'FollowController@setFollow');   // 关注操作
            $user->post('{id}/cancelfollow', 'FollowController@cancelFollow');   // 取消关注
            $user->get('{id}/followcheck', 'FollowController@followCheck');   // 检查是否关注

            $user->get('{id}/fans', 'FollowController@fansList');   // 粉丝列表
            $user->post('{id}/cancelfans', 'FollowController@cancelFans');   // 删除粉丝

            $user->post('account', 'ClientsController@postAccount');//填写用户收款信息

            //$user->post('complaints', 'ClientsController@complaints');   // 用户投诉

            $user->get('{id}/devlogin', 'ClientsController@devLogin');
            $user->post('autoregister','ClientsController@autoRegister');

            $user->get('jsconfig','ClientsController@wxJsConfig');

	    $user->get('subscribeAuth','ClientsController@subscribeAuth');

            //个人精选料列表展示
            $user->get('{id}/recommends','ClientsController@getRecommendSource');
        });

        // 料
        $api->group(['prefix' => 'source'], function($source) {
            $source->get('applypic','SourcesController@apply_picture');

            $source->get('test', 'SourcesController@test');

            $source->get('scan', 'SourcesController@refundScan');
            $source->get('false', 'SourcesController@falseData');
            $source->get('image', 'SourcesController@getImageQrcode');
            $source->get('notice', 'SourcesController@refundNotice');
            $source->get('{id}', 'SourcesController@getSourcesDetails'); //料详情
            $source->post('', 'SourcesController@postSourcesAdd');
            $source->post('newSource', 'SourcesController@postSourcesAddNew');//创建料
            $source->delete('{id}', 'SourcesController@deleteSource'); //删除料
            $source->post('{id}/shelf', 'SourcesController@putSourceOffshelf');//下架料
            $source->get('{id}/open', 'SourcesController@openSource');//公开料
            $source->get('{id}/brief', 'SourcesController@getSourceBrief');//简要信息
            $source->get('{id}/details', 'SourcesController@getSourceDetails');
            $source->get('{uid}/devlist', 'SourcesController@getSourceList4Dev');
            $source->get('{uid}/setmark', 'SourcesController@setSourceOrderStatus');
            $source->post('{uid}/worldCupCheck','SourcesController@AnswerCheck');
            $source->get('{id}/sourcedetail','SourcesController@h5SourceDetails');
            //红黑单未处理检查
            $source->get('{uid}/checkbet','SourcesController@checkBet');
            //通知粉丝
            $source->get('{sid}/sendnotice','SourcesController@sendNotice');
            $source->get('{uid}/noticetimes','SourcesController@noticeTime');
            //料修改接口
            $source->post('{uid}/edit','SourcesController@editSourceContent');

            //精选料列表(加入/取消，置顶/取消置顶精选)
            $source->post('{sid}/recommend','SourcesController@updateRecommendList');
            
            $source->get('{uid}/recommendlist', 'SourcesController@getRecommendList'); //购买时精选料列表
            $source->get('{uid}/detaillist', 'SourcesController@batchOrderDetails'); //购买后的料详情


        });

        //炫耀单
        $api->group(['prefix' => 'show'], function($show) {
            //炫耀单列表
            $show->get('list', 'ShowController@showList');

            //炫耀单详情
            $show->get('info/{id}', 'ShowController@showInfo');

            //提交炫耀单
            $show->post('submit', 'ShowController@showSubmit');

            //删除炫耀单
            $show->get('del/{id}', 'ShowController@showDel');
        });

        //推送检查
        $api->group(['prefix' => 'msg'], function($source) {
            $source->get('', 'MsgController@checkTime');
            $source->get('red', 'MsgController@checkRed');
            $source->get('test', 'MsgController@test');
        });
        //支付宝支付
        $api->group(['prefix' => 'alipay'], function($aliPay) {
            $aliPay->get('', 'AliPayController@createOrder');
            $aliPay->post('notice', 'AliPayController@notice');
            $aliPay->get('success', 'AliPayController@success');
            $aliPay->get('test', 'AliPayController@test');
        });
        //首页显示设置项
        $api->group(['prefix' => 'set'], function($source) {
            $source->get('', 'SystemController@getSetting');
        });
        //世界杯活动
        $api->group(['prefix' => 'sjb'], function($sjb) {
            $sjb->get('data', 'ActivityController@stat');
            $sjb->get('{id}', 'ActivityController@sjbData');
        });

        $api->get('withdraw', 'WithdrawController@getWithdraw');
        $api->post('withdraw', 'WithdrawController@postWithdraw');

        $api->group(['prefix' => 'order'], function($order) {
            $order->get('prepay', 'OrdersController@prepayIdToDB');
            $order->post('', 'OrdersController@postOrder');
            $order->post('update', 'OrdersController@postOrderUpdate');   // 微信更新接口
            $order->get('{id}/paid', 'OrdersController@getClinetPaid');   //收入明细
            $order->delete('{id}', 'OrdersController@deleteMySource');

            $order->post('hypay', 'OrdersController@generateOrder');
            $order->post('notify', 'OrdersController@hyNotify');

            $order->post('wxnotify', 'OrdersController@wxNotify');
            $order->post('qfnotify', 'OrdersController@qfNotify');

            $order->get('{id}/income','OrdersController@devClientIncome');
            $order->post('orderStatus', 'OrdersController@orderStatus');
            $order->get('ordercheck', 'OrdersController@ordercheck');

            //设置扫码购买操作过程记录
            $order->get('purchase/record', 'OrdersController@purchaseRecord');

            //购买用户列表
            $order->get('{id}/buyer', 'OrdersController@buyerList');
            //设置用户黑名单
            $order->get('{id}/setBuyerStatus', 'OrdersController@setBuyerStatus');
            //批量支付
            $order->any('batchpay', 'OrdersController@genBatchOrder');
            $order->post('batchnotify', 'OrdersController@batchWxPayNotify');
            $order->post('alipaynotify', 'OrdersController@alipayNotify');
            $order->any('alipaysuccess', 'OrdersController@alipaySuccess');

            $order->get('sourceOrderStatus', 'OrdersController@getSourceOrderStatus');
        });

        // 资源  上传
        $api->group(['prefix' => 'resource'], function($resource) {
            $resource->post('', 'ResourceController@postUpload');
            $resource->post('newfile', 'ResourceController@postUploadNew');
            $resource->post('desc', 'ResourceController@postUploadDesc');
            $resource->post('async', 'ResourceController@postAsync');
        });

        $api->group(['prefix' => 'section'], function($section) {
            $section->get('', 'SectionController@getSections');
            $section->get('{id}/source', 'SectionController@getSectionSources');            //获取栏目下对应的料列表
        });

        $api->group(['prefix' => 'complaints'], function($complaint) {
            $complaint->post('', 'ComplaintController@complaints');
            $complaint->post('upload', 'ComplaintController@upload');
        });



        $api->get('users/info', 'ClientsController@AuthenticatedUser'); // 用户信息获取
        $api->get('users/refresh', 'ClientsController@updateToken'); // token 刷新
        $api->get('users/revenue', 'ClientsController@revenue'); // 用户收入接口

        $api->post('sources/add', 'SourcesController@insert');   // 资源添加
        $api->get('sources/info', 'SourcesController@obtain');  // 卖资源列表
        $api->post('sources/del', 'SourcesController@del_sources');  // 用户资源删除
        $api->get('sources/yuan_img', 'SourcesController@yuan_img');  // 用户资源详情接口
        $api->post('sources/details', 'SourcesController@details');  // 获取单个资源详情（用与购买页面）
        $api->get("sources/buy", 'OrdersController@buy_source');     // 用户购买的料列表
        $api->post("sources/buy_del", 'OrdersController@buy_del');     // 用户购买的料列表
        $api->get("sources/detail", 'OrdersController@details');     // 已卖出的料  详情页


        $api->post('extract/apply', 'ExtractController@applicant');  // 提现申请添加
        $api->get('extract/list', 'ExtractController@CashList');  // 提现列表
        $api->get('extract/newcode', 'ExtractController@NewCode');  // 获取最新提现码

        $api->post("resource/upload", 'ResourceController@upload');     // 资源上传接口
        $api->post("async/upload", 'ResourceController@async_upload');     // 异步上传

        $api->post("orders/pays", 'OrdersController@generate');     // 预支付订单
        $api->post("pays/feedback", 'OrdersController@renew');     // 微信支付返回通知
        $api->get("orders/already", 'OrdersController@already_buy');     // 判断用户是否已经购买资源
        $api->post("orders/payment", 'OrdersController@payment');     // 用户支付成功后的资源页面
        $api->get("orders/play", 'OrdersController@play');     // 用户支付成功后的资源页面

        $api->get("weixin/pays", 'WxPayController@servers');     // 微信支付
        $api->get("weixin/notify", 'WxPayController@notify');     // 微信返回信息

        $api->get("sources/qrcode", 'SourcesController@image_qrcode');     // 微信返回信息
        $api->get("sources/yuan", 'SourcesController@yuan_img');     // 圆
        $api->get('login/test', 'ClientsController@test'); // 用户日志信息测试
        $api->get('sources/yuan', 'SourcesController@image_qrcode'); // 用户日志信息测试
        $api->get('sources/test_qrcode', 'SourcesController@test_qrcode'); // 用户日志信息测试
        $api->get('test/{id?}', 'RegisterController@register'); // 用户日志信息测试
        $api->put('test/{id}', 'RegisterController@test'); // 用户日志信息测试
        $api->get('sms', 'BaseController@seed_sms'); // 用户日志信息测试
        $api->get('domain', 'SystemController@domain'); // 获取域名
        $api->get('openWxPay', 'SystemController@open_wx_pay'); //开通微信支付
        $api->get('tbd', 'SystemController@tbd'); // 获取二维码跳转连接
    });


}); 

