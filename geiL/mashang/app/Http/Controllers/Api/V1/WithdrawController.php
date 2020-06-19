<?php

namespace App\Http\Controllers\Api\V1;

use App\models\client_extra;
use App\models\client_money_change;
use App\models\client_withdraw;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Ramsey\Uuid\Uuid;
use App\models\client_log;

class WithdrawController extends BaseController
{

    /**
     * 获取用户提现记录
     */
    public function getWithdraw(Request $request)
    {
        $token = JWTAuth::getToken();
        $uid = $request->input('uid', '');
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        $data = client_withdraw::where('uid', $uid)->get();

        $return['status_code'] = '200';
        $return['data'] = $data->ToArray();

        return response()->json($return);
    }


    /**
     *  用户提现申请
     */
    public function postWithdraw(Request $request)
    {
        $balance = $request->input('balance', '');
        $token = JWTAuth::getToken();
        $uid = $request->input('uid', '');
        $clients = $this->UserInfo($token);
        if (empty($token) || $clients['id'] != $uid) {
            $return['status_code'] = 10001;
            $return['error_message'] = "token 失效或异常， 以正常渠道获取重试";
            return response()->json($return);
        }

        if ( $balance < 10 || $balance > 50000 ) {
            $return['status_code'] = '10002';
            $return['error_message'] = '提现金额最低十元， 最高五万元';
            return response()->json($return);
        }

        // 判断手机号是否验证
        if( $clients['status'] != 5 ) {
            $return['status_code'] = '10003';
            $return['error_message'] = '用户手机号未验证';
            return response()->json($return);
        }

        $profile = client_extra::LeftJoin('client_rate', 'client_rate.uid', 'client_extra.id')->where('client_extra.id', $uid)->first();

        // 判断发送金额是否超过
        if( $balance > $profile['balance']) {
            $return['status_code'] = '10004';
            $return['error_message'] = '大于所剩余额';
            return response()->json($return);
        }

        // 判断是否有正在提现的进度
        $bar = client_withdraw::where('uid', $uid)->where('status', '<>', '4')->first();
        if( $bar ) {
            $return['status_code'] = '10005';
            $return['error_message'] = '已有正在提现的进度';
            return response()->json($return);
        }
        
        if(time() >= 1542859200){
            // 2018年11月22日中午12点之后费率3%
            $profile['rate'] = 3;
        } else {
            $profile['rate'] = 1;
        }

        // if( empty($profile['rate']) ) {
        //     $profile['rate'] = 5;
        // }

        // $service = number_format($balance * ($profile['rate'] / 100), 2);
        $service = doubleval($balance * ($profile['rate'] / 100));

        $withdraw['uid'] = $uid;
        $withdraw['service_fee'] = $service;
        $withdraw['balance'] = $balance;
        $withdraw['status'] = 1;
        $withdraw['createtime'] = date('Y-m-d H:i:s', time());
        client_withdraw::create($withdraw);

        // 相减用户金额
        client_extra::where('id', $uid)->decrement('balance', $balance);

        // 用户附表增加提现申请
        client_extra::where('id', $uid)->increment('withdrawing', $balance-$service);

        //记录金额变更（提现，不含手续费）
        client_money_change::setChange($uid, $balance-$service, 2, 4);

        //记录金额变更（提现手续费）
        client_money_change::setChange($uid, $service, 2, 5);

        // 用户日志记录
        $log['uid'] = $uid;
        $log['description'] = '5';
        $log['createtime'] = date('Y-m-d H:i:s', time());
        client_log::create($log);

        $return['status_code'] = '200';
        return response()->json($return);
    }


}
