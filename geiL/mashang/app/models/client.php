<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class client extends Authenticatable implements JWTSubject
{
    // 用户表
    public $timestamps = false;
    protected $table = "client";
    protected $casts = [
        'id' => 'string',
    ];

    protected $fillable = [
        'id', 'openid', 'serviceid', 'unionid', 'nickname', 'sex', 'city', 'country', 'avatarurl', 'balance', 'sessionkey','createtime','modifytime', 'auth_refresh', 'signature'
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
