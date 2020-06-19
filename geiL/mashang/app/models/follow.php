<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class follow extends Model
{
    // 料
    public $timestamps = false;
    protected $table = "follow";
    protected $fillable = [
        'id','star', 'fans', 'status', 'create_time'
    ];

}
