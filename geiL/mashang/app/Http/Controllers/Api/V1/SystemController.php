<?php
namespace App\Http\Controllers\Api\V1;


use Illuminate\Support\Facades\Redis;
use App\models\tmp_records;
use App\models\order;
use App\models\client_extra;
use App\models\client;
use App\models\source_extra;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use App\Console\Commands\sendnotice;

class SystemController extends BaseController {
    private $pay_url_one = '/pay/payment?scene=s.%s&suid=%s&buyerid=%s';
    private $pay_url_two = '/recommendlist?suid=%s&buyerid=%s';
    private $time_limit = 900;
    private $scan_limit = 20;
    private $min_pay_num = 1;
    private $bid = '65eb9aed2a1ffc3c6ffb6a01b0c596f7'; //wudigeiliao
    private $prefix_uid = 'prefix_buyerid_';
    private $prefix_is_buy = 'prefix_is_buy_';
    private $prefix_warning = 'warning_';

	public function getSetting() {

		$key = "FirstPageDisplay";
		$re = Redis::exists($key);

		if ($re) {
			$display = Redis::get($key);
		} else {
			$display = 0;
			Redis::set($key,0);
		}

		$data['status_code']=200;
		$data['display'] = $display;
		return response()->json($data);
	}

    public function domain(Request $request) {
        $can_wx_pay = 0;
        $buyerid = $request->input('buyerid', '');
        $bid = $request->input('bid', '');
        $sid = $request->input('sid', 0);
        $r_key = $this->prefix_uid . $buyerid;
        $second = Redis::get($r_key);
        //$second = $request->input('second', 0);
        $need_check = 0;
        if (!$second && $sid) {
            if ($buyerid != $bid) {
                Redis::setex($r_key, 2, 1);
            }
            $need_check = $this->record_scan($request);
        }
        $suid = $request->input('suid', '');
        //if ($suid == 'undefined') {
		//    $data['status_code'] = 200;
        //    $data['data'] = [];
        //    echo json_encode($data);
        //    exit;
        //}
        //if ($bid == 'undefined') {
        //    $bid = '';
        //}
        $sid = ltrim($sid, 's.');

        $can_wx = client_extra::can_wx($suid);
        $can_wx_buyer = client_extra::can_wx($buyerid, 1);

        //$wx_pay_users = config('constants.wx_pay_users');
        //$wx_pay_users_3671 = config('constants.wx_pay_users_3671');
        //$wx_pay_users = array_merge($wx_pay_users, $wx_pay_users_3671);
        //$wx_pay_maxid = config('constants.wx_pay_maxid');
        if (!empty($suid) && $can_wx) {
            if (!empty($buyerid) && $can_wx_buyer){
                $can_wx_pay = 1;
            }
        }

        $default = config('constants.frontend_domain');
        //$default = 'glm9.qiudashi.com';
		$data['status_code'] = 200;
        $data['data'] = ['url' => $default, 'can_wx_pay' => $can_wx_pay];
        header('Content-type: application/json');
        echo json_encode($data);
        fastcgi_finish_request();
        if ($need_check) {
            $this->security_check($sid, $suid);
            $this->security_check_two($bid, $buyerid, $need_check, $suid, $sid);
        }
        //return response()->json($data);
    }

    public function open_wx_pay(Request $request) {
        $uid = $request->input('uid', '');
        $is_buyer = $request->input('is_buyer', 0);
        client_extra::open_wx_pay($uid, $is_buyer);
		$data['status_code'] = 200;
        $data['data'] = [];
        return response()->json($data);
    }

    public function tbd(Request $request) {
        $scene = $request->input('sid', '');
        $uid = $request->input('uid', '');
        $is_notice = $request->input('is_notice', 0);
        $url_prefix = config('constants.frontend_domain_qrcode');
        if ($is_notice) {
            $url_prefix = config('constants.frontend_notice_qrcode');
        }
        $url = $url_prefix . '/pay/payment?scene=' . $scene . '&uid=' . $uid . '&bid=' . $this->bid;
        $data['status_code'] = 200;
        $data['data'] = ['url' => $url];
        return response()->json($data);
    }

