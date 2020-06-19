<?php
/**
 * 登陆模块
 * User: WangHui
 * Date: 2018/11/8
 * Time: 下午5:05
 */

namespace QK\HaoLiao\Controllers\Console\Base;


use Firebase\JWT\JWT;
use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\ManageModel;
use QK\WSF\Settings\AppSetting;

class LoginController extends ConsoleController {

    public function __construct(AppSetting $appSetting) {
        $this->setTokenCheck(false);
        parent::__construct($appSetting);
    }
    public function login() {
        $params = $this->checkApiParam(['user', 'pwd']);
        $user = $params['user']; //用户名
        $pwd = $params['pwd']; //密码md5值
        //通过用户名获取用户数据
        $manageModel = new ManageModel();
        if ($user != "" && $pwd != "") {
            $userInfo = $manageModel->getManageInfo($user);
            if ($userInfo) {
                if (strtoupper(md5($pwd . $userInfo['salt'])) === $userInfo['pwd']) {
                    //登录成功
                    $key = $this->_appSetting->getConstantSetting('JWT-KEY');
                    $jwtArray = [
                        "iat" => time(), "exp" => time() + 7200 * 5,
                    ];
                    $userInfo = array_merge($userInfo, $jwtArray);
                    $jwt = JWT::encode($userInfo, $key);
                    $data['user_id'] = intval($userInfo['uid']);
                    $data['realName'] = $userInfo['real_name'];
                    $data['userName'] = $user;
                    $data['token'] = $jwt;
                    $this->responseJson($data);
                } else {
                    //登录失败
                    $this->responseJsonError(5003);
                }
            } else {
                $this->responseJsonError(5002);
            }

        } else {$this->responseJsonError(5001);
        }


    }
}
