<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class UsersChannel extends Model
{
    //
    protected $table = 'users_channel';
    protected $timestamp = false;
	
	
	/*
     * 获取用户原始设备
     */
    public function orgChannel($userId){
        
        return self::where('user_id',$userId)->orderBy('cid','asc')->first();
    }
	

    public static function addChannel($data) {
        if (isset($data['cid'])) {
            $exists = self::where('cid', $data['cid'])
                ->exists();
            if ($exists) {
                return true;
            }
        }
        $exists1 = self::where('user_id', $data['user_id'])
            ->where('target', $data['target'])
            ->where('platform', $data['platform'])
            ->where('channel', $data['channel'])
            ->first();
        if ($exists1) {
            $cid = $exists1['cid'];
            User::where('user_id', $data['user_id'])
                ->update(['cid' => $cid]);
            return true;
        }
        $cid = self::insertGetId($data, 'cid');
        User::where('user_id', $data['user_id'])
            ->update(['cid' => $cid]);
        return;
    }

    /*
     * 写入渠道
     */
    public static function insertChannel($data){
        $exists1 = self::where('device', $data['device'])->first();
        if(!$exists1){
            $data['is_one']=1;
        }
        return self::insertGetId($data, 'cid');
    }
}
