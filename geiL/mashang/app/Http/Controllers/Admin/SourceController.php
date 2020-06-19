<?php

namespace App\Http\Controllers\Admin;

use App\models\client;
use App\models\client_money_change;
use App\models\contents;
use App\models\order;
use App\models\refund_order;
use App\models\resource;
use App\models\source;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tymon\JWTAuth\Facades\JWTAuth;


class SourceController extends BaseController
{
    // 资源控制器

    /**
     * 获取资源列表
     */
    public function getSource(Request $request)
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

        $roles = ['root', 'admin', 'audit1'];
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
        $source = source::select('source.id', 'source.uid','source.play_start','source.play_end','source.free_watch','source.order_status', 'source.title', 'source.sub_title', 'source.price', 'source.thresh', 'source.status', 'source.createtime', 'source.pack_type','source.pack_day','source.modifytime','source.is_check', 'soldnumber','section.name');
        $source->LeftJoin('source_extra', 'source_extra.id', 'source.id');
        $source->LeftJoin('section','section.id','source.section_id');
        if ( $offset != 0 )
            $source->offset($offset);
        $source->limit($numperpage);

        if ( !empty( $query['id'] ) )
            $source->where('source.id', $query['id']);

        if ( !empty( $query['title'] ) )
            $source->where('source.title', 'like', '%'.$query['title'].'%');

        if ( !empty( $query['uid'] ) )
            $source->where('source.uid', $query['uid']);

        if ( isset($query['pack_type'])&&( $query['pack_type']!="" ) ){
            $source->where('source.pack_type', $query['pack_type']);
        }

        if ( isset($query['check'])&&( $query['check']!="" ) ){
            $source->where('is_check', $query['check']);
        }

        if ( !empty( $query['soldnumber']) )
            $source->where('source_extra.soldnumber', '>=', $query['soldnumber']);

        if ( !empty( $query['createtime']))
            $source->whereBetween('source.createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        if(isset($sort['price'])){
            if ( !empty( $sort['price'] && $sort['price'] != 0) )
                $source->orderBy('source.price', $sorts[$sort['price']]);
        }

        if(isset($sort['soldnumber'])){
            if ( !empty( $sort['soldnumber'] && $sort['soldnumber'] != 0) )
                $source->orderBy('soldnumber', $sorts[$sort['soldnumber']]);
        }

        if(isset($sort['createtime'])) {
            if (!empty($sort['createtime']) && $sort['createtime'] != 0)
                $source->orderBy('source.createtime', $sorts[$sort['createtime']]);
        }
        $data = $source->get();

        // 总数量
        $source = source::select('source.id', 'source.uid','source.play_start','source.play_end','source.free_watch','source.order_status', 'source.title', 'source.sub_title', 'source.price', 'source.thresh', 'source.status', 'source.createtime', 'source.pack_type','source.pack_day','source.modifytime','source.is_check', 'soldnumber','section.name');
        $source->LeftJoin('source_extra', 'source_extra.id', 'source.id');
        $source->LeftJoin('section','section.id','source.section_id');

        if ( !empty( $query['id'] ) )
            $source->where('source.id', $query['id']);

        if ( !empty( $query['title'] ) )
            $source->where('source.title', 'like', '%'.$query['title'].'%');

        if ( !empty( $query['uid'] ) )
            $source->where('source.uid', $query['uid']);

        if ( isset($query['pack_type'])&&( $query['pack_type']!="" ) ){
            $source->where('source.pack_type', $query['pack_type']);
        }

        if ( isset($query['check'])&&( $query['check']!="" ) ){
            $source->where('is_check', $query['check']);
        }

        if ( !empty( $query['soldnumber']) )
            $source->where('source_extra.soldnumber', '>=', $query['soldnumber']);

        if ( !empty( $query['createtime']))
            $source->whereBetween('source.createtime', [$query['createtime']['from'], $query['createtime']['to']]);

        $count = $source->count();

        $pagenum = ceil($count/$numperpage);


        foreach ($data as $key => $value) {
            $status = decbin($value['status']);
            $oldStatus = sprintf('%08d', $status);
            $data[$key]['status'] = $oldStatus;
        }

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data->ToArray();

