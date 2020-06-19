<?php

namespace App\Http\Controllers\Admin;

use App\models\statics;
use App\models\statics_hours;
use Illuminate\Http\Request;
use App\Http\Controllers\Admin\BaseController;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\models\admin;
use Ramsey\Uuid\Uuid;
use App\models\client;
use App\models\client_extra;
use App\models\client_withdraw;
use App\models\order;
use App\models\resource;
use App\models\source;
use App\models\source_extra;
use App\models\statics as tics;
use Illuminate\Support\Facades\DB;

class HomeController extends BaseController
{

    /**
     * 首页统计信息
     */
    public function getActive(Request $request)
    {
        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = $request->input('query', '');
        $query = json_decode($query, True);
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }
        $offset = $page * $numperpage;
        $active = statics::select();
        if ( $offset != 0 )
            $active->offset($offset);
            $active->paginate($numperpage);
        if ( !empty( $query['statictime']['from']) )
            $active->whereBetween('statictime', [$query['statictime']['from'], $query['statictime']['to']]);
        $data = $active->get();
        $return['status_code'] = '200';
        $return['data'] = $data;
        return response()->json($return);
    }

    // 当日统计的信息
    public function getDayActive(Request $request)
    {
        $token = JWTAuth::getToken();
//        $clients = $this->getUserInfo($token);
//        if (!empty($clients['status_code'])) {
//            if ($clients['status_code'] == '401') {
//                $error['status_code'] = '10001';
//                $error['error_message'] = '用户token验证失败， 请刷新重试';
//                return response()->json($error);
//            }
//        }
        $query = $request->input('query', '');
        $order = $request->input('order', 'asc');
        $today = date('Y-m-d');
        $date = $request->input('date', $today);
        $query = json_decode($query, True);
        $hours = statics_hours::where('statictime', 'like', $date.'%');
        if ( !empty( $query['statictime']['from']) && !empty($query['statictime']['to']))
            $hours->whereBetween('statictime', [$query['statictime']['from'], $query['statictime']['to']]);
        $hours->orderBy('statictime', $order);
        $data = $hours->get();
        $statics['order'] = order::where('orderstatus', 1)->where('createtime', 'like', $date.'%')->count();
        $statics['resource'] = resource::where('createtime', 'like', $date.'%')->count();
        $statics['source'] = source::where('createtime', 'like', $date.'%')->count();
        $statics['user'] = client::where('createtime', 'like', $date.'%')->count();
        $statics['service_fee'] = client_withdraw::where('completetime', 'like', $date.'%')->where('status', 4)->sum('service_fee');
        $statics['withdrawed'] = client_withdraw::where('completetime', 'like', $date.'%')->where('status', 4)->sum('balance');
        $statics['withdrawing'] = client_withdraw::where('completetime', 'like', $date.'%')->sum('balance');
        $statics['total'] = order::where('orderstatus', 1)->where('createtime', 'like', $date.'%')->sum('price');
        $statics['statictime'] = $date;
        $statics['active'] = client_extra::where('lastlogin', 'like', $date.'%')->count();
        $sell = DB::select("select count(*) as num from client_extra where substring(bin(role), -1, 1) = 1 and lastlogin like '{$date}%'");
        $buy = DB::select("select count(*) as num from client_extra where substring(bin(role), -2, 1) = 1 and lastlogin like '{$date}%'");
        $statics['active_sell'] = $sell[0]->num;
        $statics['active_buy'] = $buy[0]->num;
        $return['status_code'] = '200';
        $return['data'] = $data;
        $return['statics'] = $statics;
        return response()->json($return);
    }


    /**
     * 后台管理员登陆
     * role
     */
    public function postLogin(Request $request)
    {
        $credentials = $request->only('username', 'password');
        if( empty($credentials['username']) || empty($credentials['password'])) {
            $return['status_code'] = '10001';
            $return['error_message'] = '用户名或密码不可为空';
        }
        $pwd = md5($credentials['password'].'geiliao');
        $users = admin::where('username', $credentials['username'])->where('password', $pwd)->first();

        if( empty($users) ) {
            $return['status_code'] = '10002';
            $return['message'] = '用户名或密码不正确';
            return response()->json($return);
        }

        $token = JWTAuth::fromUser($users);

        $return['status_code'] = '200';
        $return['message'] = '登陆成功';
        $return['token'] = $token;
        return response()->json($return);
    }



    /**
     * 管理员列表
     */
    public function getAuthUsers(Request $request)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        if ( $clients['role'] != 'root') {
            $return['status_code'] = '10001';
            $return['error_message'] = '无权限';
            return response()->json($return);
        }

        $data = admin::get();

        $return['status_code'] = '200';
        $return['data'] = $data;
        return response()->json($return);
    }


    /**
     * 创建管理员
     */
    public function postAuthUsers(Request $request)
    {
        $roles = ['root', 'admin', 'audit1', 'audit2'];
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        if ( $clients['role'] != 'root') {
            $return['status_code'] = '10001';
            $return['error_message'] = '无权限';
            return response()->json($return);
        }

        $username = $request->input('username', '');
        $password = $request->input('password', '');
        $name = $request->input('name', '');
        $role = $request->input('role', '');
        $telephone = $request->input('telephone', '');
        $email = $request->input('email', '');

        if ( !in_array($role, $roles)) {
            $return['status_code'] = '10004';
            $return['error_message'] = '角色不对称， 不在角色类型中';
            return response()->json($return);
        }



        $mobile = preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $telephone) ? true : false;
        if ( !$mobile ) {
            $return['status_code'] = '10003';
            $return['error_message'] = '手机号格式不正确';
            return response()->json($return);
        }

        $only = admin::where('username', $username)->first();
        if ( $only ) {
            $return['status_code'] = '10002';
            $return['error_message'] = '用户登陆账号唯一';
            return response()->json($return);
        }

        $password = md5($password.'geiliao');

        $uuid1 = Uuid::uuid1();
        $admin['id'] = $uuid1->getHex();
        $admin['username'] = $username;
        $admin['password'] = $password;
        $admin['name'] = $name;
        $admin['role'] = $role;
        $admin['telephone'] = $telephone;
        $admin['email'] = $email;
        $admin['createtime'] = date("Y-m-d H:i:s", time());
        admin::create($admin);

        $return['status_code'] = '200';
        $return['data'] = $admin;

        return response()->json($return);
    }


}
