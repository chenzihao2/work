<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Sources extends Model
{

    public function belongsToClients()
    {
        return $this->belongsTo('clients', 'uid', 'id');
    }

    public function source_info()
    {
        return $this->hasMany(sources_info::class);
    }
}
