<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersExpert extends Model
{
    //
    protected $table = 'users_expert';

    public static function addExpert($data) {
        $exists = self::where('real_name', $data['real_name'])
            ->orWhere('user_id', $data['user_id'])
            ->exists();
        if ($exists) {
            return true;
        }
        return self::insert($data);
    }
}
