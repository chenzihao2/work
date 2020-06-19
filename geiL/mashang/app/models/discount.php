<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class discount extends Model
{
    // 用户组
    public $timestamps = false;
    protected $table = "discount";
    protected $fillable = [
        'id', 'uid', 'nickname', 'date', 'money', 'original_rate', 'discount_rate', 'original_service_fee', 'discount_service_fee', 'discount_fee', 'is_manual', 'status', 'create_time', 'send_time', 'channel'
    ];
    protected $casts = [
        'id' => 'int',
        'uid' => 'string'
    ];
}
