<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\UsersChannel;
class User extends Authenticatable implements JWTSubject
{
    use Notifiable;
    //protected $connection = 'mysql_chat';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
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

    /*
     * 获取用户信息
     * $userId 用户id
     */

    public static function userInfo($userId){
            return self::where(['user_id'=>$userId])->first();
    }
	
	/*
	*
	*修改用户信息
	*$userId 用户id
	*$data 要修改的信息
	*/
	public static function updateUser($userId,$data){
		 return self::where(['user_id'=>$userId])->update($data);
	}
	


    public static function addUser($data) {
        $exists = self::where('phone', $data['phone'])
            ->where('phone', '<>' , '')
            ->exists();
        if ($exists) {
            return true;
        }
        $exists2 = self::where('phone',  '')
            ->where('nick_name', $data['nick_name'])
            ->exists();
        if ($exists2) {
            return true;
        }

        $exists3 = self::where('nick_name', $data['nick_name'])
            ->exists();
        if ($exists3) {
            return true;
        }
        $exists4 = self::where('user_id', $data['user_id'])
            ->exists();
        if ($exists4) {
            return true;
        }
        return self::insertGetId($data);
    }

    public static function phoneLogin($phone) {
        $user_info = self::where('phone', $phone)->first();
        if ($user_info) {
            $user_info['is_reg']=0;
            return $user_info;
        }
        $i_data = [];
        $i_data['phone'] = $phone;
        $i_data['nick_name'] = substr_replace($phone, '****', 3, 4);
        $user_id = self::insertGetId($i_data, 'user_id');
        $user_info = self::where('user_id', $user_id)->first();
        $user_info['is_reg']=1;
        return $user_info;
    }

    /*
     * 检查手机号是否已存在
     */

    public static function existsPhone($phone){
        $exists = self::where('phone', $phone)->exists();
        return $exists;
    }

    /*
     * 创建用户
     */
    public static function createUser($data){
        return self::insertGetId($data);
    }





}
