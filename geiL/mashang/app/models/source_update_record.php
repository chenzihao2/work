<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class source_update_record extends Model
{
    // 用户日志表
    public $timestamps = false;
    protected $table = "source_update_record";

    protected $fillable = [
        'rid', 'sid', 'uid', 'rkey', 'rvalue', 'createtime'
    ];

    protected $casts = [
        'rid' => 'string',
        'sid' => 'string',
        'uid' => 'string'
    ];
}
