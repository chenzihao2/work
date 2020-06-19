<?php
/**
 * 统计卖家交易数据
 * User: YangChao
 * Date: 2019/1/7
 */
namespace App\Console\Commands;

use App\models\client;
use App\models\order;
use App\models\refund_order_tmp;
use App\models\seller_data;
use Illuminate\Console\Command;

class statSellerData extends Command {

    protected $signature = 'statSellerData';

    protected $description = 'statSellerData';

    public function __construct() {
        parent::__construct();
    }

    public function handle() {
        $white_seller = client::select('id', 'openid', 'serviceid')->where('is_white', 1)->get();
        $start_date = date('Y-m-d 00:00:00', strtotime('-1 day'));
        $end_date = date('Y-m-d 00:00:00', time());
        foreach($white_seller as $key => $val){
            $selledid = $val['id'];
            $order_list = order::select()->where('selledid', $selledid)->whereRaw("substring(bin(orderstatus), -1, 1) = 1")->where('createtime', '>=', $start_date)->where('createtime', '<', $end_date)->get();
            $sellerData = [];
            $sellerData['selledid'] = $val['id'];
            $sellerData['date'] = $start_date;
            $sellerData['order_total'] = 0;
            $sellerData['order_price_total'] = 0;
            $sellerData['refund_total'] = 0;
            $sellerData['refund_price_total'] = 0;
            $sellerData['create_time'] = time();
            $order_list = $order_list->toArray();
            if(!empty($order_list)){
                foreach($order_list as $k => $v){
                    if($v['price'] > 0){
                        $sellerData['order_total']++;
                        $sellerData['order_price_total'] += $v['price'];
                    }
                }
            }

            $refund_order_list = refund_order_tmp::select()->where('selledid', $selledid)->where('create_time', '>=', strtotime($start_date))->where('create_time', '<', strtotime($end_date))->get();
            $refund_order_list = $refund_order_list->toArray();
            if(!empty($refund_order_list)){
                foreach($refund_order_list as $k => $v){
                    if($v['price'] > 0){
                        $sellerData['refund_total']++;
                        $sellerData['refund_price_total'] += $v['price'];
                    }
                }
            }

            seller_data::create($sellerData);

            sleep(1);
        }
    }

}