    private function record_scan(Request $request) {
        $sid = $request->input('sid', 0);
        $suid = $request->input('suid', '');
        if ($suid == 'undefined') {
            return false;
        }
        //if (!$dis) {
        //    return true;
        //}
        $sid = ltrim($sid, 's.');
        $buyerid = $request->input('buyerid', '');
        if ($buyerid) {
            $uid = $buyerid;
        } else {
            $token = JWTAuth::getToken();
            $clients = $this->UserInfo($token);
            $uid = isset($clients['id']) ? $clients['id'] : 0;
        }
        $r_is_buy = 0;
        $r_is_buy_key = $this->prefix_is_buy . $sid . '_' . $uid;
        $r_is_buy = Redis::get($r_is_buy_key);
        if (!$r_is_buy) {
            $is_buy = order::where('sid', $sid)->where('buyerid', $uid)->where('orderstatus', 1)->first();
            if ($is_buy) {
                $r_is_buy = 1;
                Redis::setex($r_is_buy_key, 86400, $r_is_buy);
            }
        }
        //tmp_records::lose_weight();
        if (!$r_is_buy) {
            $record_id = tmp_records::record_scan($uid, $sid);
            return $record_id;
        }
        return false;
    }

    private function security_check($sid, $uid) {
        if ($uid == 'undefined') {
            return false;
        }
        $key_time = date('Y-m-d H:i:s', time() - $this->time_limit);
        $data = tmp_records::where('scan', $sid)->where('ctime', '>', $key_time)->limit($this->scan_limit)->orderBy('ctime', 'desc')->get()->toArray();
        $buy_situation = [];
        $buy_num = 0;
        if (count($data) == $this->scan_limit) {
            foreach ($data as $item) {
                $buyerid = $item['uid'];
                if (isset($buy_situation[$buyerid]) && $buy_situation[$buyerid]) {
                    continue;
                }
                $r_is_buy_key = $this->prefix_is_buy . $sid . '_' . $buyerid;
                $r_is_buy = Redis::get($r_is_buy_key);
                if (!$r_is_buy) {
                    $is_buy = order::where('sid', $sid)->where('buyerid', $buyerid)->where('orderstatus', 1)->first();
                    if ($is_buy) {
                        Redis::setex($r_is_buy_key, 86400, 1);
                    }
                } else {
                    $is_buy = true;
                }
                if ($is_buy) {
                    $buy_num += 1;
                }
                $buy_situation[$buyerid] = 1;
            }
            if ($buy_num <= $this->min_pay_num) {
                $type = 3;
                $r_warn_key = $this->prefix_warning . $type;
                $time_out = Redis::get($r_warn_key);
                if ($time_out) {
                    return ;
                }
                \Log::info('access > 20');
                $soldnumber = 0;
                if ($sid) {
                    $source_extra_info = source_extra::where('id', $sid)->first();
                    $soldnumber = $source_extra_info['soldnumber'];
                }
                $access_count = tmp_records::where('scan', $sid)->count() ?: 0;
                $access_client = tmp_records::select('uid')->distinct('uid')->where('scan', $sid)->get()->toArray() ?: [];
                $new_client = 0;
                foreach ($access_client as $item) {
                    $f_uid = $item['uid'];
                    $access_time = tmp_records::select('ctime')->where('uid', $f_uid)->where('scan', $sid)->orderBy('ctime', 'asc')->first();
                    $access_time = $access_time['ctime'];
                    $create_time = client::select('createtime')->where('id', $f_uid)->first();
                    $create_time = $create_time['createtime'];
                    if ((strtotime($access_time) - strtotime($create_time)) < 86400) {
                        $new_client += 1;
                    }
                }
                $access_client = count($access_client);
                $sendnotice = new sendnotice();
                $sendnotice->warning($type, [$uid, $sid, $soldnumber, $access_count, $access_client, $new_client]);
            }
        }
    }

    private function security_check_two($bid, $buyerid, $record_id, $suid, $sid) {
        if ($bid == $this->bid) {
            return true;
        }
        if ($bid == $buyerid) {
            return true;
        }
        if ($bid == 'undefined') {
            return true;
        }
        if (empty($bid)) {
            return true;
            $bid = 20202020;
        }
        tmp_records::where('id', $record_id)->update(['bid' => $bid]);
        $type = 4;
        $r_warn_key = $this->prefix_warning . $type;
        $time_out = Redis::get($r_warn_key);
        if ($time_out) {
            return true;
        }
        $access_client = tmp_records::select('uid')->distinct('uid')->where('scan', $sid)->get()->toArray() ?: [];
        $access_client = count($access_client);
        $sendnotice = new sendnotice();
        $sendnotice->warning($type, [$suid, $sid, $buyerid, $bid, $access_client]);

    }

}
