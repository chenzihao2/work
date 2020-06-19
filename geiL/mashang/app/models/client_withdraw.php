<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_withdraw extends Model
{
    // 提现表

    public $timestamps = false;
    protected $table = "client_withdraw";
    protected $casts = [
        'id' => 'string',
    ];

    protected $fillable = [
        'id', 'uid', 'service_fee', 'balance', 'status', 'createtime', 'audittime', 'completetime', 'channel',
    ];
}
