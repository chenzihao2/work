<?php

namespace App\models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class client_subscribe extends Authenticatable implements JWTSubject
{
    // 用户订阅表
    public $timestamps = false;
    protected $table = "client_subscribe";
    protected $casts = [
        'id' => 'string',
    ];

    protected $fillable = [
        'user_id', 'openid', 'appid', 'status', 'create_time'
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}
