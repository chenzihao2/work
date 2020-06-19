<?php
/**
 * 分销商管理
 * User: YangChao
 * Date: 2018/12/06
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\DAL\DALUserDistRate;
use QK\HaoLiao\Model\DistExtraModel;
use QK\HaoLiao\Model\DistModel;
use QK\HaoLiao\Model\DistRateModel;
use QK\HaoLiao\Model\UserModel;

class DistController extends ConsoleController {

    /**
     * 获取分销商列表
     */
    public function distList(){
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 30]);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $query = json_decode($params['query'], true);
        $distModel = new DistModel();
        $list = $distModel->getDistList($query, $page, $pageSize);
        if(!empty($list['list'])){
            $distExtraModel = new DistExtraModel();
            $userModel = new UserModel();
            foreach($list['list'] as $key => $val){
                $distId = $val['dist_id'];
                $userInfo = $userModel->getUserInfo($distId);
                $val['nick_name'] = $userInfo['nick_name'];
                $val['headimgurl'] = $userInfo['headimgurl'];

                $distExtraInfo = $distExtraModel->getDistExtraInfo($distId);
                $val['income'] = $distExtraInfo['income'];
                $val['balance'] = $distExtraInfo['balance'];
                $val['withdrawed'] = $distExtraInfo['withdrawed'];
                $val['income_yuan'] = $distExtraInfo['income_yuan'];
                $val['balance_yuan'] = $distExtraInfo['balance_yuan'];
                $val['withdrawed_yuan'] = $distExtraInfo['withdrawed_yuan'];
                $val['gain_user'] = $distExtraInfo['gain_user'];
                $list['list'][$key] = $val;
            }
        }
        $this->responseJson($list);
    }

    /**
     * 获取分销商详情
     */
    public function distInfo(){
        $params = $this->checkApiParam(['dist_id']);
        $distId = $params['dist_id'];
        $distModel = new DistModel();
        $distInfo = $distModel->getDistInfo($distId);

        $userModel = new UserModel();
        $userInfo = $userModel->getUserInfo($distId);
        $distInfo['nick_name'] = $userInfo['nick_name'];
        $distInfo['headimgurl'] = $userInfo['headimgurl'];

        $distExtraModel = new DistExtraModel();
        $distExtraInfo = $distExtraModel->getDistExtraInfo($distId);
        $distInfo['income'] = $distExtraInfo['income'];
        $distInfo['balance'] = $distExtraInfo['balance'];
        $distInfo['withdrawed'] = $distExtraInfo['withdrawed'];
        $distInfo['gain_user'] = $distExtraInfo['gain_user'];

        $distRateModel = new DistRateModel();
        $distRateInfo = $distRateModel->getDistRateInfo($distId);
        $distInfo['rate'] = $distRateInfo['rate'];

        $this->responseJson($distInfo);
    }

    /**
     * 设置分销商信息
     */
    public function setDist(){
        $params = $this->checkApiParam(['dist_id', 'dist_address', 'rate']);
        $distId = $params['dist_id'];
        $distAddress = $params['dist_address'];
        $rate = $params['rate'];

        $distModel = new DistModel();
        $distModel->setDistInfo($distId, ['dist_address' => $distAddress]);

        $rateData = [];
        $rateData['dist_id'] = $distId;
        $rateData['rate'] = $rate;
        $rateData['effect_time'] = $rateData['create_time'] = time();
        $dalUserDistRate = new DALUserDistRate($this->_appSetting);
        $dalUserDistRate->newDistRate($rateData);
        $this->responseJson();
    }

    /**
     * 设置分销商状态
     */
    public function setDistStatus(){
        $params = $this->checkApiParam(['dist_id', 'dist_status']);
        $distId = $params['dist_id'];
        $distStatus = $params['dist_status'];

        $distModel = new DistModel();
        $distModel->setDistInfo($distId, ['dist_status' => $distStatus]);

        $this->responseJson();
    }



}