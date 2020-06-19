<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class sms_log extends Model
{
    // 短信日志表
    public $timestamps = false;
    protected $table = "sms_log";
    protected $fillable = [
        'id', 'uid', 'telephone', 'description', 'createtime',
    ];
    protected $casts = [
        'id' => 'string',
    ];
}
