<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UsersWechat;
use App\Models\UsersExpert;
use App\Models\UsersChannel;

use Illuminate\Support\Facades\DB;
use toolbox\net\FileDownload;

class hl_login_channel extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_login_channel';
    protected $primaryKey = 'id';
    public $timestamps = false;
   // protected $guarded = [];

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
