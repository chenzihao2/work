<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class contents extends Model
{
    public $timestamps = false;
    protected $table = "contents";
    protected $fillable = [
        'cid', 'sid', 'uid', 'description', 'createtime', 'modifytime'
    ];
    protected $casts = [
        'cid' => 'string',
        'sid' => 'string',
        'uid' => 'string'
    ];
}
