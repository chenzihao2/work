<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class manual_record extends Model
{
    // 用户组
    public $timestamps = false;
    protected $table = "manual_record";
    protected $fillable = [
        'id','relation_id', 'merchant', 'collect_uid', 'out_order_no', 'type', 'create_time'
    ];
    protected $casts = [
        'id' => 'int',
        'relation_id' => 'string'
    ];
}
