<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class hl_order_refund extends Model
{
    protected $connection = 'mysql_origin';
    protected $table = 'hl_order_refund';
    protected $pagesize = 20;
    public $timestamps = false;

   //统计退款总金额
    public function refundAmount($expert_id){
        $where[]=['expert_id','=',$expert_id];
        $where[]=['refund_status','>',0];
        $where[]=['refund_type','=',2];
       return  self::where($where)->sum('refund_amount');
    }
}
