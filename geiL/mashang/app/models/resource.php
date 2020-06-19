<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class resource extends Model
{
    // 资源表
    public $timestamps = false;
    protected $table = "resource";
    protected $fillable = [
        'id','sid', 'uid', 'sourceid', 'sindex', 'stype', 'url', 'description', 'status', 'createtime', 'modifytime',
    ];
    protected $casts = [
        'id' => 'string',
        'sid' => 'string',
        'uid' => 'string',
        'sourceid' => 'string',
    ];
}
