<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class hl_config extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_config';
    public $timestamps = false;
    //修改配置
     public static function updateConfig($id,$config){
         $config['utime']=date('Y-m-d H:i:s');
         return self::whereIn('id',$id)->update($config);
        // return self::whereIn('id',$id)->update($config);
     }

     //查询配置
    public static function configInfo($id){
       return self::where('id',$id)->value('value');

    }



}
