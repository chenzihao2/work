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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

//Route::get('/foo/fo', function(){
//    return 'foo';
//})->name('f');

Route::prefix('user')->namespace('API')->middleware(['auth', 'addtoken'])->group(function() {
    Route::any('czh/{uid?}', 'UserController@test');
});

Route::any('czh1', 'UserController@test1');

//APP接口
Route::group(['prefix' => '/app', 'namespace' => 'Api'], function () {
        /**聊天组**/
        Route::group(['prefix' => 'chat'], function () {

                Route::any('/bind', 'ChatController@bind');//聊天账号绑定
                Route::any('/unreadCount', 'ChatController@unreadCount');//未读数量
                Route::any('/openChat', 'ChatController@openChat');//未读改为已读
                Route::any('/closeChat', 'ChatController@closeChat');//未读改为已读
                Route::any('/messageList', 'ChatController@messageList');//聊天记录列表
                Route::post('/send', 'ChatController@send');//
                Route::any('/onMessage', 'ChatController@onMessage');//监听聊天接口
                Route::any('/friendsList', 'ChatController@friendsList');//好友列表接口
                Route::any('/follow', 'ChatController@follow');//关注专家发送自动回复
                Route::any('/cancel', 'ChatController@cancel');//取消关注专家清空聊天记录
                Route::any('/onClose', 'ChatController@onClose');//断开连接

        });

        /**登录接口**/
        Route::any('login', 'UserController@login');

        /**短信验证码接口**/
        Route::any('sendMessage', 'UserController@sendMessage');
        Route::any('wxLogin', 'UserController@wxLogin');//微信登陆
        Route::any('bind', 'UserController@bind');//绑定手机号
        Route::any('updateDevice', 'UserController@updateDevice');//修改友盟device_token
        Route::middleware(['auth', 'addtoken'])->any('deviceInfo', 'UserController@deviceInfo');//设备信息
        Route::middleware(['auth', 'addtoken'])->any('userInfo', 'UserController@userInfo');//用户信息

        /**评论接口**/
        Route::group(['prefix' => 'comment', 'middleware' => ['auth', 'addtoken']], function () {
            Route::any('/releaseComent', 'CommentController@releaseComent');//评论接口
            Route::any('/commentReply', 'CommentController@commentReply');//回复接口
            Route::any('/commentList', 'CommentController@commentList');//评论列表接口
            Route::any('/commentInfo', 'CommentController@commentInfo');//评论详情接口
            Route::any('/replyList', 'CommentController@replyList');//回复列表接口
        });

        /**点赞接口**/
        Route::middleware(['auth', 'addtoken'])->any('fabulous', 'FabulousController@fabulous');//点赞/取消点赞

        /**资讯相关**/
        Route::group(['prefix' => 'news', 'middleware' => ['auth', 'addtoken']], function () {
            Route::any('newsList', 'NewsController@newsList');//资讯列表
            Route::any('newsListRelated', 'NewsController@newsListRelated');//资讯列表
            Route::any('relatedVideos', 'NewsController@relatedVideos');//相关视频列表
            Route::any('getVideoList', 'NewsController@getVideoList');//视频列表
        });

        /**banner相关**/
        Route::group(['prefix' => 'banner'], function () {
            Route::any('lists', 'BannerController@lists');//banner列表
        });
        /**专家**/
        Route::group(['prefix' => 'expert', 'middleware' => ['auth', 'addtoken']], function () {
            Route::any('soccerBasketRecommend', 'ExpertController@soccerBasketRecommend');//足篮球推荐
            Route::any('dryStuffList', 'ExpertController@dryStuffList');//专家干货列表
            Route::any('collectedDryStuffList', 'ExpertController@collectedDryStuffList');//收藏的专家干货列表
            Route::any('collectDryStuff', 'ExpertController@collectDryStuff');//收藏专家干货
            Route::any('dryStuffListRelated', 'ExpertController@dryStuffListRelated');//相关干货列表
            Route::any('expertInfo', 'ExpertController@expertInfo');//专家详细信息
            Route::any('redMan', 'ExpertController@redMan');//红人榜
            Route::any('redManResource', 'ExpertController@redManResource');//红人榜相关方案
            Route::any('recommendExpert', 'ExpertController@recommendExpert');//引导关注专家列表
            Route::any('redRecord', 'ExpertController@redRecord');//连红榜
            Route::any('batchFollowExpert', 'ExpertController@batchFollowExpert');//批量关注
            Route::any('folowExpert', 'ExpertController@folowExpert');//单个关注
            Route::any('highProfit', 'ExpertController@highProfit');//连红榜
            Route::any('hitRate', 'ExpertController@hitRate');//命中率
            Route::any('allExpert', 'ExpertController@allExpert');//全部专家
            Route::any('updateExpertExtra', 'ExpertController@updateExpertExtra');//更新专家相关信息
        });

        /**新登陆逻辑**/
        Route::group(['prefix' => 'login'], function () {
            Route::any('login', 'LoginController@login');//手机号登陆
            Route::any('sendMessage', 'LoginController@sendMessage');//发送验证码
            Route::any('wxLogin', 'LoginController@wxLogin');//微信登陆
            Route::any('bind', 'LoginController@bind');//绑定手机号
            Route::any('appleLogin', 'LoginController@appleLogin');//苹果授权
            Route::any('appleBind', 'LoginController@appleBind');//苹果授权绑定手机号
            Route::any('bindApple', 'LoginController@bindApple');//绑定苹果账号
            Route::middleware(['auth', 'addtoken'])->any('bindMobile', 'LoginController@bindMobile');//安全中心绑定手机号
        });

        /**举报**/
        Route::group(['prefix' => 'report'], function () {
            Route::any('getReportCate', 'ReportController@getReportCate');//获取举报分类
            Route::middleware(['auth', 'addtoken'])->any('submitReport', 'ReportController@submitReport');//提交举报内容
        });

        /**用户相关**/
        Route::group(['prefix' => 'user'], function () {
            Route::middleware(['auth', 'addtoken'])->any('userInfo', 'UserController@userInfo');//用户信息
            Route::any('updateDevice', 'UserController@updateDevice');//修改友盟device_token
        });

        /**情报相关**/
        Route::group(['prefix' => 'info'], function () {
            Route::middleware(['auth', 'addtoken'])->any('getMatchInfo', 'MatchController@getInformation');//获取赛事情报
        });

        /**方案相关**/
        Route::group(['prefix' => 'resource'], function () {
            Route::middleware(['auth', 'addtoken'])->any('/viewRecord', 'ResourceController@viewRecord');//方案浏览记录
        });
    /**情报管理**/
    Route::group(['prefix' => 'match'], function () {
        Route::any('/getMatch', 'MatchController@getMatch');

    });

});



