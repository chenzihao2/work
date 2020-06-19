<?php

namespace App\Http\Controllers\Admin;

use App\models\client_account;
use App\models\client_extra;
use App\models\client_money_change;
use App\models\client_withdraw;
use App\models\discount;
use App\models\financial_data;
use App\models\manual_record;
use App\models\rate;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\models\client;

class WithdrawController extends BaseController
{
    // 提现管理

    /**
     * 提现列表
     */
    public function getWithdraw(Request $request)
    {
        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = $request->input('query', '');
        $sort = $request->input('sort', '');
        $query = json_decode($query, True);
        $sort = json_decode($sort, True);
        $offset = $page * $numperpage;

        $withdraw = client_withdraw::select('client_withdraw.id', 'service_fee', 'balance', 'uid', 'client_withdraw.status', 'client_withdraw.createtime', 'audittime', 'completetime', 'nickname', 'client_withdraw.is_manual' );
        $withdraw->LeftJoin('client', 'client_withdraw.uid', 'client.id');
        if ( $offset != 0 )
            $withdraw->offset($offset);
            $withdraw->limit($numperpage);

        if ( !empty( $query['createtime']['from']))
            $withdraw->whereBetween('client_withdraw.createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        if ( !empty( $query['uid'] ) )
            $withdraw->where('client_withdraw.uid', $query['uid'] );

        if ( !empty( $query['status'] ) )
            $withdraw->where('client_withdraw.status', $query['status'] );

        if ( !empty( $query['balance'] ) )
            $withdraw->where('balance', '>=', $query['balance']);

        if(isset($query['is_manual']))
            $withdraw->where('is_manual', $query['is_manual']);

        if ( !empty( $sort['balance'] ) )
            $withdraw->orderBy('balance', $sorts[$sort['balance']]);

        if ( isset( $sort['createtime'] ) )
            $withdraw->orderBy('client_withdraw.createtime', $sorts[$sort['createtime']]);

        $data = $withdraw->orderBy('completetime', 'desc')->get();

        $withdraw = client_withdraw::select();

        if ( !empty( $query['createtime']))
            $withdraw->whereBetween('client_withdraw.createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        if ( !empty( $query['status'] ) )
            $withdraw->where('status', $query['status'] );

        if ( !empty( $query['uid'] ) )
            $withdraw->where('client_withdraw.uid', $query['uid'] );

        if ( !empty( $query['balance'] ) )
            $withdraw->where('balance', '>', $query['balance']);

        $count = $withdraw->count();

        $pagenum = ceil($count/$numperpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();
        return response()->json($return);

    }

    public function export(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = json_decode($request->input('query', ''),true);
        $sort = json_decode($request->input('sort', ''), True);
        $offset = $page * $numperpage;

        $withdraw = client_withdraw::select('client_withdraw.id', 'client_withdraw.service_fee', 'client_withdraw.balance', 'client_withdraw.uid', 'client_withdraw.status',
            'client_withdraw.createtime', 'audittime', 'completetime', 'nickname','client.telephone','client_rate.grade','client_rate.rate',
            'client_extra.balance as extra_num','client_extra.total as inbalance','client_extra.withdrawed','client_extra.payed as payedNum',
            'client_extra.service_fee as all_service_fee');
        $withdraw->LeftJoin('client', 'client_withdraw.uid', 'client.id');
        $withdraw->LeftJoin('client_rate','client_withdraw.uid','client_rate.uid');
        $withdraw->LeftJoin('client_extra','client_withdraw.uid','client_extra.id');

        if ( !empty( $query['createtime']['from']))
            $withdraw->whereBetween('client_withdraw.createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        if ( !empty( $query['uid'] ) )
            $withdraw->where('client_withdraw.uid', $query['uid'] );

        if ( !empty( $query['status'] ) )
            $withdraw->where('client_withdraw.status', $query['status'] );

        if ( !empty( $query['balance'] ) )
            $withdraw->where('client_withdraw.balance', '>=', $query['balance']);

        if ( !empty( $sort['balance'] ) )
            $withdraw->orderBy('client_withdraw.balance', $sorts[$sort['balance']]);

        if ( isset( $sort['createtime'] ) )
            $withdraw->orderBy('client_withdraw.createtime', $sorts[$sort['createtime']]);

        $dataList = $withdraw->orderBy('completetime', 'desc')->get()->toArray();

        $res[] = ['用户ID','用户昵称','提现金额','提现服务费','提现时间','审核时间','完成时间','提现状态','手机号','级别',
            '汇率','余额','总收入','已提现金额','已支付金额','服务费总额'];
        foreach ($dataList as $value){
            $rate = $value['rate'].'%';
            if($value['status'] == 1){
             $status = '提交申请';
            }else if($value['status'] == 2){
                $status = '审核中';
            }else if($value['status'] == 4){
                $status = '提现完成';
            }
	    $nickname = $this->userTextEncode($value['nickname']);
            $data = [
                $value['uid'],$nickname,$value['balance'],$value['service_fee'],
                $value['createtime'], $value['audittime'], $value['completetime'],$status,
                $value['telephone'],$value['grade'],$rate,$value['extra_num'],$value['inbalance'],
                $value['withdrawed'],$value['payedNum'],$value['all_service_fee']
            ];
            array_push($res,$data);
        }

        Excel::create('提现列表',function($excel) use ($res){
            $excel->sheet('score', function($sheet) use ($res){
                $sheet->rows($res);
            });
        })->export('xls');
    }

    /**
     * 提现状态更新（体现账号被封，临时办法，更新用户体现数据，以及各种金额）
     */
    public function putWithdrawUpdateTest(Request $request, $wid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $manual = $request->input('manual', '');
        if(!$wid){
            $return['status_code'] = '101';
            $return['error_message'] = '请选择要操作的打款记录';
            return response()->json($return);
        }
        if(empty($manual)){
            $return['status_code'] = '101';
            $return['error_message'] = '请填写打款信息';
            return response()->json($return);
        }


        // 修改信息
        $source = client_withdraw::where('id', $wid)->first()->toArray();
        if($source['status'] == 4){
            $return['status_code'] = '101';
            $return['error_message'] = '该记录打过款了';
            return response()->json($return);
        }

        $date = date('Y-m-d H:i:s', time());
        if( $source['status'] == 1 ) {
            $update['status'] = 2;
            $update['audittime'] = $date;
            client_withdraw::where('id', $wid)->update($update);
            $return['status_code'] = '200';
            $return['status'] = '2';
            return response()->json($return);
        }

        if ( $source['status'] == 2 ) {
            //添加打款记录
            $manual = json_decode($manual, TRUE);
            $manual['relation_id'] = $wid;
            $manual['type'] = 1;
            $manual['create_time'] = time();

            $res_manual = manual_record::create($manual);
            if($res_manual){
                $update['status'] = '4';
                $update['is_manual'] = 1;
                $update['completetime'] = $date;
                client_withdraw::where('id', $wid)->update($update);
                client_extra::where('id', $source['uid'])->decrement('withdrawing', $source['balance'] - $source['service_fee']);
                client_extra::where('id', $source['uid'])->increment('withdrawed', $source['balance'] - $source['service_fee']);
                // 增加用户对应的服务费
                client_extra::where('id', $source['uid'])->increment('service_fee', $source['service_fee']);
                $return['status_code'] = '200';
                $return['status'] = 4;
                return response()->json($return);
            }

            $return['status_code'] = '101';
            $return['error_message'] = '打款信息添加失败';
            return response()->json($return);

        }
    }

    /**
     * 提现状态更新
     */
    public function putWithdrawUpdate(Request $request, $wid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        // 修改信息
        $source = client_withdraw::where('id', $wid)->first();

        $date = date('Y-m-d H:i:s', time());
        if( $source['status'] == 1 ) {
            $update['status'] = 2;
            $update['audittime'] = $date;
            client_withdraw::where('id', $wid)->update($update);
            $return['status_code'] = '200';
            $return['status'] = '2';
            return response()->json($return);
        }

        if ( $source['status'] == 2 ) {
//            $clients = client::where('id', $source['uid'])->first();
//            $data['partner_trade_no'] = $source['id'];
//            $data['openid'] = $clients['openid'];
//            $data['serviceid'] = $clients['serviceid'];
//            $data['balance'] = $source['balance'];
//            $data['balance'] = $source['balance'] - $source['service_fee'];

            //$notify = $this->play($data);
//            $notify = $this->wechatWithdraw($data);

//			if ( $notify['return_code'] == 'SUCCESS' ) {
//				if($notify['result_code'] == 'FAIL'){
//					$failReturn['status_code'] = '10008';
//					$failReturn['error_message'] = $notify['err_code_des'];
//					return response()->json($failReturn);
//				}
//				$update['status'] = '4';
//				$update['completetime'] = $date;
//				client_withdraw::where('id', $wid)->update($update);
//				client_extra::where('id', $source['uid'])->decrement('withdrawing', $source['balance'] - $source['service_fee']);
//				client_extra::where('id', $source['uid'])->increment('withdrawed', $source['balance'] - $source['service_fee']);
//				// 增加用户对应的服务费
//				client_extra::where('id', $source['uid'])->increment('service_fee', $source['service_fee']);
//				$return['status_code'] = '200';
//				$return['status'] = 4;
//				return response()->json($return);
//			}

			//切换为支付宝打款

			$aliPayInfo = client_account::where('uid', $source['uid'])->where('type',2)->first()->toArray();
			if(empty($aliPayInfo)){
				$return['status_code'] = "10009";
				$return['error_message'] = "支付宝信息未录入";
				return response()->json($return);
			}

			$data['order'] = $source['id'];
			$data['name'] = $aliPayInfo['name'];
			$data['account'] = $aliPayInfo['account'];
			$data['amount'] = $source['balance'] - $source['service_fee'];
			$notify  = $this->aliPayFund($data);

			if ( $notify['status'] == true ) {
				$update['status'] = '4';
				$update['completetime'] = $date;
				client_withdraw::where('id', $wid)->update($update);
				client_extra::where('id', $source['uid'])->decrement('withdrawing', $source['balance'] - $source['service_fee']);
				client_extra::where('id', $source['uid'])->increment('withdrawed', $source['balance'] - $source['service_fee']);
				// 增加用户对应的服务费
				client_extra::where('id', $source['uid'])->increment('service_fee', $source['service_fee']);
				$return['status_code'] = '200';
				$return['status'] = 4;
				return response()->json($return);
			}else{
				$failReturn['status_code'] = '10008';
				$failReturn['error_message'] = $notify['msg'];
				return response()->json($failReturn);
			}
        }

    }




    /**
     * 获取单个用户提现记录
     */
    public function getClientBrief(Request $request, $uid)
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

        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $withdraw = client_withdraw::where('uid', $uid)->get();

        $return['status_code'] = '200';
        $return['data'] = $withdraw->ToArray();
        return response()->json($return);
    }

    /**
     * xml 转换为 array
     * @param xml
     * @return array
     */
    public function xmlToArray($xml)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);

        $xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        $val = json_decode(json_encode($xmlstring), true);

        return $val;
    }


    /**
     * 微信api 企业给用户打款
     */
    public function play($data)
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        $pre = [];
        $pre['mch_appid'] = config('wxxcx.wechat_appid');
        $pre['mchid'] = config('pay.wxpay.outmchid');
        $pre['nonce_str'] = md5(time() . mt_rand(0,1000));
        $pre['partner_trade_no'] = $data['partner_trade_no'];
        $pre['openid'] = $data['openid'];
        $pre['check_name'] = 'NO_CHECK';
        $pre['amount'] = $data['balance'] * 100;
        $pre['desc'] = '您已成功提现'.($pre['amount'] / 100). '元';
        $pre['spbill_create_ip'] = config('pay.wxpay.spbill_create_ip');
        $secrect_key = config('pay.wxpay.out_mch_secret_key');
        ksort($pre);
        $sign = '';
        foreach ($pre as $key => $value) {
            $sign .= "{$key}={$value}&";
        }
        $sign .= 'key='.$secrect_key;
        $pre['sign'] = strtoupper(md5($sign));
        $xml = $this->arraytoxml($pre);
        $data = $this->https_curl_json($url, $xml, 'xml', '1');
        $return = $this->xmlToArray($data);
        return $return;
    }
    public function wechatWithdraw($data, $pay_type = 1, $date = '2018-01-01')
    {
        $url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";
        $pre['mch_appid'] = config("wxxcx.wechat_appid");
        $pre['mchid'] = config("pay.wxpay.outmchid");
        $pre['nonce_str'] = md5(time() . mt_rand(0,1000));
        $pre['partner_trade_no'] = $data['partner_trade_no'];
        $pre['openid'] = $data['serviceid'];
        $pre['check_name'] = 'NO_CHECK';
        $pre['amount'] = $data['balance'] * 100;
        if($pay_type == 1){
            $pre['desc'] = '您已成功提现'.($pre['amount'] / 100). '元';
        } elseif($pay_type == 2) {
            $pre['desc'] = $date . '给料优惠服务费到账'.($pre['amount'] / 100). '元';
        }
        $pre['spbill_create_ip'] = config('pay.wxpay.spbill_create_ip');
        $secrect_key = config("pay.wxpay.out_mch_secret_key");
        ksort($pre);
        $sign = '';
        foreach ($pre as $key => $value) {
            $sign .= "{$key}={$value}&";
        }
        $sign .= 'key='.$secrect_key;
        $pre['sign'] = strtoupper(md5($sign));
        $xml = $this->arraytoxml($pre);
        $data = $this->https_curl_json($url, $xml, 'xml', '1');
        $return = $this->xmlToArray($data);
        return $return;
    }


