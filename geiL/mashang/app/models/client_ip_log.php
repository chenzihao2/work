<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;
use App\models\user_agent;
use \QKPHP\Common\Utils\Url;
use App\Console\Commands\sendnotice;

class client_ip_log extends Model
{
    public $timestamps = false;
    protected $table = "client_ip_log";
    const TIME_LIMIT_IP = 300; //s 
    const IP_TIME_LIMIT = 4; //s 
    
    static public function record_ip($uid) {
        $ip = Url::getClientIp();
        $uaid = 0;
        //$ua = $_SERVER['HTTP_USER_AGENT'];
        //$ua_encode = md5($ua);
        //$ua_info = user_agent::where('ua_encode', $ua_encode)->first();
        //if ($ua_info) {
        //    $uaid = $ua_info['id'];
        //} else {
        //    $uaid = user_agent::insertGetId(['ua' => $ua, 'ua_encode' => $ua_encode]);
        //}
        $insert_data = ['uid' => $uid, 'ip' => $ip, 'uaid' => $uaid];
        self::insert($insert_data);
        self::check($insert_data);
    }

    static private function check($data) {
        $time_scope = [date('Y-m-d H:i:s', time() - self::TIME_LIMIT_IP), date('Y-m-d H:i:s')];
        $ip_data = self::select('uid')->where('ip', $data['ip'])->distinct('uid')->whereBetween('createtime', $time_scope)->limit(self::IP_TIME_LIMIT)->get()->toArray();
        if (count($ip_data) == self::IP_TIME_LIMIT) {
            $uids = array_column($ip_data, 'uid');
            $uids = implode(',', $uids);
            \Log::info('ip > 3   === ' . json_encode($ip_data)); 
            $sendnotice = new sendnotice();
            $sendnotice->warning(1 , $uids);
        }
    }
}
