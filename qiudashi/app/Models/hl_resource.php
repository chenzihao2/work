<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class hl_resource extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_resource';//方案表
    protected $view_record_table = 'hl_resource_view_record';//方案浏览记录表
    protected $resource_extra = 'hl_resource_extra';//方案扩展表
    public $timestamps = false;
    public function __construct() {


    }

    /*
     * 方案浏览记录
     */
    public function viewRecord($device,$resource_id,$user_id=0){
        $data=['device'=>$device,'resource_id'=>$resource_id,'user_id'=>$user_id,'ctime'=>date('Y-m-d H:i:s')];
        self::from($this->view_record_table)->insert($data);
    }

}
