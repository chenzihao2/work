<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class profile extends Model
{
    protected $table = "profile";

    protected $fillable = [
        'uid', 'grade', 'profit', 'is_buy', 'is_sell',
    ];
}
