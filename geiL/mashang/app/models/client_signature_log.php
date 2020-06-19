<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_signature_log extends Model
{
    // 用户个性签名修改日志表
    public $timestamps = false;
    protected $table = "client_signature_log";

    protected $fillable = [
        'id', 'uid', 'nickname', 'signature', 'status', 'create_time'
    ];
}
