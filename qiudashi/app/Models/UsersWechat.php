<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersWechat extends Model
{
    protected $table = 'users_wechat';

    public static function addWechat($data) {
        $exists = self::where('openid', $data['openid'])
            ->exists();
        if ($exists) {
            return true;
        }
        return self::insertGetId($data);
    }

    /*
     * 检查用户是否存在
     */

    public static function existsWechat($openid){
        return self::where('openid',$openid)->first();
    }
    /*
     * 根据unionid 查询
     */
    public static function existsUnWechat($unionid){
        return self::where('unionid',$unionid)->first();
    }
    /*
     * 创建/获取 微信用户
     */
    public static function insertWechat($data){
        $exists = self::where('openid', $data['openid'])
            ->first();
        if ($exists) {
            $exists['is_reg']=0;
        }else{
            $id=self::insertGetId($data);
            $exists=self::where('id',$id)->first();
            $exists['is_reg']=1;
        }
        return $exists;
    }


    /*
     * 修改微信用户信息
     * $wid user_wechat 表id
     * $data[] 修改的信息
     */
    public static function updateWechat($wid,$data){
        return self::where('id',$wid)->update($data);
    }
    /*
     * 获取微信用户信息
     * $where[] 查询条件
     */
    public static function getWechatInfo($where){

        return self::where($where)->first();
    }
}