//后台管理
Route::group(['prefix' => '/admin', 'namespace' => 'Admin'], function () {

        /**聊天管理组**/
        Route::group(['prefix' => 'chat'], function () {

                Route::any('/recordList', 'ChatController@recordList');//聊天记录列表
                Route::any('/forbiddenSay', 'ChatController@forbiddenSay');//禁言
				

        });

        /**订单统计**/
        Route::group(['prefix' => 'channel'], function () {

            Route::any('/lists', 'ChannelController@lists');//日充值统计
            Route::any('/listsSum', 'ChannelController@listsSum');//日充值统计总和
            Route::any('/consumeList', 'ChannelController@consumeList');//日充值统计
            Route::any('/consumeSum', 'ChannelController@consumeSum');//日充值统计总和

            Route::any('/channelList', 'ChannelController@channelList');//渠道列表

        });

        /**资讯管理**/
        Route::group(['prefix' => 'news'], function () {
            Route::any('newsList', 'NewsController@newsList');//资讯列表
            Route::any('recommend', 'NewsController@recommend');//推荐
            Route::any('videoList', 'NewsController@videoList');//视频列表
            Route::any('editVideo', 'NewsController@editVideo');//修改视频

        });

        /**配置**/
        Route::group(['prefix' => 'config'], function () {
            Route::any('show', 'ConfigController@show');//过审配置展示
            Route::any('editShow', 'ConfigController@editShow');//编辑过审配置
            Route::any('showInfo', 'ConfigController@showInfo');//过审配置详情
            Route::any('showList', 'ConfigController@showList');//过审配置列表
            Route::any('dealBase64', 'ConfigController@dealBase64');//base64转图片
            Route::any('/setNewsConfig', 'ConfigController@setNewsConfig');//设置文章评论模块
            Route::any('/setVideoConfig', 'ConfigController@setVideoConfig');//设置视频评论模块
            Route::any('/getNewsConfig', 'ConfigController@getNewsConfig');//获取新闻评论模块
            Route::any('/getVideoConfig', 'ConfigController@getVideoConfig');//获取视频评论模块
        });

        /**评论管理**/
        Route::group(['prefix' => 'comment'], function () {
            Route::any('/replyList', 'CommentController@replyList');//回复列表
            Route::any('/commentReplyList', 'CommentController@commentReplyList');//回复列表
            Route::any('/commentList', 'CommentController@commentList');//评论列表接口
            Route::any('/commentInfo', 'CommentController@commentInfo');//评论详情接口
            Route::any('/changeStatus', 'CommentController@changeStatus');//审核
        });

        /**专家**/
        Route::group(['prefix' => 'expert'], function () {
            Route::any('expertList', 'ExpertController@expertList');//列表
            Route::any('recommend', 'ExpertController@recommend');//足篮球推荐
            Route::any('redMan', 'ExpertController@redMan');//红人榜
            Route::any('setTopRedMan', 'ExpertController@setTopRedMan');//红人榜置顶
            Route::any('setShowRedMan', 'ExpertController@setShowRedMan');//红人榜展示设置
            Route::any('expertNameList', 'ExpertController@expertNameList');//专家名字列表
            Route::any('expertDryStuff', 'ExpertController@expertDryStuff');//专家干货
            Route::any('setTopDryStuff', 'ExpertController@setTopDryStuff');//置顶专家干货
            Route::any('refreshRedMan', 'ExpertController@refreshRedMan');//刷新红人榜
        });

        /**敏感词**/
        Route::group(['prefix' => 'sensitives'], function () {
            Route::any('/wordsList', 'SensitivesController@wordsList');//敏感词列表
            Route::any('/addWords', 'SensitivesController@addWords');//添加敏感词
            Route::any('/updateStatus', 'SensitivesController@updateStatus');//修改敏感词
            Route::any('/delWords', 'SensitivesController@delWords');//删除敏感词
            Route::any('/testAction', 'SensitivesController@testAction');//删除敏感词
        });

        /**举报管理**/
        Route::group(['prefix' => 'report'], function () {
            Route::any('/reportList', 'ReportController@reportList');//列表
            Route::any('/seeInfo', 'ReportController@seeInfo');//查看
            Route::any('/reply', 'ReportController@reply');//回复
            Route::any('/changeStatus', 'ReportController@changeStatus');//处理举报
        });

        /**用户管理**/
        Route::group(['prefix' => 'user'], function () {
            Route::any('/isForbidden', 'UserController@isForbidden');//禁止评论发言
        });

        /**情报管理**/
        Route::group(['prefix' => 'match'], function () {
            Route::any('/getMatchInfomation', 'MatchController@getMatchInfomation');//获取情报详情
            Route::any('/addInformation', 'MatchController@addInformation');//添加情报
            Route::any('/updateInformation', 'MatchController@updateInformation');//修改情报
            Route::any('/timingGetMatchInformation', 'MatchController@timingGetMatchInformation');//定时获取最新情报信息
        });

        /**方案相关管理**/
        Route::group(['prefix' => 'resource'], function () {
            Route::any('/getRecord', 'ResourceController@getRecord');//获方案修改记录
            Route::any('/addRecord', 'ResourceController@addRecord');//添加方案修改记录
        });

});
