<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class order extends Model
{
    // 订单表
    public $timestamps = false;
    protected $table = "order";
    protected $casts = [
        'id' => 'string',
        'sid' => 'string',
        'buyerid' => 'string',
        'selledid' => 'string',
        'sourceid' => 'string',
        'score' => 'string'
    ];

    protected $fillable = [
        'id', 'sid', 'pack_type', 'score', 'ordernum', 'payment', 'prepay_id', 'buyerid', 'selledid', 'sourceid', 'orderstatus', 'price', 'createtime', 'modifytime', 'start_time', 'end_time', 'score', 'lastcid', 'pack_type', 'is_batch', 'batch_order_num', 'mch_account'
    ];
}
