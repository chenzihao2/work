<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class admin extends Authenticatable implements JWTSubject
{
    // 后台用户表
    public $timestamps = false;
    protected $table = "admin";

    protected $fillable = [
        'id', 'username', 'password', 'name', 'role', 'telephone', 'email', 'createtime',
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

    protected $casts = [
        'id' => 'string',
    ];

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
