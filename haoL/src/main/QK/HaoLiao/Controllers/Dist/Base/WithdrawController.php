<?php
/**
 * 分销商提现处理
 * User: YangChao
 * Date: 2018/12/05
 */

namespace QK\HaoLiao\Controllers\Dist\Base;

use QK\HaoLiao\Controllers\Dist\DistController as Dist;
use QK\HaoLiao\Model\DistExtraModel;
use QK\HaoLiao\Model\DistModel;
use QK\HaoLiao\Model\DistWithdrawModel;

class WithdrawController extends Dist {

    /**
     * 分销商提现接口
     */
    public function doWithDraw() {
        $param = $this->checkApiParam(['user_id']);
        $userId = $param['user_id'];

        //获取分销商详情
        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfo($userId);
        if(empty($distInfo)){
            $this->responseJsonError(101);
        }

        $distExtraModel = new DistExtraModel();
        $distExtraInfo = $distExtraModel->getDistExtraInfo($userId);
        if($distExtraInfo['balance'] <= 0){
            //没有可提现余额
            $this->responseJsonError(4002);
        }

        $distWithdrawModel = new DistWithdrawModel();

        $distWithDrawInfo = $distWithdrawModel->getDistWithDraw($userId);
        if(!empty($distWithDrawInfo)){
            $this->responseJsonError(4001);
        }

        $withdrawMoney = $distExtraInfo['balance'];
        $distWithdrawModel->doWithDraw($userId, $withdrawMoney);

        $this->responseJson();
    }



}