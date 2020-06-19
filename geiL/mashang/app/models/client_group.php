<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_group extends Model
{
    // 用户组
    public $timestamps = false;
    protected $table = "client_group";
    protected $fillable = [
        'gid','uid', 'create_time'
    ];
    protected $casts = [
        'gid' => 'int',
        'uid' => 'string'
    ];
}
