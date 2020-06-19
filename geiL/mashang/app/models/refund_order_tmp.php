<?php

namespace App\models;


use Illuminate\Database\Eloquent\Model;

class refund_order_tmp extends Model {
	public $timestamps = false;
	protected $table = "refund_order_tmp";

    protected $casts = [
        'sid' => 'int',
        'order' => 'string',
        'refund' => 'string',
    ];

    protected $fillable = [
        'id', 'sid', 'buyerid', 'selledid', 'order', 'refund', 'price', 'payment', 'refund_time', 'create_time', 'oper', 'status', 'is_manual', 'reason', 'is_batch_order', 'batch_ordernum', 'mch_account', 'assumed_host'
    ];

}
