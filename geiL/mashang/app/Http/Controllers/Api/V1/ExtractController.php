<?php

namespace App\Http\Controllers\Api\V1;

use App\extract;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Clients;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class ExtractController extends BaseController
{

    /**
     * 提现接口
     *
     * @SWG\Post(path="/api/extract/apply",
     *   tags={"提现接口"},
     *   summary="提现申请接口",
     *   description="",
     *   operationId="api/v1/extract/apply",
     *   produces={"application/json"},
     *   @SWG\Parameter(
     *     in="formData",
     *     name="token",
     *     type="string",
     *     description="token",
     *     required=true,
     *   ),
     *
     *   @SWG\Response(response="201", description="'code' : '提取码'")
     * )
     */
    public function applicant()
    {
        // 获取用户信息
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists("status_code", $clients)) {
            if($clients['status_code'] == 401) {
                return response()->json(['message' => 'token失效或异常，请刷新'], 401);
            }
        }

        // 金额计算
        $balance = $clients['balance'];

        // 金额必须大于10
        if($balance  < 10) {
            return response()->json(['message' => '提现金额小于一百元'], 403);
        }

        // 是否有重复正在提现的记录
        $repeat = Extract::where("status", 0)->where('uid', $clients['id'])->orderBy("id", "desc")->first();

        if (count($repeat) > 0) {
            return response()->json(['message' => '请联系客服，根据上次提现码进行提现', 'code' => $repeat['code']], 202);
        }

        $server = number_format($balance * 0.05, 2);  // 服务费

        $in = $balance - $server;   // 用户可提取金额

        // 提现码
        $code = $this->StrextractOne();

        $extract['uid'] = $clients['id'];
        $extract['code'] = $code;
        $extract['font_balance'] = $clients['balance'];
        $extract['in_balance'] = $in;
        $extract['server_balance'] = $server;
        $extract['status'] = '0';   // （0 提交 1 审核中 2 已打款）



        DB::beginTransaction();
        try{
            // 添加提现申请， 减去账户余额
            DB::table("extract")->insert($extract);
//            DB::table("clients")->where("id", $clients['id'])->update(['balance' => 0]);
            DB::commit();
        }catch (Exception $e) {
            DB::rollback();
            throw $e;
        };
        $return['font_balance'] = $clients['balance'];
        $return['in_balance'] = $in;
        $return['server_balance'] = $server;
        $return['status'] = '0';
        $return['code'] = $code;

        return response()->json($return, 200);

    }

    /**
     * 提现接口
     *
     * @SWG\Get(path="/api/extract/newcode",
     *   tags={"提现接口"},
     *   summary="获取最新提现码",
     *   description="",
     *   operationId="api/v1/extract/newcode",
     *   produces={"application/json"},
     *   @SWG\Parameter(
     *     in="formData",
     *     name="token",
     *     type="string",
     *     description="token",
     *     required=true,
     *   ),
     *
     *   @SWG\Response(response="default", description="{'status' : '0 当前没有取现码 1 ， 有', 'code' : '取现码'")
     * )
     */
    public function NewCode()
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists("status_code", $clients)) {
            if($clients['status_code'] == 401) {
                return response()->json(['message' => 'token失效或异常，请刷新'], 401);
            }
        }

        $return['status'] = 0;
        $return['code'] = "";
        $code = Extract::where("status", 0)->where("uid", $clients['id'])->orderBy("created_at", "desc")->first();
        if ( !empty($code) ) {
            $return['status'] = 1;
            $return['balance'] = floatval($code['in_balance']); // 当前提取金额的费用
            $return['code'] = $code['code'];
        }
        return response()->json($return);
    }


    /**
     * 提现接口
     *
     * @SWG\Get(path="/api/extract/list",
     *   tags={"提现接口"},
     *   summary="提现列表(暂不用)",
     *   description="",
     *   operationId="api/v1/extract/list",
     *   produces={"application/json"},
     *   @SWG\Parameter(
     *     in="formData",
     *     name="token",
     *     type="string",
     *     description="token",
     *     required=true,
     *   ),
     *
     *   @SWG\Response(response="default", description="{'count' : '提现总金额', 'data' : 'code 提取码  font_balance 提取金额 status （0 提交 1 审核中 2 已打款） created_at 提取时间'")
     * )
     */
    public function CashList()
    {
        $token = JWTAuth::getToken();
        $clients = $this->UserInfo($token);
        if (array_key_exists("status_code", $clients)) {
            if($clients['status_code'] == 401) {
                return response()->json(['message' => 'token失效或异常，请刷新'], 401);
            }
        }

        $data = extract::select('code', 'font_balance', 'in_balance', 'status', 'created_at')->where("uid", $clients['id'])->orderBy("created_at", "desc")->get();
        $count = 0;
        if ( !empty($data) ) {
            foreach ($data->ToArray() as $key => $value) {
                $count += $value['in_balance'];
            }
        }
        $return['count'] = $count;
        $return['data'] = $data;

        return response()->json($return);

    }


    // 唯一提现码生成
    public function StrextractOne()
    {
        mt_srand((double) microtime() * 1000000);

        return date('Ymd') . str_pad(mt_rand(1, 9999), 5, '0', STR_PAD_LEFT);
    }


}
