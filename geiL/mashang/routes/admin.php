<?php
parse_str(file_get_contents("php://input"),$post_vars);
error_log(print_r($post_vars, true), 3, '/tmp/qiudashi');
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Credentials: true");
    header('Access-Control-Allow-Origin: '.$_SERVER['HTTP_ORIGIN']);
    header("Access-Control-Allow-Headers: *, X-Requested-With, Content-Type");
    header('Access-Control-Allow-Methods:OPTIONS, GET, POST,PUT,DELETE');
    header('Access-Control-Max-Age: 3600');
}


Route::group(['namespace' => 'Admin', 'prefix' => 'admin','middleware' => ['App\Http\Middleware\AdminAuthenticate','App\Http\Middleware\Chronography']], function($admin)
{

    $admin->group(['prefix' => 'home'], function($home) {
        $home->get('active', 'HomeController@getActive');
        $home->get('active_day', 'HomeController@getDayActive');
    });

    $admin->post('login', 'HomeController@postLogin'); // 登陆接口

    $admin->get('auth/users', 'HomeController@getAuthUsers'); // 管理员列表
    $admin->post('auth/users', 'HomeController@postAuthUsers'); // 管理员添加
    //$admin->get('users/signlist', 'ClientController@signatureList');                
    
    $admin->group(['prefix' => 'user'], function($user) {
        $user->get('', 'ClientController@getUser'); // 用户信息列表
        $user->get('signlist', 'ClientController@signatureList');                //个性签名列表

        //用户分组体系
        $user->get('groupList', 'ClientController@groupList');              //组别列表
        $user->get('groupAdd', 'ClientController@groupAdd');                //新增页面，返回组号
        $user->post('group/checkUser', 'ClientController@groupCheckUser');    //绑定用户操作
        $user->post('group/set', 'ClientController@groupSet');               //设置组别操作
        $user->get('{gid}/groupInfo', 'ClientController@groupInfo');         //获取组别相关信息

        $user->get('{id}', 'ClientController@getClientInfo');    // 用户信息详情
        $user->put('{id}', 'ClientController@putUser');    // 用户信息修改
        $user->put('{id}/able', 'ClientController@putUserAble'); // 用户暂停/允许
        $user->put('{id}/white', 'ClientController@putUserWhite'); // 用户加入/剔除白名单
        $user->get('{id}/business', 'ClientController@getClientBusiness'); // 用户订单列表
        $user->get('{id}/source', 'ClientController@getClientSource'); // 用户料列表
        $user->get('{id}/account', 'ClientController@getClientAccount'); // 用户提现渠道列表
        $user->put('{id}/accountupdate', 'ClientController@updateClientAccount'); // 用户提现渠道列表更新
        $user->get('excel/export', 'ClientController@export');

    });

    $admin->group(['prefix' => 'source'], function($source) {
        $source->get('', 'SourceController@getSource');
        $source->get('{id}', 'SourceController@getSourceDeatail');
        $source->put('{id}/disable', 'SourceController@putSourceDisable');
        $source->put('{id}/check', 'SourceController@putSourceCheck');
        $source->get('{id}/refund', 'SourceController@setSourceOrderStatus'); //设置红黑单

        $source->put('{id}/tag','TagController@putSourceTag');                      //为料添加标签
        $source->put('{id}/recommend','SourceController@recommendSource');        //后台运营将料加入推荐
        $source->put('{id}/section','SourceController@putSourceSection');         //后台运营人员更改料的所属栏目
        $source->put('{id}/rank','SourceController@putSourceRank');                      //运营人员对推荐列表做人工排序
    });


    //料内容
    $admin->group(['prefix' => 'content'], function($content) {
        $content->get('', 'ContentController@contentList');	//料内容列表
        $content->put('{id}/set', 'ContentController@contentCheck');	//料审核
    });

    //首页显示设置项
    $admin->group(['prefix' => 'set'], function($set) {
        $set->get('', 'SystemController@getSetting');
        $set->get('set', 'SystemController@setSetting');
    });

    $admin->group(['prefix' => 'withdraw'], function($withdraw) {
        $withdraw->get('', 'WithdrawController@getWithdraw');

        //运营发起提现
        $withdraw->post('postWithdraw', 'WithdrawController@postWithdraw');

        //财务数据显示
        $withdraw->get('financial', 'WithdrawController@financial');              //财务数据列表
        $withdraw->get('financial/export', 'WithdrawController@financialExport');   //财务数据导出表格

        //服务费率
        $withdraw->get('rate', 'WithdrawController@rateList');              //服务费率列表
        $withdraw->get('rateinfo', 'WithdrawController@rateInfo');          //服务费率详情
        $withdraw->post('setrate', 'WithdrawController@setRate');           //设置服务费率
        $withdraw->get('onlinerate', 'WithdrawController@onlineRate');      //上线服务费率
        $withdraw->get('downlinerate', 'WithdrawController@downlineRate');  //下线服务费率

        //发放优惠
        $withdraw->get('discount', 'WithdrawController@discountList');  //发放优惠列表
        $withdraw->post('discountManual', 'WithdrawController@discountManual');  //手动发放优惠
        $withdraw->post('discountAuto', 'WithdrawController@discountAuto');  //自动发放优惠
        $withdraw->get('discount/export', 'WithdrawController@discountExport');  //发放优惠列表导出表格

        $withdraw->put('{id}', 'WithdrawController@putWithdrawUpdate');
        $withdraw->post('interim/{id}', 'WithdrawController@putWithdrawUpdateTest');
        $withdraw->get('play', 'WithdrawController@play');
        $withdraw->get('{id}', 'WithdrawController@getClientBrief');
        $withdraw->get('excel/export', 'WithdrawController@export');
        //$withdraw->get('play', 'WithdrawController@play');
    });

    $admin->group(['prefix' => 'order'], function($order) {
        $order->get('', 'OrderController@getOrdersList');
        $order->get('excel/export', 'OrderController@export');
        $order->get('fix', 'OrderController@withdrawingFix');
    });

    $admin->group(['prefix' => 'section'], function($section) {
        $section->get('', 'SectionController@getSections');
        $section->post('', 'SectionController@postSectionInfo');
        $section->put('{id}', 'SectionController@putSection');
        $section->delete('{id}', 'SectionController@deleteSection');
        //获取推荐列表
        $section->get('{id}/source','SectionController@recommendSources');
    });

    $admin->group(['prefix' => 'tag'], function($tag) {
        $tag->get('', 'TagController@getTags');
    });

    $admin->get('complaints', 'ComplaintsController@getComplaints');
    $admin->put('complaints/{id}', 'ComplaintsController@updateStatus');

    $admin->post('manualRefund', 'OrderController@manualRefund');
});

