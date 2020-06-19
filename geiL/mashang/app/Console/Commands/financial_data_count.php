<?php
/**
 * 每日计算财务数据
 * User: YangChao
 * Date: 2018/8/3
 */

namespace App\Console\Commands;


use App\models\client_rate;
use App\models\discount;
use App\models\order;
use App\models\refund_order;
use App\models\financial_data;
use Illuminate\Console\Command;

class financial_data_count extends Command {

    protected $signature = 'financial_data_count';

    protected $description = 'financial_data_count';

    public function __construct() {
        parent::__construct();

    }

    public function handle() {

        $nowTime = time();

        //计算前五日流水统计
        for($i = 1; $i <=6; $i++){
            $sRedTime = date('Y-m-d',strtotime('-' . $i .' day', $nowTime));
            $eRedTime = date('Y-m-d',strtotime('-' . $i+1 .' day', $nowTime));
            $data = $this->getFinancial($sRedTime, $eRedTime);
            financial_data::where('date', $sRedTime)->update($data);
        }

        $data = [];
        //计算该日用户流水等信息
        $sTime = date('Y-m-d',strtotime('-1 day', $nowTime));
        $eTime = date('Y-m-d', $nowTime);
        $data = $this->getFinancial($sTime, $eTime);
        financial_data::create($data);
    }


    private function getFinancial($sTime, $eTime){

        $nowTime = time();

        //获取该日订单的总服务费
        $selled = order::select('order.id', 'order.sid', 'order.selledid', 'order.orderstatus', 'order.price', 'order.modifytime', 'order.pack_type', 'source.status', 'source.pack_type', 'source.order_status');
        $selled->LeftJoin('source', 'order.sid', 'source.sid');
        $selled->whereRaw("substring(bin(orderstatus), -1, 1) = 1")->whereBetween('order.createtime', [$sTime, $eTime]);
        $selledidOrderList = $selled->get()->toArray();
        $accountFlow = $originalServiceFee = 0;

        foreach ($selledidOrderList AS $kso => $vso) {
            //获取用户相关原始费率
            $selledInfo = client_rate::where('uid', $vso['selledid'])->first();
            $rate = !empty($selledInfo) ? $selledInfo['rate'] : 5;

            $userTodayTotal = 0;
            if ($vso['pack_type'] == 2) {
                if($vso['order_status'] == 1){
                    //红单计入流水
                    $userTodayTotal += $vso['price']; //计算总流水
                }
            } else {
                $userTodayTotal += $vso['price'];
            }
            $originalServiceFee += $userTodayTotal * ($rate / 100);
            $accountFlow += $userTodayTotal;
        }

        //获取今日优惠发放服务费
        $discountFee = discount::select()->where('date', $sTime)->sum('discount_fee');

        //腾讯服务费
        $tencentFee = $accountFlow * 0.01;

        //毛利
        $profit = $originalServiceFee - $discountFee - $tencentFee;

        $data = [];
        $data['date'] = $sTime;
        $data['account_flow']  = $accountFlow;
        $data['original_service_fee'] = round($originalServiceFee, 2);
        $data['discount_fee'] = $discountFee;
        $data['tencent_fee'] = round($tencentFee, 2);
        $data['profit'] = round($profit, 2);
        $data['create_time'] = $nowTime;
        return $data;
    }
}
