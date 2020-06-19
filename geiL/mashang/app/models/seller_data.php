<?php

namespace App\models;


use Illuminate\Database\Eloquent\Model;

class seller_data extends Model {
	public $timestamps = false;
	protected $table = "seller_data";

    protected $casts = [
        'sid' => 'int',
        'order' => 'string',
        'refund' => 'string',
    ];

    protected $fillable = [
        'id', 'selledid', 'date', 'order_total', 'order_price_total', 'refund_total', 'refund_price_total', 'create_time'
    ];

}