        return response()->json($return);
    }


    /**
     * 设置红单黑单
     */
    public function setSourceOrderStatus(Request $request,$sid) {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        $mark = $request->input("mark", "");
        if( $sid == "" || $mark == "" ) {
            return response()->json(['status_code'=>'10002', 'error_message'=>'效验参数，缺少参数']);
        }
        if( $mark == 1 ) {
            return response()->json(['status_code'=>'10003', 'error_message'=>'暂不允许GM设置红单']);
        }
        $source = source::where('sid', $sid)->first();

        if($source['pack_type']!=2){
            return response()->json(['status_code'=>'10006', 'error_message'=>'非不中退款单']);
        }
        if($source['order_status']==2){
            return response()->json(['status_code'=>'10007', 'error_message'=>'已设置黑单']);
        }

        //设置红单黑单
        source::where('sid', $sid)->update([
            'order_status' => $mark
        ]);

//		if($mark==1){
//			//收益增加料所售出
//			$amount = order::where('sid',$sid)->where('selledid',$uid)->where('price','>',0)->where('orderstatus',1)->sum('price');
//
//			DB::table('client_extra')->where('id', $uid)->increment('balance', $amount);   // 销售者余额增加
//			DB::table('client_extra')->where('id', $uid)->increment('total', $amount);  // 收入增加
//		}
        //黑单申请退款
        if($mark==2){
            //退款
            //获取所有订单
            //建立计数器，微信正常退款支持150qps，无效退款6qps

            $clock =1;
            $refundFalseClock =1;
            $offset = 0;
            $query = order::select()->where('sid',$sid)->where('orderstatus',1);
            $result = $query->offset($offset)->limit(1)->first();
            while ($result) {
                //发起退款请求

                if($source['order_status']==1){
                    //检查收入
                    DB::table('client_extra')->where('id', $source['uid'])->decrement('balance', $result['price']);   // 销售者余额增加
                    DB::table('client_extra')->where('id', $source['uid'])->decrement('total',  $result['price']);  // 收入增加

                    //记录金额变更
                    client_money_change::setChange($source['uid'], $result['price'], 2, 3);
                }

                if($mark==2&&$result['price']>0){
                    //写redis
                    $redisKey = "refund_list_admin";
                    Redis::lpush($redisKey, $sid);
                } else {
                    $this->sendMsg($result, 1);
                }


                $offset++;
                $query = order::select()->where('sid',$sid)->where('orderstatus',1);
                $result = $query->offset($offset)->limit(1)->first();
                $clock++;
                if($clock % 100==0){
                    sleep(1);
                }
                if($refundFalseClock % 5==0){
                    sleep(1);
                }
            }

            /*
            $clock =1;
            $refundFalseClock =1;
            $offset = 0;
            $query = order::select()->where('sid',$sid)->where('orderstatus',1);
            $result = $query->offset($offset)->limit(1)->first();
//			var_dump($result);
            while ($result) {

                if($source['order_status']==1){
                    //检查收入
                    DB::table('client_extra')->where('id', $source['uid'])->decrement('balance', $result['price']);   // 销售者余额增加
                    DB::table('client_extra')->where('id', $source['uid'])->decrement('total',  $result['price']);  // 收入增加

                    //记录金额变更
                    client_money_change::setChange($source['uid'], $result['price'], 2, 3);
                }

                if($mark==2&&$result['price']>0){
                    //发起退款请求
                    $refund = $this->refund($result);
                    if(!$refund){
                        $refundFalseClock++;
                    }
                }

                $this->sendMsg($result,$mark);

                $offset++;
                $query = order::select()->where('sid',$sid)->where('orderstatus',1);
                $result = $query->offset($offset)->limit(1)->first();


                $clock++;
                if($clock % 100==0){
                    sleep(1);
                }
                if($refundFalseClock % 5==0){
                    sleep(1);
                }
            }
            */
        }

        return response()->json([
            'status_code' => 200,
            'data' => array()
        ]);
    }


    public function getOpenId($uid) {
        $client = client::select('openid', 'serviceid')->where('id', $uid)->first();
        return $client;
    }

    public function msg_access_token($type = 1) {
        if ($type == 1) {
            $key = 'xcx_access_token';
        } else {
            $key = 'gzh_access_token_subscribe';

        }
        $re = Redis::exists($key);
        if ($re) {
//		if (false) {

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

    public function sendMsg($order,$mark){
        //换取openid
        $userInfo = $this->getOpenId($order['buyerid']);

        if (isset($userInfo['serviceid']) && $userInfo['serviceid'] != null) {
            $type = 2;
        } else {
            $type = 1;
        }

        //支付走的公众号参数，使用小程序推送会报form id错误，判断原因是 form id 与小程序参数不拼配，故小程序直接返回
//		if($type==1){
//			return true;
//		}
        $token = $this->msg_access_token($type);

        $time = date("Y-m-d H:i:s", time());
        if ($type == 1) {
            //小程序
            $noticeTemplateId = "TSzfcY9CuEd-jYzBoPpfji4Bzmxu_W8IUuUgok9F4SE";
            $openid = $userInfo['openid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token=$token";


            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
            //支付信息

            $params['form_id'] = $order['prepay_id'];
            $msg = array();
            $msg['keyword1'] = [
                'value' => "您买的料已经可以查看",
                //				'value' => '11',
            ];//信息详情


            if($mark == 2){
                $msg['keyword1'] = [
                    'value' => "您购买的料已更改为黑，钱款将退还至微信零钱",
                ];//信息详情

            }else{
                $msg['keyword1'] = [
                    'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
                ];//信息详情
            }

            $msg['keyword2'] = [
                'value' => $time,
            ];//发布时间
            $params['data'] = $msg;

        } else {
            //公众号
            $noticeTemplateId = "23rQY_hmlkQe5TMULTjGkB-cSTX-1fGkNzbvDWLjKR8";
            $openid = $userInfo['serviceid'];
            $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

            $params['touser'] = $openid;
            $params['template_id'] = $noticeTemplateId;
//			$params['appid'] = "wx1ad97741a12767f9";
            //支付信息
            $msg = array();
            if($mark == 2){
                $msg['keyword1'] = [
                    'value' => "您购买的料已确认为黑，钱款将原路退回至您的支付账户。",
                ];//信息详情
                $msg['keyword2'] = [
                    'value' => "黑",
                ];//发布时间

            }else{
                $msg['keyword1'] = [
                    'value' => "您购买的料卖家已确认为红，如有疑问请联系客服",
                ];//信息详情
                $msg['keyword2'] = [
                    'value' => "红",
                ];//发布时间

            }

            $msg['keyword3'] = [
                'value' => $time,
            ];//发布时间
            $params['data'] = $msg;
        }

        if ($openid != null) {
            $result = $this->postCurl($api, $params, 'json');

            return ($result);
        } else {
            return null;
        }

    }


    private function refund($data){
        $input = new \WxPayRefund();
        $input->SetOut_trade_no($data['ordernum']);
        $input->SetTotal_fee($data['price']*100);
        $input->SetRefund_fee($data['price']*100);
        //退款单号
        $refund = array();
        $refund['sid'] = $data['sid'];
        $refund['order'] = $data['ordernum'];
        $refund['buyerid'] = $data['buyerid'];
        $refundOrder = $this->refundOrder();
        $refund['refund'] = $refundOrder;
        $refund['price'] = $data['price'];
        $refund['oper'] = 1;
        $refund['time'] = time();
        $refund['status'] = 0;

        refund_order::create($refund);

        $input->SetOut_refund_no($refundOrder);
        $input->SetOp_user_id("1487651632");
        $input->SetNonce_str("1487651632");

        $config['appid'] = config('wxxcx.wechat_appid');
        $config['secret'] = config('wxxcx.wechat_appsecret');
        $config['mchid'] = config('pay.wxpay.mchid');
        $config['mch_secret_key'] = config('pay.wxpay.mch_secret_key');
//        $config['sslcert_path'] = config('pay.wxpay.sslcert_path');
//        $config['sslkey_path'] = config('pay.wxpay.sslkey_path');

        $result = \WxPayApi::refund($input,$config);
        if($result['result_code']=="SUCCESS"){

//			refund_order::create($refund);
            return true;
        }else{
            return false;
        }
    }
    private function refundOrder(){
        return md5(time().rand(0,10000));
    }
    /**
     * 获取料的详情
     */
    public function getSourceDeatail(Request $request, $sid)
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

        //$roles = ['root', 'admin', 'audit1', 'audit2'];
        $roles = ['root','admin','audit1'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $is_list = $request->input('is_list','');
        $page = $request->input('page', '0');
        $numberpage = $request->input('numberpage', '50');
        $query = $request->input('query', '');
        $sort = $request->input('sort', '');
        $query = json_decode($query, True);
        $sort = json_decode($sort, True);
        $offset = $page * $numberpage;

        $source = source::where('id', $sid)->first();
        $status = decbin($source['status']);
        $source['status'] = sprintf('%08d', $status);

        $contentsData = [];
        $contentsQuery = contents::where('sid',$sid)->orderBy('createtime','desc');
        $count =  $contentsQuery->count();
        if($is_list&&!empty($is_list)){
            //list
            if ( $offset != 0 )
                $contentsQuery->offset($offset);
            $contentsQuery->limit($numberpage);
            $contents = $contentsQuery->get()->ToArray();
            foreach ($contents as $ind=>$content){
                $resources = resource::where('cid',$content['cid'])->where('status', '0')->where('stype','<>',1)->get()->ToArray();
                $contents[$ind]['resource'] = $resources;
            }
            $contentsData = $contents;
        }else{
            //single
            if($count && $count>0) {
                $lastcontent = $contentsQuery->first();
                $resources = resource::where('cid', $lastcontent['cid'])->where('status', '0')->where('stype','<>',1)->get()->ToArray();
                if($resources && !empty($resources)){
                    $lastcontent['resource'] = $resources;
                }
                $contentsData[] = $lastcontent;
            }else if($source['pack_type'] == 0){
                $old_resource = resource::where('sid',$sid)->where('status', '0')->where('stype','<>',1)->get()->ToArray();
                $contentsData[] = ['cid'=>'-1', 'description'=>$source['description'], 'createtime'=>$source['createtime'],'resource'=>$old_resource];
            }
        }
        if($is_list&&!empty($is_list)){
            $source['pagenum'] = ceil($count/$numberpage);
            $source['count'] = $count;
        }
        $data['source'] = $source;
        $data['contents'] = $contentsData;
        $return['status_code'] = '200';
        $return['data'] = $data;
        return response()->json($return);
    }


    /**
     * 禁止一个料
     */
    public function putSourceDisable(Request $request, $sid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        $able = $request->input('able', 1);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        //$roles = ['root', 'admin', 'audit1'];
        $roles = ['root','admin','audit1'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        // 修改信息
        $source = source::where('id', $sid)->first();
//        $status = $this->statusChanger($source['status'], '8');
        $status = decbin($source['status']);
        $oldStatus = sprintf('%08d', $status);
        $newStatus = substr_replace($oldStatus, $able, -4, 1);
        $newStatusChange = bindec((int)$newStatus);
        source::where('id', $sid)->update(['status' => $newStatusChange]);

        $return['status_code'] = '200';
        $return['able'] = $able;
        return response()->json($return);
    }

    /**
     * 审核
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function putSourceCheck(Request $request, $sid)
    {
        $token = JWTAuth::getToken();
        $clients = $this->getUserInfo($token);
        $check = $request->input('check', 1);

        if (!empty($clients['status_code'])) {
            if ($clients['status_code'] == '401') {
                $error['status_code'] = '10001';
                $error['error_message'] = '用户token验证失败， 请刷新重试';
                return response()->json($error);
            }
        }

        // 验证权限
        //$roles = ['root', 'admin', 'audit1'];
        $roles = ['root','admin','audit1'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $source_info = source::where('id', $sid)->first();

        // 修改信息
        source::where('id', $sid)->update(['is_check' => $check]);

        $userInfo = $this->getOpenId($source_info['uid']);

        $token = $this->msg_access_token(2);

        $noticeTemplateId = "5SutqB_KWCsbfbNkT-wq7Xt-Q70LL33jESXmUnIehlc";
        //$noticeTemplateId = "P3esE_PHNPQvYi7j6-Y39nwpib1Hy4y3OVC2lFcfNaw";
        $openid = $userInfo['serviceid'];
        $api = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$token";

        $params['touser'] = $openid;
        $params['template_id'] = $noticeTemplateId;
        //支付信息
        $msg = array();

        //卖出的料，发送更新提醒给买家（包时段，给买家发送包时段更新，其他料类型发送修改通知），给卖家发送审核通过

        $msg['first']  = [
            'value' => "您好，您的料已审核完毕",
        ];
        if($check == 1){

            $this->sendNoticeMsg($sid);
            $params['url'] = "https://glm9.qiudashi.com/home/sources";
            $msg['keyword1'] = [
                'value' => $source_info['title'],
            ];//信息详情
            $msg['keyword2'] = [
                'value' => "审核通过",
            ];//发布时间

        }else{
            $params['url'] = "https://glm9.qiudashi.com/home/sources";
            $msg['keyword1'] = [
                'value' => $source_info['title'],
            ];//信息详情
            $msg['keyword2'] = [
                'value' => "审核未通过",
            ];//发布时间
        }

        $params['data'] = $msg;
        $result = $this->postCurl($api, $params, 'json');

        $return['status_code'] = '200';
        $return['check'] = $check;
        return response()->json($return);
    }

    /**
     * 后台运营将料加入推荐
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function recommendSource(Request $request,$sid){
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
        $roles = ['root','admin','audit1'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $section_id = $request->input('section_id','');
        try{
            $source = DB::table('source')->where('id', $sid)->first();
            if($source){
                //如果是加入精选栏目，则将此料添加到精选栏目对应的队列中
                $key = config('constants.SECTION_KEY').$section_id;
                if($source->price == 0){
                    $score = '11000000'.strtotime($source->createtime);
                }else{
                    $score = '01000000'.strtotime($source->createtime);
                }
                $res = Redis::zAdd($key,$score,$sid);
                if(!$res){
                    $return['status_code'] = '10004';
                    $return['error_message'] = '更新不成功';
                }
                $return['status_code'] = '200';
            }else{
                $return['status_code'] = '10005';
                $return['error_message'] = '要操作的料不存在';
            }
            return response()->json($return);
        }catch(\Exception $e){
            \Log::INFO($e->getCode());
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10010';
            $return['error_message'] = '写入数据失败';
            return response()->json($return);
        }
    }

    /**
     * 后台运营人员更改料的所属栏目
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function putSourceSection(Request $request,$sid){
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
        $roles = ['root','admin','audit1'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $section_id = $request->input('section_id','');
        $source = DB::table('source')->where('id', $sid)->first();
        if(!$source){
            $return['status_code'] = '10003';
            $return['error_message'] = '要操作的料不存在';
            return response()->json($return);
        }
        try{
            //判断此料是否属于推荐料，如果属于推荐料，则更改对应的队列,否则只修改数据库中对应的栏目关联
            $key = config('constants.SECTION_KEY').$source->section_id;
            $rank = Redis::zRank($key,$sid);
            if(isset($rank)){
                $score = $this->score2str(Redis::zScore($key,$sid));
                Redis::zRem($key,$sid);
                Redis::zAdd(config('constants.SECTION_KEY').$section_id,$score,$sid);
            }
            DB::table('source')->where('id', $sid)->update(['section_id'=>$section_id]);
            $return['status_code'] = '200';
        }catch(\Exception $e){
            \Log::INFO($e->getCode());
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10010';
            $return['error_message'] = '写入数据失败';
        }
        return response()->json($return);
    }

    /**
     * 运营人员对推荐列表做人工排序
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function putSourceRank(Request $request,$sid){
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
        $roles = ['root','admin','audit1'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $sortSection = $request->input('section_id','');
        $upperSource = $request->input('upperSource','');
        $lowerSource = $request->input('lowerSource','');
        try{
            $key = config('constants.SECTION_KEY').$sortSection;
            $score = $this->score2str(Redis::zScore($key,$sid));        //获取当前料对应的score，将score更改为上界和下界之间的score
            $upperRank = substr($this->score2str(Redis::zScore($key,$upperSource)),2,6);
            $lowerRank = substr($this->score2str(Redis::zScore($key,$lowerSource)),2,6);
            $currentRank = $this->sort($upperRank,$lowerRank);          //排序规则
            $currentScore = substr_replace($currentRank,$score,2,6);
            $res = Redis::zAdd($key,$currentScore,$sid);
            if($res){
                $return['status_code'] = '200';
            }else{
                $return['status_code'] = '10010';
                $return['error_message'] = '写入数据失败';
            }
            return response()->json($return);
        }catch(\Exception $e){
            \Log::INFO($e->getCode());
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10010';
            $return['error_message'] = '写入数据失败';
            return response()->json($return);
        }
    }

    /**
     * 下线机制（发布时间超过规定时间将此料从推荐列删除）
     * @param Request $request
     * @param $sid
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteRecommend(Request $request,$sid){
        $source = DB::table('source')->where('id',$sid)->first();
        $key = config('constants.SECTION_KEY').$source->section_id;
        $score = $this->score2str(Redis::zScore($key,$sid));
        try{
            //下线是将score的值的第二位（推荐位）的值更为0
            $currentScore = substr_replace($score,'0',1,1);
            $res = Redis::zAdd($key,$currentScore,$sid);
            if($res){
                $return['status_code'] = '200';
            }else{
                $return['status_code'] = '10010';
                $return['error_message'] = '写入数据失败';
            }
            return response()->json($return);
        }catch (\Exception $e){
            \Log::INFO($e->getCode());
            \Log::INFO($e->getMessage());
            $return['status_code'] = '10010';
            $return['error_message'] = '操作数据失败';
            return response()->json($return);
        }
    }
}
