<?php

namespace App\Http\Controllers\Admin;

use App\models\admin;
use App\models\client;
use App\models\client_account;
use App\models\client_group;
use App\models\client_rate;
use App\models\client_withdraw;
use App\models\client_signature_log;
use App\models\discount;
use App\models\group;
use App\models\manual_record;
use App\models\source;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Predis\Replication\RoleException;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Controllers\Controller;
use App\models\order;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends BaseController
{
    // 用户信息列表
    public function getUser(Request $request)
    {
        $roles = ['root', 'admin', 'audit1', 'audit2'];
        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = $request->input('query', '');
        $sort = $request->input('sort', '');
        $query = json_decode($query, True);
        $sort = json_decode($sort, True);
        $offset = $page * $numperpage;

        // 进行查询
        $client = client::select();
        $client->LeftJoin('client_extra', 'client_extra.id', 'client.id');
        $client->LeftJoin('client_rate', 'client_rate.uid', 'client.id');

        if(!empty($query['id'])){
            $client->where('client.id',$query['id']);
        }

        if(!empty($query['nickname'])){
            $client->where('nickname','like','%'.$query['nickname'].'%');
        }

        if(isset($query['is_white']) && $query['is_white'] != ''){
            $client->where('is_white',$query['is_white']);
        }

        if ( !empty( $query['seller'] ) )
            $client->whereRaw("substring(bin(role), -{$query['seller']}, 1) = 1");

        if ( !empty( $query['balance'] ) )
            $client->where('balance', '>=', $query['balance']);

        if ( !empty( $query['logintime']['from'] ) )
            $client->whereBetween('lastlogin', [$query['logintime']['from'], $query['logintime']['to']]);

        if ( !empty( $sort['balance'] ) )
            $client->orderBy('balance', $sorts[$sort['balance']]);

        if ( !empty( $sort['total'] ) )
            $client->orderBy('total', $sorts[$sort['total']]);

        if ( !empty( $sort['logintime'] ) )
            $client->orderBy('lastlogin', $sorts[$sort['logintime']]);
        if ( $offset != 0 )
            $client->offset($offset);
        $client->limit($numperpage);

        $data = $client->get();


        // 总数
        $client = client::select();
        $client->LeftJoin('client_extra', 'client_extra.id', 'client.id');
        $client->LeftJoin('client_rate', 'client_rate.uid', 'client.id');

        if(!empty($query['id'])){
            $client->where('client.id',$query['id']);
        }

        if(!empty($query['nickname'])){
            $client->where('nickname','like','%'.$query['nickname'].'%');
        }

        if ( !empty( $query['seller'] ) )
            $client->whereRaw("substring(bin(role), -{$query['seller']}, 1) = 1");

        if ( !empty( $query['balance'] ) )
            $client->where('balance', '>=', $query['balance']);

        if ( !empty( $query['logintime']['from'] ) )
            $client->whereBetween('lastlogin', [$query['logintime']['from'], $query['logintime']['to']]);

        $count = $client->count();

        $pagenum = ceil($count/$numperpage);

        foreach ($data as $key => $value) {
            $allow = $this->statusChanger($value['status'], 8);
            $data[$key]['allow'] = $allow;
            $status = decbin($value['status']);
            $data[$key]['status'] = sprintf('%08d', $status);
        }

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();

        return response()->json($return);

    }

    public function export(Request $request){
        $roles = ['root', 'admin', 'audit1', 'audit2'];
        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        /*$token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }*/

        $query = $request->input('query', '');
        $sort = $request->input('sort', '');
        $query = json_decode($query, True);
        $sort = json_decode($sort, True);

        // 进行查询
        $client = client::select();
        $client->LeftJoin('client_extra', 'client_extra.id', 'client.id');
        $client->LeftJoin('client_rate', 'client_rate.uid', 'client.id');

        if(!empty($query['id'])){
            $client->where('client.id',$query['id']);
        }

        if(!empty($query['nickname'])){
            $client->where('nickname','like','%'.$query['nickname'].'%');
        }

        if ( !empty( $query['seller'] ) )
            $client->whereRaw("substring(bin(role), -{$query['seller']}, 1) = 1");

        if ( !empty( $query['balance'] ) )
            $client->where('balance', '>=', $query['balance']);

        if ( !empty( $query['logintime']['from'] ) )
            $client->whereBetween('lastlogin', [$query['logintime']['from'], $query['logintime']['to']]);

        if ( !empty( $sort['balance'] ) )
            $client->orderBy('balance', $sorts[$sort['balance']]);

        if ( !empty( $sort['total'] ) )
            $client->orderBy('total', $sorts[$sort['total']]);

        if ( !empty( $sort['logintime'] ) )
            $client->orderBy('lastlogin', $sorts[$sort['logintime']]);
        $dataList = $client->limit(2000)->get()->ToArray();

        $res[] = ['用户ID','用户昵称','电话', '是否白名单', '省份','已提现金额','服务费总额','提现中金额','余额','总支付金额','总收入金额','发布料总数','卖出料总数','购买料总数','最后登录时间'];
        foreach($dataList as $value){
            $nickname = $this->userTextEncode($value['nickname']);
            $data = [$value['id'],$nickname,$value['telephone'], $value['is_white'],$value['province'],$value['withdrawed'],$value['service_fee'],$value['withdrawing'],$value['balance'],$value['payed'],$value['total'],$value['publishednum'],$value['soldnum'],$value['buynum'],$value['lastlogin']];
            array_push($res,$data);
        }
        Excel::create('用户列表',function($excel) use ($res){
            $excel->sheet('user', function($sheet) use ($res){
                $sheet->rows($res);
            });
        })->export('xls');
    }


    /**
     * 用户详情接口
     */
    public function getClientInfo(Request $request, $id)
    {
        $roles = ['root', 'admin', 'audit1', 'audit2'];
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }
        $data = client::LeftJoin('client_extra', 'client.id', 'client_extra.id')->LeftJoin('client_rate', 'client_rate.uid', 'client.id')->where('client.id', $id)->first();
        $allow = $this->statusChanger($data['status'], 8);

        // 根据提现表示获取订单信息
        $wid = $request->input('wid', '');
        $is_manual = 0;
        $manualInfo = [];
        if(!empty($wid)){
            $manualType = $request->input('manual_type', '');
            if($manualType == 1){
                //获取该提现单详情
                $orderInfo = client_withdraw::where('id', $wid)->where('uid', $id)->first();
            } elseif ($manualType == 2){
                //获取该优惠单详情
                $orderInfo = discount::where('id', $wid)->where('uid', $id)->first();
            }

            if(!empty($orderInfo)){
                $orderInfo = $orderInfo->toArray();
                $is_manual = $orderInfo['is_manual'];
                //判定是否为手动提现
                if($orderInfo['is_manual'] == 1){
                    $manualInfo = manual_record::where('relation_id', $wid)->first();
                    $manualInfo = !empty($manualInfo) ? $manualInfo->toArray() : [];
                }
            }
        }

        $data['allow'] = $allow;
        $data['is_manual'] = $is_manual;
        $data['manualInfo'] = $manualInfo;
        $return['status_code'] = '200';
        $return['data'] = $data->ToArray();

//        $return['manualInfo'] = $manualInfo;
        return response()->json($return);
    }


    /**
     *  用户信息修改
     */
    public function putUser(Request $request, $uid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试1';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }
        if( $request->input('telephone', '') != '')
            $user['telephone'] = $request->input('telephone', '');
        if( $request->input('idcardtype', '') != '')
            $user['idcardtype'] = $request->input('idcardtype', '');
        if( $request->input('idcardnumber', '') != '')
            $user['idcardnumber'] = $request->input('idcardnumber', '');
        if ( $request->input('rate', '') != '')
            $rate['rate'] = $request->input('rate', '');
        if ( $request->input('grade', '') != '')
            $rate['grade'] = $request->input('grade', '');

        /*if( empty($user['telephone']) || empty($user['idcardtype']) || empty($user['idcardnumber']) || empty($rate['rate']) || empty($rate['grade'])) {
            $return['status_code'] = '10003';
            $return['error_message'] = '有参数为空';
            return response()->json($return);
        }*/

        if(isset($user))
            client::where('id', $uid)->update($user);
        if(isset($rate))
            client_rate::where('uid', $uid)->update($rate);

        $return['status_code'] = '200';

        return response()->json($return);
    }

    /**
     * 暂定/允许 用户
     */
    public function putUserAble(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        $allow = $request->input('status', '1'); // 默认为禁止一个用户

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        // 修改信息
        $user = client::where('id', $id)->first();
//        $status = $this->statusChanger($user['status'], '8');
        $status = decbin($user['status']);
        $oldStatus = sprintf('%08d', $status);
        $newStatus = substr_replace($oldStatus, $allow, -8, 1);
        $newStatusChange = bindec((int)$newStatus);
        client::where('id', $id)->update(['status' => $newStatusChange]);

        $return['status_code'] = '200';
        return response()->json($return);
    }



    /**
     * 用户加入/剔除白名单
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function putUserWhite(Request $request, $id)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        $is_white = $request->input('is_white', 1); // 默认为加入白名单

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        // 修改信息
        client::where('id', $id)->update(['is_white' => $is_white]);

        if($is_white == 1){
            $userInfo = client::select('openid', 'serviceid', 'nickname')->where('id', $id)->first();

            $token = $this->msg_access_token(2);

            //$noticeTemplateId = "ZQfFFFUl5PVPt4vq8brFlG_6dWimvjFAZpffT87SCNo";
            $noticeTemplateId = "n3VAVR6XwcvspLCRU8aWP8cR_f-yOz-P3TQ8ogUX4Vw";
            $openid = $userInfo['serviceid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
            //支付信息
            $msg = array();

            $msg['first']  = [
                'value' => "恭喜您成为新给料金牌卖家，无需审核，极速发料。",
            ];
            $params['url'] = "https://glm9.qiudashi.com/";
            $msg['keyword1'] = [
                'value' => $userInfo['nickname'],
            ];
            $msg['keyword2'] = [
                'value' => "金牌卖家",
            ];
            $msg['keyword3'] = [
                'value' => date('Y-m-d H:i', time()),
            ];
            $msg['remark']  = [
                'value' => "感谢您对给料的支持",
            ];
            $params['data'] = $msg;
            $this->postCurl($api, $params, 'json');
        }

        $return['status_code'] = '200';
        return response()->json($return);
    }

    /**
     * 用户订单列表
     */
    public function getClientBusiness(Request $request, $uid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        $status = $request->input('status', '0');

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $roles = ['root', 'admin', 'audit1', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }
        $business = 'buyerid';
        if( $status == 1 ) {
            $business = 'selledid';
        }

//        $withdraw = order::where($business, $uid)->where('orderstatus', '1')->get();
        $withdraw = order::where($business, $uid)
            ->whereRaw("substring(bin(orderstatus), -1, 1) = 1")     //用户已支付状态
            ->get();

        $return['status_code'] = '200';
        $return['data'] = $withdraw->ToArray();
        return response()->json($return);
    }


    /**
     * 用户资源列表
     */
    public function getClientSource(Request $request, $uid)
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

        $roles = ['root', 'admin', 'audit1', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $data = source::where('uid', $uid)->get();
        foreach ($data as $key => $value) {
            $data[$key]['source_status'] = 0;
            if ( $this->statusChanger($value['status'], 2) == 1 ) {
                $data[$key]['source_status'] = 1;
            }
            if ( $this->statusChanger($value['status'], 1) == 1 ) {
                $data[$key]['source_status'] = 2;
            }if ( $this->statusChanger($value['status'], 4) == 1 ) {
                $data[$key]['source_status'] = 3;
            }
            if ( $this->statusChanger($value['status'], 8) == 1 ) {
                $data[$key]['source_status'] = 4;
            }
        }

        $return['status_code'] = '200';
        $return['data'] = $data->ToArray();
        return response()->json($return);
    }


    /**
     * 用户组别列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function groupList(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '20');
        $query = $request->input('query', '');
        $query = json_decode($query, True);

        $offset = $page * $numperpage;

        // 进行查询
        $client_group = client_group::select('client_group.gid', 'group.name', 'client_group.uid', 'client_group.create_time', 'client.nickname', 'client.avatarurl');
        $client_group->LeftJoin('client', 'client_group.uid', 'client.id');
        $client_group->LeftJoin('group', 'client_group.gid', 'group.id');

        if(!empty($query['uid'])){
            $client_group->where('client_group.uid',$query['uid']);
        }

        if(!empty($query['gid'])){
            $client_group->where('client_group.gid',$query['gid']);
        }
        $client_group->orderBy('client_group.gid', 'desc');
        if ( $offset != 0 ) {
            $client_group->offset($offset);
            $client_group->limit($numperpage);
        }
        $data = $client_group->get()->toArray();

        // 总数
        $client_group = client_group::select();
        if(!empty($uid)){
            $client_group->where('client_group.uid',$uid);
        }

        if(!empty($gid)){
            $client_group->where('client_group.gid',$gid);
        }

        $count = $client_group->count();

        $pagenum = ceil($count/$numperpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data;
        return response()->json($return);

    }


    /**
     * 创建用户组操作
     * @return \Illuminate\Http\JsonResponse
     */
    public function groupAdd(){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        $data['create_time'] = time();
        $res_add = group::create($data);
        if(!empty($res_add)){
            $res = json_decode($res_add, TRUE);
            $return['status_code'] = '200';
            $return['group_id'] = intval($res['id']);
            return response()->json($return);
        }

        $return['status_code'] = '101';
        $return['error_message'] = '创建用户分组失败，请重试';
        return response()->json($return);
    }

    /**
     * 用户组添加用户时检测用户
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function groupCheckUser(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        $gid = $request->input('gid', 0);
        $uid = $request->input('uid', 0);
        if ( !$gid) {
            $return['status_code'] = '101';
            $return['error_message'] = '请选择用户组';
            return response()->json($return);
        }
        if ( !$uid) {
            $return['status_code'] = '101';
            $return['error_message'] = '请填写用户ID';
            return response()->json($return);
        }

        //判断用户组是否存在
        $groupInfo = group::where('id', $gid)->first();
        if(empty($groupInfo)){
            $return['status_code'] = '101';
            $return['error_message'] = '该用户组不存在';
            return response()->json($return);
        }

        //获取用户信息
        $userInfo = client::select('id AS uid' , 'nickname', 'avatarurl')->where('id',$uid)->first();
        if(empty($userInfo)){
            //用户不存在
            $return['status_code'] = '101';
            $return['error_message'] = '该用户不存在';
            return response()->json($return);
        }
        $userInfo = $userInfo->toArray();

        //判断改用户是否存在于用户组中
        $clientGroupInfo = client_group::where('uid', $uid)->first();
        if(!empty($clientGroupInfo)){
            $return['status_code'] = '101';
            $return['error_message'] = '该用户已存在于用户组';
            return response()->json($return);
        }

        $return['status_code'] = '200';
        $return['data'] = $userInfo;
        return response()->json($return);
    }

    /**
     * 设置用户组操作。绑定，解绑
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function groupSet(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        $gid = $request->input('gid', 0);
        $name = $request->input('name', '');
        $bind = $request->input('bind', '');
        $untie = $request->input('untie', '');

        if ( !$gid) {
            $return['status_code'] = '101';
            $return['error_message'] = '请选择用户组';
            return response()->json($return);
        }

        if (empty($name)) {
            $return['status_code'] = '101';
            $return['error_message'] = '请填写用户组名';
            return response()->json($return);
        }

        //判断用户组是否存在
        $groupInfo = group::where('id', $gid)->first();
        if(empty($groupInfo)){
            $return['status_code'] = '101';
            $return['error_message'] = '该用户组不存在';
            return response()->json($return);
        }

        if(!empty($bind)){
            //绑定用户
            $bindUserId = explode(',', $bind);
            foreach ($bindUserId AS $key=>$val) {
                if(!empty($val)){
                    //判断改用户是否存在于用户组中
                    $clientGroupInfo = client_group::where('uid', $val)->first();
                    if(!empty($clientGroupInfo)){
                        $return['status_code'] = '101';
                        $return['error_message'] = '用户' . $val . '已存在于用户组';
                        return response()->json($return);
                    }
                    //进行用户组绑定
                    $client_group = [];
                    $client_group['gid'] = $gid;
                    $client_group['uid'] = $val;
                    $client_group['create_time'] = time();
                    client_group::create($client_group);
                }
            }
        }

        if(!empty($untie)){
            //解绑用户
            $untieUserId = explode(',', $untie);
            foreach ($untieUserId AS $key=>$val) {
                if(!empty($val)){
                    //判断改用户是否存在于用户组中
                    $clientGroupInfo = client_group::where('gid', $gid)->where('uid', $val)->first();
                    if(empty($clientGroupInfo)){
                        $return['status_code'] = '101';
                        $return['error_message'] = '用户' . $val . '不存在于该用户组';
                        return response()->json($return);
                    }
                    //进行用户组解绑
                    client_group::where('gid', $gid)->where('uid', $val)->delete();
                }
            }
        }

        group::where('id', $gid)->update(['name'=>$name]);

        $return['status_code'] = '200';
        return response()->json($return);
    }

    /**
     * 获取组别相关信息
     * @param Request $request
     * @param $gid
     * @return \Illuminate\Http\JsonResponse
     */
    public function groupInfo(Request $request, $gid){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }
        //判断用户组是否存在
        $groupInfo = group::where('id', $gid)->first();
        if(empty($groupInfo)){
            $return['status_code'] = '101';
            $return['error_message'] = '该用户组不存在';
            return response()->json($return);
        }

        $groupInfo = $groupInfo->toArray();
        $groupInfo['bindList'] = $bindList = [];
        $client_group = client_group::select('client_group.gid', 'client_group.uid', 'client_group.create_time', 'client.nickname', 'client.avatarurl');
        $client_group->LeftJoin('client', 'client_group.uid', 'client.id');
        $client_group->where('client_group.gid',$gid);
        $bindList = $client_group->get()->toArray();
        $groupInfo['bindList'] = $bindList;

        $return['status_code'] = '200';
        $return['data'] = $groupInfo;
        return response()->json($return);
    }

    /**
     * 获取用户绑定提现渠道信息
     * @param Request $request
     * @param $uid
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientAccount(Request $request , $uid){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if ( array_key_exists('code', $clients)) {
            if ($clients['code'] == '401') {
                $return['status_code'] = 10001;
                $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
                return response()->json($return);
            }
        }

        $data = client_account::where('uid', $uid)->get();
        $return['status_code'] = '200';
        $return['data'] = $data->ToArray();
        return response()->json($return);
    }


	public function updateClientAccount(Request $request , $uid){
		$token = JWTAuth::getToken();
		$clients = $this->getUserInfo($token);

		if (!empty($clients['status_code'])) {
			if ($clients['status_code'] == '401') {
				$error['status_code'] = '10001';
				$error['error_message'] = '用户token验证失败， 请刷新重试1';
				return response()->json($error);
			}
		}

		// 验证权限
		$roles = ['root', 'admin'];
		if ( !in_array($clients['role'], $roles)) {
			$return['status_code'] = '10002';
			$return['error_message'] = '权限不足';
			return response()->json($return);
		}
		$user = [];
		$id = '';
		if( $request->input('id', '') != '')
			$id = $request->input('id', '');
		if( $request->input('account', '') != '')
			$user['account'] = $request->input('account', '');
		if( $request->input('bank', '') != '')
			$user['bank'] = $request->input('bank', '');
		if ( $request->input('id_card', '') != '')
			$user['id_card'] = $request->input('id_card', '');
		if ( $request->input('name', '') != '')
			$user['name'] = $request->input('name', '');


		if($id==""){
			$return['status_code'] = '10002';
			$return['error_message'] = '请输入id';
			return response()->json($return);
		}

		client_account::where('id', $id)->update($user);

		$return['status_code'] = '200';

		return response()->json($return);







	}


    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            $key = 'gzh_access_token_subscribe';

        }
        $re = Redis::exists($key);
        if ($re) {
//      if (false) {

            return Redis::get($key);
        } else {
            //小程序
            if ($type == 1) {
                $appid = 'wx1ad97741a12767f9';
                $appsecret = '001b7d3059af1a707a5d4e432aa45b7a';
            } else {
                //公众号
                $appid = config("wxxcx.wechat_appid");
                $appsecret = config("wxxcx.wechat_appsecret");

                $key = 'gzh_access_token_subscribe';
            }
            $action_url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appid}&secret={$appsecret}";
            $outopt = file_get_contents($action_url);
            $data = json_decode($outopt, True);
            Redis::setex($key,7000, $data['access_token']);
            return $data['access_token'];
        }
    }


    public function postCurl($url, $data, $type) {
        if ($type == 'json') {
            $data = json_encode($data);//对数组进行json编码
            $header = array(
                "Content-type: application/json;charset=UTF-8",
                "Accept: application/json",
                "Cache-Control: no-cache",
                "Pragma: no-cache"
            );
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        $res = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Error+' . curl_error($curl);
        }
        curl_close($curl);
        return $res;
    }

    /*
     * 后台用户个性签名列表
     */
    public function signatureList(Request $request) {
        $token = JWTAuth::getToken();
        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }
        $page = $request->input('page', 1);
        $page_num = $request->input('page_num', 20);
        $user_id = $request->input('user_id', 0);
        $nickname = trim($request->input('nickname', ''));
        $status = $request->input('status', 0);
        $query = client_signature_log::select();
        if ($user_id) {
            $query->where('uid', $user_id);
        }
        if ($nickname) {
            $query->where('nickname', 'like', '%' . $nickname . '%');
        }
        $query->where('status', $status);
        $query->orderBy('createtime', 'desc');
        $offset = ($page - 1) * $page_num;
        $query->offset($offset);
        $query->limit($page_num);
        $data = $query->get();
        $data = $data->toArray();
        $return['status_code'] = '200';
        $return['data'] = $data;
        return response()->json($return);
    }

}

