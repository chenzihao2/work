<?php

namespace App\Http\Controllers\Admin;

use App\models\client_extra;
use App\models\order;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;

use App\models\client;
use App\models\client_money_change;
use App\models\refund_order;
use App\models\source;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderController extends BaseController
{
    // 订单

    /**
     * 订单列表
     */
    public function getOrdersList(Request $request)
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


        $order = order::select('order.id', 'order.buyerid','order.payment', 'order.selledid', 'sourceid', 'orderstatus','sell.openid as openid','source.order_status as source_status','sell.serviceid as serviceid', 'order.price', 'order.createtime', 'order.modifytime', 'buy.nickname as buy_name', 'sell.nickname as sell_name', 'source.title','source.pack_type','source.pack_day','source.play_start', 'source.play_end','source.free_watch',  'ordernum','refund_order.time as refund_time','refund_order.status as refund_status');
        $order->LeftJoin('client as buy', 'buy.id', 'order.buyerid');
        $order->LeftJoin('client as sell', 'sell.id', 'order.selledid');
        $order->LeftJoin('source', 'source.id', 'order.sourceid');
        $order->LeftJoin('refund_order', 'refund_order.order', 'order.ordernum');
        if ( $offset != 0 )
            $order->offset($offset);
            $order->limit($numperpage);
        if ( !empty( $query['title'] ) )
            $order->where('source.title', 'like', '%'.$query['title'].'%' );
        if ( !empty( $query['buy_name'] ) )
            $order->where('buy.nickname', 'like', '%'.$query['buy_name'].'%' );
        if ( !empty( $query['buy_id'] ) )
            $order->where('order.buyerid', $query['buy_id']);
        if ( !empty( $query['sell_id'] ) )
            $order->where('order.selledid', $query['sell_id']);
        if ( !empty( $query['sell_name'] ) )
            $order->where('sell.nickname', 'like', '%'.$query['sell_name'].'%' );
        if ( isset( $query['orderstatus']) )
            if ( $query['orderstatus'] != '')
//                $order->where('orderstatus', $query['orderstatus']);
                $order->whereRaw("substring(bin(orderstatus), -1, 1) = {$query['orderstatus']}");
        if ( !empty( $query['createtime']['from']) && !empty($query['createtime']['to']))
            $order->whereBetween('order.createtime', [$query['createtime']['from'], $query['createtime']['to']]);
        if ( isset( $sort['createtime'] ) )
            $order->orderBy('order.createtime', $sorts[$sort['createtime']]);

//        $sql = $order->toSql();
//        dd($sql);
//        var_dump($sql);die;
        $data = $order->get();
        $data = $data->ToArray();
        foreach ( $data as $key => $value ) {
            $status = decbin($value['orderstatus']);
            $newStatus = sprintf('%08d', $status);
            $data[$key]['orderstatus'] = substr($newStatus,-1,1);
			if(!empty($value['refund_time'])){
				$data[$key]['refund_time'] =date("Y-m-d H:i:s",$value['refund_time']);
			}else{
				$data[$key]['refund_time'] = "";
			}
			if($value['refund_status']==2){
				$data[$key]['refund_status'] = 2;
			}else{
				$data[$key]['refund_status'] = 0;
			}


			if($value['pack_type']==2&&$value['source_status']==0){
				$data[$key]['source_status'] = "未判定";
			}elseif($value['pack_type']==2&&$value['source_status']==1){
				$data[$key]['source_status'] = "红单";
			}elseif($value['pack_type']==2&&$value['source_status']==2){
				$data[$key]['source_status'] = "黑单";
			}else{
				$data[$key]['source_status'] = "普通料";
			}

			if($value['pack_type']==0){
				$data[$key]['pack_type'] = "普通单";
			}elseif($value['pack_type']==1){
				$data[$key]['pack_type'] = "包时段";
			}elseif($value['pack_type']==2){
				$data[$key]['pack_type'] = "不中退款";
			}else{
				$data[$key]['pack_type'] = "限时料";
			}

			if($value['payment']==1){
				$data[$key]['payment'] = "微信支付";
			}elseif($value['payment']==2){
				$data[$key]['payment'] = "华移支付";
			}elseif($value['payment']==3){
				$data[$key]['payment'] = "支付宝";
			}elseif($value['payment']==4){
				$data[$key]['payment'] = "钱方支付";
			}else{
				$data[$key]['payment'] = "免费料";
			}
        }
        $data = array_values($data);

        $order = order::select('order.id', 'buyerid', 'selledid', 'sourceid', 'orderstatus', 'order.price', 'source.order_status', 'order.createtime', 'order.modifytime', 'buy.nickname as buy_name', 'sell.nickname as sell_name', 'source.title');
        $order->LeftJoin('client as buy', 'buy.id', 'order.buyerid');
        $order->LeftJoin('client as sell', 'sell.id', 'order.selledid');
        $order->LeftJoin('source', 'source.id', 'order.sourceid');
        if ( !empty( $query['title'] ) )
            $order->where('source.title', 'like', '%'.$query['title'].'%' );
        if ( !empty( $query['buy_name'] ) )
            $order->where('buy.nickname', 'like', '%'.$query['buy_name'].'%' );
        if ( !empty( $query['buy_id'] ) )
            $order->where('buyerid', $query['buy_id']);
        if ( !empty( $query['sell_id'] ) )
            $order->where('selledid', $query['sell_id']);
        if ( !empty( $query['sell_name'] ) )
            $order->where('sell.nickname', 'like', '%'.$query['sell_name'].'%' );
        if ( isset ( $query['orderstatus']) )
            if ( $query['orderstatus'] != '')
                $order->whereRaw("substring(bin(orderstatus), -1, 1) = {$query['orderstatus']}");
        if ( !empty( $query['createtime']['from']) && !empty($query['createtime']['to']))
            $order->whereBetween('order.createtime', [$query['createtime']['from'], $query['createtime']['to']]);
        $count = $order->count();

        $pagenum = ceil($count/$numperpage);

        $return['status_code'] = '200';
        $return['count'] = $count;
        $return['pagenum'] = $pagenum;
        $return['data'] = $data;

        return response()->json($return);
    }

    public function export(Request $request)
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

        $roles = ['root', 'admin', 'audit2'];
        if ( !in_array($clients['role'], $roles)) {
            $return['status_code'] = '10002';
            $return['error_message'] = '权限不足';
            return response()->json($return);
        }

        $sorts = ['0' => 'desc', '1' => 'asc', '2' => 'desc'];
        $page = $request->input('page', '0');
        $numperpage = $request->input('numperpage', '50');
        $query = json_decode($request->input('query', ''), True);
        $sort = json_decode($request->input('sort', ''), True);
        $offset = $page * $numperpage;

        $order = order::select('order.id', 'order.buyerid','order.payment','source.order_status as source_status', 'order.selledid', 'order.sid', 'orderstatus', 'order.price','client_rate.rate', 'order.createtime', 'order.modifytime', 'buy.nickname as buy_name', 'sell.nickname as sell_name','sell.openid as openid','sell.serviceid as serviceid','source.title','source.pack_type','source.pack_day', 'ordernum','refund_order.time as refund_time','refund_order.status as refund_status');

        $order->LeftJoin('client as buy', 'buy.id', 'order.buyerid');
        $order->LeftJoin('client as sell', 'sell.id', 'order.selledid');
        $order->LeftJoin('client_rate', 'client_rate.uid', 'order.selledid');
        $order->LeftJoin('source', 'source.id', 'order.sourceid');
		$order->LeftJoin('refund_order', 'refund_order.order', 'order.ordernum');
        if ( !empty( $query['title'] ) )
            $order->where('source.title', 'like', '%'.$query['title'].'%' );
        if ( !empty( $query['buy_name'] ) )
            $order->where('buy.nickname', 'like', '%'.$query['buy_name'].'%' );
        if ( !empty( $query['buy_id'] ) )
            $order->where('order.buyerid', $query['buy_id']);
        if ( !empty( $query['sell_id'] ) )
            $order->where('order.selledid', $query['sell_id']);
        if ( !empty( $query['sell_name'] ) )
            $order->where('sell.nickname', 'like', '%'.$query['sell_name'].'%' );
        if ( isset( $query['orderstatus']) )
            if ( $query['orderstatus'] != '')
                $order->whereRaw("orderstatus & 1 = {$query['orderstatus']}");
        if ( !empty( $query['createtime']['from']) && !empty($query['createtime']['to']))
            $order->whereBetween('order.createtime', [$query['createtime']['from'], $query['createtime']['to']]);
        if ( isset( $sort['createtime'] ) )
            $order->orderBy('order.createtime', $sorts[$sort['createtime']]);
        $dataList = $order->get()->ToArray();
