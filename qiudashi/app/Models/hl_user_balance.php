<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class hl_user_balance extends Model
{
    /*
     * 用户余额表
     */
    protected $connection = 'mysql_origin';
    protected $table = 'hl_user_balance';
    public static function getUserBalanceInfo($user_id){

        return self::where('user_id',$user_id)->value('vc_balance');

    }



}
