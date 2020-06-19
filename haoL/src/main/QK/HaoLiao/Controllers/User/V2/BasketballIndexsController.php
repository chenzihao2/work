<?php
/**
 * 篮球比赛指数相关接口
 * User: zhangyujie
 * Date: 2019/08/29
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\Base\BasketballIndexsController as BasketballIndexs;
use QK\HaoLiao\Model\BasketballIndexsModel;
use QK\WSF\Settings\AppSetting;
class BasketballIndexsController extends BasketballIndexs {
    public function __construct(AppSetting $appSetting) {
//        $this->setTokenCheck(false);

        $this->testM = new BasketballIndexsModel();

    }

    //比赛指数接口 前端展示
    public function getIndex(){

        $param = $this->checkApiParam(['match_num','indexs_type'], []);
        $match_num=$param['match_num'];
        $indexs_type=$param['indexs_type'];

        $list=$this->testM->lists($match_num,$indexs_type);

        $this->responseJson($list);
    }
    //公司指数详情
    public function compMatchIndexs() {
        $params = $this->checkApiParam(['match_num'], ['indexs_type' => 1, 'comp_num' => 0]);
        $match_num = $params['match_num'];
        $indexs_type = $params['indexs_type'];
        $comp_num = $params['comp_num'];
        $result = $this->testM->compIndexs($match_num, $indexs_type, $comp_num);
        //dump($result);
        $this->responseJson($result);
    }
}
