<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Respository\FaceUtility;


class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public $user_info = null;
    protected $cate=[
        ['id'=>1,'title'=>'广告，垃圾信息'],
        ['id'=>2,'title'=>'政治敏感'],
        ['id'=>3,'title'=>'淫秽色情'],
        ['id'=>4,'title'=>'其他'],
    ];//举报类型
    public function __construct() {
    }

    //正常返回
    public function rtJson_($data = [], $code = 200, $message = 'SUCCESS') {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
        //->withHeaders(['ttttttoken' => 1111]);
    }

    //正常输出
    public function rtJson($data = [], $code = 200, $message = 'SUCCESS') {
        header('Content-Type:application/json; charset=utf-8');
        $datas = json_encode([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            ]);
        echo $datas;
    }


    //异常返回 将异常信息配置在 errorcode.php 中
    public function rtJsonError($code = 500, $message = '',$data = []) {
        if (!$message) {
            $message = config('errorcode.' . $code) ?: '';
        }
        $code == 0 && $code == 500;
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
        ]);
    }

    //获取旧项目中的token
    public function getOldToken($user_id = 0, $nick_name = '') {
        $this->utility = new FaceUtility();
        $domain = config('app.old_domain');
        $url = $domain . "index.php?user_id=&token=&platform=1&v=2&p=user&c=login&do=getTokenForNew&v=2&p=user&user_id=$user_id&nick_name=".urlencode($nick_name);
        $res = $this->utility->httpRequestOnce($url);
        $result = json_decode($res, 1);
        return $result['data']['token'];
    }

}