    /**
     * 查询财务数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function financial(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];

        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = $request->input('query', '');
        $query = json_decode($query, TRUE);

        $sort = $request->input('sort', '');
        $sort = json_decode($sort, TRUE);

        $offset = $page * $numperpage;

        $financial = financial_data::select();

        if ( !empty( $query['date']['from']))
            $financial->whereBetween('date', [$query['date']['from'], $query['date']['to']]);

        if ( $offset != 0 )
            $financial->offset($offset);
            $financial->limit($numperpage);

        if ( !empty( $sort['date'] ) ) {
            $financial->orderBy('date', $sorts[$sort['date']]);
        } else {
            $financial->orderBy('date', 'desc');
        }

        if ( !empty( $sort['account_flow'] ) )
            $financial->orderBy('account_flow', $sorts[$sort['account_flow']]);

        if ( !empty( $sort['original_service_fee'] ) )
            $financial->orderBy('original_service_fee', $sorts[$sort['original_service_fee']]);

        if ( !empty( $sort['discount_fee'] ) )
            $financial->orderBy('discount_fee', $sorts[$sort['discount_fee']]);

        if ( !empty( $sort['tencent_fee'] ) )
            $financial->orderBy('tencent_fee', $sorts[$sort['tencent_fee']]);

        if ( !empty( $sort['profit'] ) )
            $financial->orderBy('profit', $sorts[$sort['profit']]);


        $financialList = $financial->get()->toArray();

        $return['status_code'] = '200';
        $return['data'] = $financialList;
        return response()->json($return);
    }



    /**
     * 导出财务数据excle
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function financialExport(Request $request){

        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];

        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = $request->input('query', '');
        $query = json_decode($query, TRUE);

        $sort = $request->input('sort', '');
        $sort = json_decode($sort, TRUE);

        $offset = $page * $numperpage;

        $financial = financial_data::select();

        if ( !empty( $query['date']['from']))
            $financial->whereBetween('date', [$query['date']['from'], $query['date']['to']]);

        if ( $offset != 0 )
            $financial->offset($offset);
        $financial->limit($numperpage);

        if ( !empty( $sort['date'] ) ) {
            $financial->orderBy('date', $sorts[$sort['date']]);
        } else {
            $financial->orderBy('date', 'desc');
        }

        if ( !empty( $sort['account_flow'] ) )
            $financial->orderBy('account_flow', $sorts[$sort['account_flow']]);

        if ( !empty( $sort['original_service_fee'] ) )
            $financial->orderBy('original_service_fee', $sorts[$sort['original_service_fee']]);

        if ( !empty( $sort['discount_fee'] ) )
            $financial->orderBy('discount_fee', $sorts[$sort['discount_fee']]);

        if ( !empty( $sort['tencent_fee'] ) )
            $financial->orderBy('tencent_fee', $sorts[$sort['tencent_fee']]);

        if ( !empty( $sort['profit'] ) )
            $financial->orderBy('profit', $sorts[$sort['profit']]);

        $financialList = $financial->get()->toArray();

        $res[] = ['日期','流水总额', '原始服务费', '优惠发放服务费', '腾讯服务费', '毛利'];
        foreach ($financialList as $value){
            $data = [
                $value['date'],$value['account_flow'],$value['original_service_fee'], $value['discount_fee'], $value['tencent_fee'], $value['profit']
            ];
            array_push($res,$data);
        }
        Excel::create('财务数据',function($excel) use ($res){
            $excel->sheet('score', function($sheet) use ($res){
                $sheet->rows($res);
            });
        })->export('xls');
    }

    /**
     * 服务费率列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function rateList(){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }
        $rate_list = rate::orderBy('status', 'desc')->orderBy('update_time', 'desc')->get()->toArray();
        foreach ($rate_list AS $key=>$val) {
            $rate_list[$key]['rate'] = json_decode($val['rate'], TRUE);
        }

        $return['status_code'] = '200';
        $return['data'] = $rate_list;
        return response()->json($return);

    }

    /**
     * 获取费率合集详情
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rateInfo(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }
        $rateId = $request->input('rateid', 0);
        if(!$rateId){
            $return['status_code'] = '101';
            $return['error_message'] = '请选择费率集合';
            return response()->json($return);
        }

        $rateInfo = rate::where('id', $rateId)->first()->toArray();
        $rateInfo['rate'] = json_decode($rateInfo['rate'], TRUE);

        $return['status_code'] = '200';
        $return['data'] = $rateInfo;
        return response()->json($return);
    }

    /**
     * 设置服务费率
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setRate(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }
        $rateId = $request->input('rateid', 0);
        $rate = $request->input('rate', '');
//        $rate = '[{"money":2000,"rate":5},{"money":4000,"rate":3},{"money":6000,"rate":2}]';
        if(empty($rate)){
            $return['status_code'] = '101';
            $return['error_message'] = '请输入费率';
            return response()->json($return);
        }

        if($rateId){
            //编辑
            $rateInfo = rate::where('id', $rateId)->first()->toArray();
            if($rateInfo['status'] == 1){
                $return['status_code'] = '101';
                $return['error_message'] = '请下线后再修改';
                return response()->json($return);
            }
            $data = [];
            $data['rate'] = $rate;
            $data['update_time'] = time();
            $res_rate = rate::where('id', $rateId)->update($data);
        } else {
            //新增
            $data = [];
            $data['rate'] = $rate;
            $data['status'] = 0;    //默认为下线状态
            $data['create_time'] = $data['update_time'] = time();
            $res_rate = rate::create($data);
        }

        if($res_rate){
            $return['status_code'] = '200';
            return response()->json($return);
        }

        $return['status_code'] = '101';
        $return['error_message'] = '费率操作失败，请重试';
        return response()->json($return);

    }

    /**
     * 上线服务费率
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function onlineRate(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $rateId = $request->input('rateid', 0);
        if(!$rateId){
            $return['status_code'] = '101';
            $return['error_message'] = '请选择操作费率';
            return response()->json($return);
        }

        $res_rate = rate::where('id', $rateId)->update(['status'=>1, 'update_time'=>time()]);
        if($res_rate){
            $return['status_code'] = '200';
            return response()->json($return);
        } else {
            $return['status_code'] = '101';
            $return['error_message'] = '服务费率上线失败，请重试';
            return response()->json($return);
        }

    }

    /**
     * 下线服务费率操作
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function downlineRate(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $rateId = $request->input('rateid', 0);
        if(!$rateId){
            $return['status_code'] = '101';
            $return['error_message'] = '请选择操作费率';
            return response()->json($return);
        }

        $res_rate = rate::where('id', $rateId)->update(['status'=>0, 'update_time'=>time()]);
        if($res_rate){
            $return['status_code'] = '200';
            return response()->json($return);
        } else {
            $return['status_code'] = '101';
            $return['error_message'] = '服务费率下线失败，请重试';
            return response()->json($return);
        }
    }


    /**
     * 发放优惠列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discountList(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];

        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = $request->input('query', '');
        $query = json_decode($query, TRUE);

        $sort = $request->input('sort', '');
        $sort = json_decode($sort, TRUE);

        $offset = $page * $numperpage;

        $discount = discount::select();

        if ( !empty( $query['uid'] ) )
            $discount->where('uid', $query['uid'] );

        if ( !empty( $query['date']['from']))
            $discount->whereBetween('date', [$query['date']['from'], $query['date']['to']]);

        $discount->orderBy('status', 'asc');

        if ( !empty( $sort['date'] ) ) {
            $discount->orderBy('date', $sorts[$sort['date']]);
        } else {
            $discount->orderBy('date', 'desc');
        }

        if ( !empty( $sort['money'] ) )
            $discount->orderBy('money', $sorts[$sort['money']]);

        if ( !empty( $sort['discount_service_fee'] ) )
            $discount->orderBy('discount_service_fee', $sorts[$sort['discount_service_fee']]);

        if ( !empty( $sort['discount_fee'] ) )
            $discount->orderBy('discount_fee', $sorts[$sort['discount_fee']]);


        if ( $offset != 0 )
            $discount->offset($offset);

        $discount->limit($numperpage);

        $data = $discount->get();

        $discountCount = discount::select();
        if ( !empty( $query['uid'] ) )
            $discountCount->where('uid', $query['uid'] );

        if ( !empty( $query['date']['from']))
            $discountCount->whereBetween('date', [$query['date']['from'], $query['date']['to']]);

        $count = $discountCount->count();

        $pagenum = ceil($count/$numperpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();
        return response()->json($return);

    }


    /**
     * 发放优惠列表
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discountExport(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];

        $query = $request->input('query', '');
        $query = json_decode($query, TRUE);

        $sort = $request->input('sort', '');
        $sort = json_decode($sort, TRUE);

        $discount = discount::select();

        if ( !empty( $query['uid'] ) )
            $discount->where('uid', $query['uid'] );

        if ( !empty( $query['date']['from']))
            $discount->whereBetween('date', [$query['date']['from'], $query['date']['to']]);

        $discount->orderBy('status', 'asc');

        if ( !empty( $sort['date'] ) ) {
            $discount->orderBy('date', $sorts[$sort['date']]);
        } else {
            $discount->orderBy('date', 'desc');
        }
        if ( !empty( $sort['money'] ) )
            $discount->orderBy('money', $sorts[$sort['money']]);

        if ( !empty( $sort['discount_service_fee'] ) )
            $discount->orderBy('discount_service_fee', $sorts[$sort['discount_service_fee']]);

        if ( !empty( $sort['discount_fee'] ) )
            $discount->orderBy('discount_fee', $sorts[$sort['discount_fee']]);

        $data = $discount->get()->toArray();

        $res[] = ['用户ID','用户昵称', '日期', '总流水', '原始汇率', '优惠汇率', '原始服务费', '优惠服务费', '应发放优惠', '是否手动发放', '状态'];
        foreach ($data as $value){
            $original_rate = $value['original_rate'].'%';
            $discount_rate = $value['discount_rate'].'%';
            if($value['status'] == 0){
                $status = '未打款';
            }else if($value['status'] == 1){
                $status = '红黑单未处理';
            }else if($value['status'] == 2){
                $status = '已发放';
            }
            if($value['is_manual'] == 0){
                $is_manual = '自动发放';
            } elseif ($value['is_manual'] == 1){
                $is_manual = '手动发放';
            }
            $nickname = $this->userTextEncode($value['nickname']);

            $data = [
                $value['uid'], $nickname, $value['date'], $value['money'], $original_rate, $discount_rate,  $value['original_service_fee'], $value['discount_service_fee'], $value['discount_fee'], $is_manual, $status
            ];
            array_push($res,$data);
        }
        Excel::create('优惠数据',function($excel) use ($res){
            $excel->sheet('score', function($sheet) use ($res){
                $sheet->rows($res);
            });
        })->export('xls');

    }


    /**
     * 自动发放优惠
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function discountAuto(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $did = $request->input('did', '');  //优惠标识ID
        if(!$did){
            $return['status_code'] = '101';
            $return['error_message'] = '请选择要操作的打款记录';
            return response()->json($return);
        }

        // 修改信息
        $discountInfo = discount::where('id', $did)->first()->toArray();
        if($discountInfo['status'] == 2){
            $return['status_code'] = '101';
            $return['error_message'] = '该记录打过款了';
            return response()->json($return);
        }
        if($discountInfo['status'] == 1){
            $return['status_code'] = '101';
            $return['error_message'] = '该记录有红黑单未处理，暂不能打款';
            return response()->json($return);
        }

        if ( $discountInfo['status'] == 0 ) {
            $clients = client::where('id', $discountInfo['uid'])->first();

            //操作微信打款
            $data['partner_trade_no'] = $discountInfo['id'];
            $data['openid'] = $clients['openid'];
            $data['serviceid'] = $clients['serviceid'];
            $data['balance'] = $discountInfo['discount_fee'];
            $notify = $this->wechatWithdraw($data, 2, $discountInfo['date']);

            if ( $notify['return_code'] == 'SUCCESS' ) {
                if($notify['result_code'] == 'FAIL'){
                    $failReturn['status_code'] = '10008';
                    $failReturn['error_message'] = $notify['err_code_des'];
                    return response()->json($failReturn);
                }
                $update['status'] = 2;
                $update['is_manual'] = 1;
                $update['send_time'] = time();
                discount::where('id', $did)->update($update);

                //增加用户相对应的提现总额
                client_extra::where('id', $discountInfo['uid'])->increment('withdrawed', $discountInfo['discount_fee']);
                // 减少用户对应的服务费
                // client_extra::where('id', $discountInfo['uid'])->decrement('service_fee', $discountInfo['discount_fee']);

                //记录金额变更
                client_money_change::setChange($discountInfo['uid'], $discountInfo['discount_fee'], 1, 6);

                $return['status_code'] = '200';
                $return['status'] = 2;
                return response()->json($return);
            }
            $return['status_code'] = '101';
            $return['error_message'] = '打款失败';
            return response()->json($return);
        }

    }

    /**
     * 手动发放优惠操作
     */
    public function discountManual(Request $request){
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $did = $request->input('did', '');  //优惠标识ID
        $manual = $request->input('manual', '');
        if(!$did){
            $return['status_code'] = '101';
            $return['error_message'] = '请选择要操作的打款记录';
            return response()->json($return);
        }

        if(empty($manual)){
            $return['status_code'] = '101';
            $return['error_message'] = '请填写打款信息';
            return response()->json($return);
        }

        // 修改信息
        $discountInfo = discount::where('id', $did)->first()->toArray();
        if($discountInfo['status'] == 2){
            $return['status_code'] = '101';
            $return['error_message'] = '该记录打过款了';
            return response()->json($return);
        }
        if($discountInfo['status'] == 1){
            $return['status_code'] = '101';
            $return['error_message'] = '该记录有红黑单未处理，暂不能打款';
            return response()->json($return);
        }

        if ( $discountInfo['status'] == 0 ) {
            //添加打款记录
            $manual = json_decode($manual, TRUE);
            $manual['relation_id'] = $did;
            $manual['type'] = 2;
            $manual['create_time'] = time();
            $res_manual = manual_record::create($manual);

            if($res_manual){
                $update['status'] = 2;
                $update['is_manual'] = 1;
                $update['send_time'] = time();
                discount::where('id', $did)->update($update);

                //增加用户相对应的提现总额
                client_extra::where('id', $discountInfo['uid'])->increment('withdrawed', $discountInfo['discount_fee']);
                // 减少用户对应的服务费
                // client_extra::where('id', $discountInfo['uid'])->decrement('service_fee', $discountInfo['discount_fee']);
                $return['status_code'] = '200';
                $return['status'] = 2;
                return response()->json($return);
            }

            $return['status_code'] = '101';
            $return['error_message'] = '打款信息添加失败';
            return response()->json($return);
        }
    }


