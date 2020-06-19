<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_log extends Model
{
    // 用户日志表
    public $timestamps = false;
    protected $table = "client_log";

    protected $fillable = [
        'id', 'uid', 'description', 'createtime'
    ];

    protected $casts = [
        'id' => 'string',
    ];
}
