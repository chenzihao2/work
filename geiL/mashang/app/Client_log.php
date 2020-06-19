<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Client_log extends Model
{
    protected $table = "client_log";

    protected $fillable = [
        'uid', 'register',
    ];
}