	public function aliPayFund($data) {
		$aop = new \AopClient ();
		$aop->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$aop->appId = config("pay.alipay.appid");
		$aop->rsaPrivateKey = config("pay.alipay.private_key");
		$aop->alipayrsaPublicKey=config("pay.alipay.publick_key");
		$aop->apiVersion = '1.0';
		$aop->signType = 'RSA2';
		$aop->postCharset='GBK';
		$aop->format='json';
		$request = new \AlipayFundTransToaccountTransferRequest ();
		$request->setBizContent("{" .
			"\"out_biz_no\":\"".$data['order']."\"," .
			"\"payee_type\":\"ALIPAY_LOGONID\"," .
			"\"payee_account\":\"".$data['account']."\"," .
			"\"amount\":\"".$data['amount']."\"," .
			"\"payer_show_name\":\"给料官方\"," .
			"\"payee_real_name\":\"".$data['name']."\"," .
			"\"remark\":\"您已成功提现".$data['amount']."元\"" .
			"}");
		$result = $aop->execute ( $request);
		$responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
		$resultCode = $result->$responseNode->code;
		if(!empty($resultCode)&&$resultCode == 10000){
			$finalResult['status'] = true;
		} else {
			$finalResult['status'] = false;
		}
		$finalResult['msg'] = $result->$responseNode->msg;
		return $finalResult;
    }

}
