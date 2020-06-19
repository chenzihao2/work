<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_account extends Model
{
    // 用户副表
    public $timestamps = false;
    protected $table = "client_account";

    protected $fillable = [
        'id', 'uid', 'type', 'name', 'id_card', 'account', 'bank', 'is_default', 'create_time'
    ];
}
