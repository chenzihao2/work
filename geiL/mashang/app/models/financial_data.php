<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class financial_data extends Model
{
    // 用户组
    public $timestamps = false;
    protected $table = "financial_data";
    protected $fillable = [
        'id', 'date', 'account_flow', 'original_service_fee', 'discount_fee', 'tencent_fee', 'profit', 'create_time'
    ];
    protected $casts = [
        'id' => 'int'
    ];
}
