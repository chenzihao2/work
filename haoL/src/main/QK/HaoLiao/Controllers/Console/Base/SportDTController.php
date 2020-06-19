<?php

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\BaseController;
use QK\HaoLiao\Model\SportsDTModel;

class SportDTController extends BaseController {
    public function list(){
        $sportsDTModel = new SportsDTModel();
        $data = $sportsDTModel->lists();
        $this->responseJson($data);
    }

    public function info() {
      $param = $this->checkApiParam(['gameId'], []);
      $gid = $param['gameId'];
      $sportsDTModel = new SportsDTModel();
      $data = $sportsDTModel->gameInfo($gid);
      $this->responseJson($data);
    }

}
