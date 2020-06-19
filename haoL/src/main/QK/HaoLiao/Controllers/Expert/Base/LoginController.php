<?php
/**
 * Date: 2019/06/25
 * Time: 11:41
 */

namespace QK\HaoLiao\Controllers\Expert\Base;

use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ExpertExtraModel;

class AuthController extends BaseController {

    public function login() {
        $params = $this->checkApiParam(['user_id']);
        $user_id = $params['user_id'];

        return $this->responseJson($data);
    }

}
