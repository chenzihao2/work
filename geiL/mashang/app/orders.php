<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class orders extends Model
{
    protected $table = "orders";

    protected $fillable = [
        'orderNum', 'buy_uid', 'sell_id', 'sid', 'sell_balance', 'pay_status', 'source_price', 'status',
    ];
}
