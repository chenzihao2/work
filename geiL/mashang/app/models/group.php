<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class group extends Model
{
    // 用户组
    public $timestamps = false;
    protected $table = "group";
    protected $fillable = [
        'id','name', 'create_time'
    ];
    protected $casts = [
        'id' => 'string',
        'name' => 'string'
    ];
}
