<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class sources_info extends Model
{
    protected $table = 'sources_info';

    public function Sources()
    {
        return $this->belongsTo(Sources::class);
    }
}
