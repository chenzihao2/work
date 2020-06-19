<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class statics extends Model
{
    // 统计表
    public $timestamps = false;
    protected $table = "statics_day";

    protected $fillable = [
        'statictime', 'order', 'resource', 'source', 'user', 'service_fee', 'withdrawed', 'withdrawing', 'total', 'active_sell', 'active_buy', 'active',
    ];
}
