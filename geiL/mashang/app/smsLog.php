<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class smsLog extends Model
{
    // 短信记录表
    protected $table = "sms_log";

    protected $fillable = [
        'uid', 'mobile', 'content',
    ];
}
