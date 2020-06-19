<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class client_rate extends Model
{
    //
    public $timestamps = false;
    protected $table = "client_rate";

    protected $fillable = [
        'uid', 'grade', 'rate', 'effecttime', 'createtime'
    ];

    protected $casts = [
        'id' => 'string',
    ];
}
