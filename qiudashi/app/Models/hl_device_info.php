<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\UsersWechat;
use App\Models\UsersExpert;
use App\Models\UsersChannel;

use Illuminate\Support\Facades\DB;
use toolbox\net\FileDownload;

class hl_device_info extends Model
{
    //
    protected $connection = 'mysql_origin';
    protected $table = 'hl_device_info';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $guarded = [];

    /*
      * 新增/修改设备详细信息
      */
    public static function addDeviceInfo($data){
        $times=date("Y-m-d H:i:s");

        $platform=$data['platform'];
        $result['user_id']=isset($data['user_id'])?$data['user_id']:'';
        $result['country']=isset($data['country'])?$data['country']:'';
        $result['province']=isset($data['province'])?$data['province']:'';
        $result['city']=isset($data['city'])?$data['city']:'';
        $result['district']=isset($data['district'])?$data['district']:'';
        $result['resolution']=isset($data['Resolution'])?$data['Resolution']:'';
        $result['first_install_channel']=isset($data['Channel'])?$data['Channel']:'';
        $result['install_channel']=isset($data['Channel'])?$data['Channel']:'';
        $result['first_install_datetime']=date("Y-m-d H:i:s",isset($data['FirstInstallTime'])?bcdiv($data['FirstInstallTime'], 1000):time());
        $result['last_update_time']=date("Y-m-d H:i:s",($platform=='ios')?$data['LastUpdateTime']:bcdiv($data['LastUpdateTime'], 1000));
        $result['app_version']=isset($data['AppVersion'])?$data['AppVersion']:'';
        $result['device_version']=isset($data['DeviceVersion'])?$data['DeviceVersion']:'';
        $result['readable_version']=isset($data['AppReadableVersion'])?$data['AppReadableVersion']:'';
        $result['application_name']=isset($data['ApplicationName'])?$data['ApplicationName']:'';
        $result['build_number']=isset($data['BuildNumber'])?$data['BuildNumber']:'';
        $result['bundle_id']=isset($data['BundleId'])?$data['BundleId']:'';
        $result['font_scale']=isset($data['FontScale'])?$data['FontScale']:'';
        $result['carrier']=isset($data['Carrier'])?$data['Carrier']:'';
        $result['manufacturer']=isset($data['Manufacturer'])?$data['Manufacturer']:'';
        $result['max_memory']=isset($data['MaxMemory'])?$data['MaxMemory']:'';
        $result['phone_number']=isset($data['PhoneNumber'])?$data['PhoneNumber']:'';
        $result['serial_number']=isset($data['SerialNumber'])?$data['SerialNumber']:'';
        $result['system_version']=isset($data['SystemVersion'])?$data['SystemVersion']:'';
        $result['system_name']=isset($data['SystemName'])?$data['SystemName']:'';
        $result['total_disk_capacity']=isset($data['TotalDiskCapacity'])?$data['TotalDiskCapacity']:'';
        $result['free_disk_storage']=isset($data['FreeDiskStorage'])?$data['FreeDiskStorage']:'';
        $result['total_memory']=isset($data['TotalMemory'])?$data['TotalMemory']:'';
        $result['device_ip']=isset($data['Address'])?$data['Address']:'';
        $result['idfa']=isset($data['Idfa'])?$data['Idfa']:'';
        $result['idfv']=isset($data['UniqueId'])?$data['UniqueId']:'';
        $result['mac']=isset($data['MacAddress'])?$data['MacAddress']:'';
        $result['android_id']=isset($data['AndroidId'])?$data['AndroidId']:'';
        $result['device_brand']=isset($data['DeviceBrand'])?$data['DeviceBrand']:'';
        $result['device_model']=isset($data['DeviceModel'])?$data['DeviceModel']:'';
        $result['device_number']=isset($data['device'])?$data['device']:'';
        $result['ctime']=$times;
        $result['utime']=$times;


        if($platform=='ios'){
            // $where['idfa']=$data['Idfa'];
            $where['device_number']=$data['device'];
        }else{
            $where['android_id']=$data['AndroidId'];
        }
        $info=self::where($where)->first();
        if($info){
            unset($result['first_install_channel']);
            unset($result['first_install_datetime']);
            unset($result['ctime']);
            self::where($where)->update($result);
        }else{
            if($platform=='ios'){
                $result['first_install_datetime']=$times;
            }
            self::insert($result);
        }
    }

}