//        var_dump($dataList);die;
        $res[] = ['订单号','资源名称','订单价格','购买者','购买者ID','出售者','出售者小程序id','出售者公众号id','出售者ID','出售者提现利率','创建时间','支付渠道','订单状态','料类型','料状态','退款时间','退款状态'];
        foreach ($dataList as $value){
            $rate = $value['rate'].'%';
            if($value['orderstatus']&1){
                $orderstatus = '已支付';
            }else{
                $orderstatus = '未支付';
            }
            if(!empty($value['refund_time'])){
				$refundTime =date("Y-m-d H:i:s",$value['refund_time']);
			}else{
            	$refundTime = "";
			}
            if($value['refund_status']==2){
                $refundStatus = '成功';
            }else{
				$refundStatus = '失败';
            }

            if($value['source_status']==0){
            	$sourceStatus = "未判定";
			}elseif($value['source_status']==1){
            	$sourceStatus = "红单";
			}else{
            	$sourceStatus = "黑单";
			}

            if($value['pack_type']==0){
            	$packType = "普通单";
			}elseif($value['pack_type']==1){
				$packType = "包时段";
			}elseif($value['pack_type']==2){
				$packType = "不中退款";
			}else{
				$packType = "限时料";
			}
            if($value['payment']==1){
				$payment = "微信支付";
			}elseif($value['payment']==2){
				$payment = "华移支付";
			}elseif($value['payment']==3){
				$payment = "支付宝";
            }elseif($value['payment']==4){
                $payment = "钱方支付";
            }else{
                $payment = "免费料";
            }
            $buyname = $this->userTextEncode($value['buy_name']);
            $sellname = $this->userTextEncode($value['sell_name']);
			
			$value['title'] =  str_replace("=", "", $value['title']);
            $data = [
                strval($value['ordernum']),$value['title'],$value['price'], $buyname, $value['buyerid'],
                $sellname,$value['openid'],$value['serviceid'],$value['selledid'], $rate, $value['createtime'],$payment,$orderstatus,$packType,$sourceStatus,$refundTime,$refundStatus
            ];
            array_push($res,$data);
        }

        Excel::create('订单列表',function($excel) use ($res){
            $excel->sheet('score', function($sheet) use ($res){
                $sheet->rows($res);
            });
        })->export('xls');
    }


    /**
     * 0.01bug修改
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function withdrawingFix(Request $request)
    {
        $uid = $request->input('uid', '0');
        if($uid != 0){
            $extraInfo = client_extra::select('withdrawing')->where('id', $uid)->first();
            if($extraInfo['withdrawing'] == 0.01){
                client_extra::where('id', $uid)->update(['withdrawing' => 0]);
                $return['status_code'] = '200';
                $return['msg'] = 'success';
                $return['data'] = [];
                return response()->json($return);
            }else{
                $error['status_code'] = '10001';
                $error['error_message'] = '非0.01';
                return response()->json($error);
            }
        }else{
            $error['status_code'] = '10001';
            $error['error_message'] = '请输入uid';
            return response()->json($error);
        }
    }

    /**
     * 手动退款接口
     *
     */
    public function manualRefund(Request $request) {
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
      $type = $request->input('type', '0');     //0表示订单退款， 1表示料退款
      $orderId = $request->input('id', '0');
      $sourceId = $request->input('sourceId', '0');
      $reason = $request->input('reason', '');
      if(empty($orderId) && empty($sourceId)) {
        return response()->json(['status_code' => 10003, 'error_message' => '参数错误']);
      }

      //触发当前订单的退款操作
      $orderList = array();
      if($type == 0) {
        $orderList = order::select()->where('id',$orderId)->get();
      }else {
        $orderList = order::select()->where('sid', $sourceId)->where('orderstatus', 1)->get();
      }
      if (!empty($orderList)) {
        foreach($orderList as $result) {
          if($result['price']>0){
            $redisKey = "refund_list_manual";
            $manualRefundInfo = array(
              'orderId' => $result['id'],
              'reason' => $reason
            );
            Redis::lpush($redisKey, json_encode($manualRefundInfo));
          }
        }
      }
      return response()->json([
        'status_code' => 200,
        'data' => array()
      ]);
    }
}
