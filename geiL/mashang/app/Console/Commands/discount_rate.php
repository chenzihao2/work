<?php
/**
 * 每日计算用户优惠费率
 * User: YangChao
 * Date: 2018/8/3
 */

namespace App\Console\Commands;


use App\models\client;
use App\models\client_group;
use App\models\discount;
use App\models\order;
use App\models\rate;
use Illuminate\Console\Command;

class discount_rate extends Command {

    protected $signature = 'discount_rate';

    protected $description = 'discount_rate';

    public function __construct() {
        parent::__construct();

    }

    public function handle() {

        $nowTime = time();

        //计算前五日红黑单处理
        for($i = 1; $i <=6; $i++){
            $sRedTime = date('Y-m-d',strtotime('-' . $i .' day',$nowTime));
            $eRedTime = date('Y-m-d',strtotime('-' . $i+1 .' day',$nowTime));

            //取出前N天的红黑单流水
            $redBlack = discount::where('status', 1)->whereBetween('date', [$sRedTime, $eRedTime])->get()->toArray();
            if(!empty($redBlack)) {
                foreach ($redBlack AS $krb => $vrb) {
                    $discountId = $vrb['id'];
                    $selledid = $vrb['uid'];

                    $sDate = $vrb['date'];
                    $eDate = date('Y-m-d', strtotime('+1 day', strtotime($vrb['date'])));

                    $rateInfo = rate::where('create_time', '<=', strtotime($eDate))->first();
                    $rate = json_decode($rateInfo['rate'], TRUE);

                    //            $rate_json = '[{"money":200,"rate":4},{"money":400,"rate":3},{"money":600,"rate":2}]';
                    //            $rate = json_decode($rate_json, TRUE);

                    $selledGroupPrice = 0;
                    //判断用户是不是在用户组内
                    $selledGroup = client_group::where('uid', $selledid)->first();
                    if (!empty($selledGroup)) {
                        //获取群组绑定用户
                        $groupClientList = client_group::where('gid', $selledGroup['gid'])->get()->toArray();
                        $selledIdGroup = array_column($groupClientList, 'uid');

                        //获取用户组该日订单总金额
                        $selled = order::select('order.id', 'order.sid', 'order.selledid', 'order.orderstatus', 'order.price', 'order.createtime', 'order.pack_type', 'source.pack_type', 'source.order_status', 'source.status');
                        $selled->LeftJoin('source', 'order.sid', 'source.sid');
                        $selled->whereIn('order.selledid', $selledIdGroup)->whereRaw("substring(bin(orderstatus), -1, 1) = 1")->whereBetween('order.createtime', [$sDate, $eDate]);
                        $selledGroupList = $selled->get()->toArray();

                        $priceGroupTotal = 0;
                        $abnormalSourceGroupPrice = 0;
                        foreach ($selledGroupList AS $ksg=>$vsg){
                            if ($vsg['pack_type'] == 2) {
                                if($vsg['order_status'] == 1){
                                    //红单计入流水
                                    $priceGroupTotal += $vsg['price']; //计算总流水
                                }
                            } else {
                                $priceGroupTotal += $vsg['price']; //计算总流水
                            }

                            if($vsg['status'] == 8 || $vsg['status'] == 10){
                                //异常料金额
                                $abnormalSourceGroupPrice += $vsg['price'];
                            }
                        }

                        //实际流水   该日全部流水-异常流水
                        $selledGroupPrice = $priceGroupTotal-$abnormalSourceGroupPrice;


                    }

                    //获取用户该日订单列表
                    $selled = order::select('order.id', 'order.sid', 'order.selledid', 'order.orderstatus', 'order.price', 'order.createtime', 'source.status', 'source.pack_type', 'source.order_status');
                    $selled->LeftJoin('source', 'order.sid', 'source.sid');
                    $selled->where('order.selledid', $selledid)->whereRaw("substring(bin(orderstatus), -1, 1) = 1")->whereBetween('order.createtime', [$sDate, $eDate]);
                    $selledidOrderList = $selled->get()->toArray();

                    if (!empty($selledidOrderList)) {
                        $discount = [];
                        $discount['money'] = 0;
                        $discount['original_rate'] = $vrb['original_rate'];
                        $discount_rate = $vrb['original_rate'];
                        $isRedBlack = 0;
                        $priceTotal = 0;
                        $abnormalSourcePrice = 0;
                        foreach ($selledidOrderList AS $kso => $vso) {
                            if ($vso['pack_type'] == 2) {
                                if ($vso['order_status'] == 1) {
                                    //红单计入流水
                                    $priceTotal += $vso['price']; //计算总流水
                                } elseif ($vso['order_status'] == 0) {
                                    //判断是否有未判定红黑单
                                    $isRedBlack = 1;
                                }
                            } else {
                                $priceTotal += $vso['price']; //计算总流水
                            }
                            if ($vso['status'] == 8 || $vso['status'] == 10) {
                                //异常料金额
                                //$abnormalSourcePrice += $vso['price'];
                            }
                        }

                        //实际流水   该日全部流水-异常流水
                        $realPriceTotal = $priceTotal - $abnormalSourcePrice;

                        $discount['money'] = $priceTotal;
                        $discount['status'] = $isRedBlack ? 1 : 0; //1：红黑单

                        foreach ($rate AS $kr => $vr) {
                            //计算用户应用优惠费率
                            if ($selledGroupPrice) {
                                //用户组计算优惠
                                if ($selledGroupPrice >= $vr['money']) {
                                    $discount_rate = $vr['rate'];
                                }
                            } else {
                                if ($priceTotal >= $vr['money']) {
                                    $discount_rate = $vr['rate'];
                                }
                            }
                        }
                        //如果没有达到优惠费率则停止
                        if ($discount_rate >= $vrb['original_rate']) {
                            continue;
                        }

                        $discount['discount_rate'] = $discount_rate;
                        $discount['original_service_fee'] = round($priceTotal * ($discount['original_rate'] / 100), 2);
                        $discount['discount_service_fee'] = round($realPriceTotal * ($discount['discount_rate'] / 100), 2);
                        if ($discount['discount_service_fee'] == 0) {
                            $discount['discount_fee'] = 0;
                        } else {
                            $discount['discount_fee'] = round($discount['original_service_fee'] - $discount['discount_service_fee'], 2);
                        }

                        $discount['create_time'] = $nowTime;
                        //优惠金额重新计算
                        $res_discount = discount::where('id', $discountId)->update($discount);

                    }
                }
            }
        }

        //计算该日用户流水等信息
        $sTime = date('Y-m-d',strtotime('-1 day',$nowTime));
        $eTime = date('Y-m-d',$nowTime);
        // $sTime = "2018-12-13";
        // $eTime = "2018-12-14";
        // $rateInfo = rate::where('create_time', '<=', strtotime(date('Y-m-d',time())))->first();
        $rateInfo = rate::where('create_time', '<=', strtotime($eTime))->first();
        $rate = json_decode($rateInfo['rate'], TRUE);

        //获取今日有订单收入的用户
        $selledid_arr = order::select('selledid')->whereRaw("substring(bin(orderstatus), -1, 1) = 1")->whereBetween('createtime', [$sTime, $eTime])->groupBy('selledid')->get()->toArray();
        

        // $selledid_arr = [['selledid'=>87206]];

        foreach ($selledid_arr AS $key=>$val){
            $selledid = $val['selledid'];
            if (!$selledid) {
                continue;
            }
            //获取用户相关信息和原始费率
            $client = client::select('client.id', 'client.nickname', 'client_rate.rate');
            $client->LeftJoin('client_rate', 'client.id', 'client_rate.uid');
            $client->where('client.id', $selledid);
            $selledInfo = $client->first()->toArray();
            $selledInfo['rate'] = "3.0";

            $selledGroupPrice = 0;
            //判断用户是不是在用户组内
            $selledGroup = client_group::where('uid', $selledid)->first();
            if(!empty($selledGroup)){
                //获取群组绑定用户
                $groupClientList = client_group::where('gid', $selledGroup['gid'])->get()->toArray();
                $selledIdGroup = array_column($groupClientList, 'uid');

                //获取用户组该日订单总金额
                $selled = order::select('order.id', 'order.sid', 'order.selledid', 'order.orderstatus', 'order.price', 'order.createtime', 'order.pack_type', 'source.pack_type', 'source.order_status', 'source.status');
                $selled->LeftJoin('source', 'order.sid', 'source.sid');
                $selled->whereIn('order.selledid', $selledIdGroup)->whereRaw("substring(bin(orderstatus), -1, 1) = 1")->whereBetween('order.createtime', [$sTime, $eTime]);
                $selledGroupList = $selled->get()->toArray();

                $priceGroupTotal = 0;
                $abnormalSourceGroupPrice = 0;
                foreach ($selledGroupList AS $ksg=>$vsg){
                    if ($vsg['pack_type'] == 2) {
                        if($vsg['order_status'] == 1){
                            //红单计入流水
                            $priceGroupTotal += $vsg['price']; //计算总流水
                        }
                    } else {
                        $priceGroupTotal += $vsg['price']; //计算总流水
                    }

                    if($vsg['status'] == 8 || $vsg['status'] == 10){
                        //异常料金额
                        $abnormalSourceGroupPrice += $vsg['price'];
                    }
                }

                //实际流水   该日全部流水-异常流水
                $selledGroupPrice = $priceGroupTotal-$abnormalSourceGroupPrice;

            }

            //获取用户该日订单列表
            $selled = order::select('order.id', 'order.sid', 'order.selledid', 'order.orderstatus', 'order.price', 'order.createtime', 'source.status', 'source.pack_type', 'source.order_status');
            $selled->LeftJoin('source', 'order.sid', 'source.sid');
            $selled->where('order.selledid', $selledid);
            $selled->whereRaw("substring(bin(orderstatus), -1, 1) = 1");
            $selled->whereBetween('order.createtime', [$sTime, $eTime]);
            $selledidOrderList = $selled->get()->toArray();

            if(!empty($selledidOrderList)){
                $discount = [];
                $discount['uid'] = $selledid;
                $discount['nickname'] = $selledInfo['nickname'];
                $discount['date'] = $sTime;
                $discount['money'] = 0;
                $discount['original_rate'] = $selledInfo['rate'];
                $discount_rate = $selledInfo['rate'];
                $isRedBlack = 0;
                $priceTotal = 0;
                $abnormalSourcePrice = 0;
                foreach ($selledidOrderList AS $kso=>$vso){
                    if ($vso['pack_type'] == 2) {
                        if($vso['order_status'] == 1){
                            //红单计入流水
                            $priceTotal += $vso['price']; //计算总流水
                        } elseif($vso['order_status'] == 0) {
                            //判断是否有未判定红黑单
                            $isRedBlack = 1;
                        }
                    } else {
                        $priceTotal += $vso['price']; //计算总流水
                    }

                    if($vso['status'] == 8 || $vso['status'] == 10){
                        //异常料金额
                        //$abnormalSourcePrice += $vso['price'];
                    }
                }

                //实际流水   该日全部流水-异常流水
                $realPriceTotal = $priceTotal-$abnormalSourcePrice;

                $discount['money'] = $priceTotal;
                $discount['status'] = $isRedBlack ? 1 : 0 ; //1：红黑单

                foreach ($rate AS $kr=>$vr){
                    //计算用户应用优惠费率
                    if($selledGroupPrice){
                        //用户组计算优惠
                        if($selledGroupPrice >= $vr['money']){
                            $discount_rate = $vr['rate'];
                        }
                    } else {
                        if($priceTotal >= $vr['money']){
                            $discount_rate = $vr['rate'];
                        }
                    }
                }
                //如果没有达到优惠费率则停止
                if($discount_rate >= $selledInfo['rate']){
                    continue;
                }

                $discount['discount_rate'] = $discount_rate;
                $discount['original_service_fee'] = round($priceTotal * ($discount['original_rate']/100), 2);
                $discount['discount_service_fee'] = round($realPriceTotal * ($discount['discount_rate']/100), 2);
                $discount['discount_fee'] = round($discount['original_service_fee'] - $discount['discount_service_fee'], 2);
                $discount['create_time'] = $nowTime;
                //优惠金额等数据入库
                $res_discount = discount::create($discount);
            }
            sleep(1);
        }
    }
}
