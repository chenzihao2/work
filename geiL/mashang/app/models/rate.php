<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class rate extends Model
{
    // 用户组
    public $timestamps = false;
    protected $table = "rate";
    protected $fillable = [
        'id','rate', 'status', 'create_time', 'update_time'
    ];
    protected $casts = [
        'id' => 'int',
        'status' => 'int',
        'create_time' => 'int',
        'update_time' => 'int',
    ];
}
