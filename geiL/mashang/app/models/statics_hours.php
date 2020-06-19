<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class statics_hours extends Model
{
    public $timestamps = false;
    protected $table = "statics_hours";

    protected $fillable = [
        'statictime', 'order', 'resource', 'source', 'user', 'service_fee', 'withdrawed', 'withdrawing', 'total', 'active_sell', 'active_buy', 'active',
    ];
}
