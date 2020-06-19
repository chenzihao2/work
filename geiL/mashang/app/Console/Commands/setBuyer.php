<?php
/**
 * 买家列表设置/更新
 * User: YangChao
 * Date: 2019/1/2
 */

namespace App\Console\Commands;

use App\models\buyer;
use App\models\order;
use App\models\source;
use Illuminate\Console\Command;

class setBuyer extends Command {

    protected $signature = 'setBuyer';

    protected $description = 'setBuyer';

    public function __construct() {
        parent::__construct();
    }

    public function handle(){
        $time = date("Y-m-d H:i:s", time() - 600);
        $end_time = date("Y-m-d H:i:s", time());
        $orderList = order::select('buyerid', 'selledid', 'price', 'createtime')->where('createtime', '>=', $time)->where('createtime', '<', $end_time)->where('orderstatus', 1)->orderBy('createtime', 'asc')->get();

        foreach($orderList as $ke => $va){
            $sourceInfo = source::where('id', $va['sourceid'])->first();
            if($sourceInfo['pack_type'] == 2){
                if($sourceInfo['order_status'] != 1){
                    $va['price'] = 0;
                }
            }

            $buyerInfo = [];
            $buyerInfo = buyer::select('id', 'selledid', 'buyerid', 'payed', 'buy_num', 'last_buy_time', 'status', 'create_time')->where('buyerid', $va['buyerid'])->where('selledid', $va['selledid'])->first();
            $buyerData = [];
            if(!empty($buyerInfo)){
                $buyerData['payed'] = $buyerInfo['payed'] + $va['price'];
                $buyerData['buy_num'] = $buyerInfo['buy_num'] + 1;
                $buyerData['last_buy_time'] = strtotime($va['createtime']);
                buyer::where('id', $buyerInfo['id'])->update($buyerData);
            } else {
                $buyerData['selledid'] = $va['selledid'];
                $buyerData['buyerid'] = $va['buyerid'];
                $buyerData['payed'] = $va['price'];
                $buyerData['buy_num'] = 1;
                $buyerData['last_buy_time'] = strtotime($va['createtime']);
                $buyerData['status'] = 1;
                $buyerData['create_time'] = time();
                buyer::insert($buyerData);
            }
            sleep(1);
        }
    }

}