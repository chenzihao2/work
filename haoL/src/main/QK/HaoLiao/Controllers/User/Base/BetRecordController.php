<?php
/**
 * 战绩相关接口
 * User: YangChao
 * Date: 2018/10/17
 */

namespace QK\HaoLiao\Controllers\User\Base;


use QK\HaoLiao\Controllers\User\UserController;
use QK\HaoLiao\Model\BetRecordModel;

class BetRecordController extends UserController {

    /**
     * 根据日期获取战绩统计API
     */
    public function totalStat(){
        $param = $this->checkApiParam(['date'], ['user_id' => 0]);
        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        }
        $date = $param['date'];
        $betRecordModel = new BetRecordModel();
        $totalStat = $betRecordModel->getBetRecordStatByDate($date);
        $this->responseJson($totalStat);
    }

    /**
     * 获取专家战绩列表
     */
    public function expertStatList(){
        $this->checkToken();
        $param = $this->checkApiParam(['date'], ['page' => 1, 'pagesize' => 10]);
        $date = $param['date'];
        $page = intval($param['page']);
        $pagesize = intval($param['pagesize']);
        $betRecordModel = new BetRecordModel();
        $data = $betRecordModel->getExpertBetRecordStatByDate($date, $page, $pagesize);
        $this->responseJson($data);
    }

    /**
     * 获取专家30天战绩
     */
    public function expertMonthStat(){
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0]);

        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        }
        $expertId = $param['expert_id'];
        $betRecordModel = new BetRecordModel();
        $data = [];
        //获取专家近30日战绩
        $betRecord = $betRecordModel->getBetRecordMonthStatByExpertId($expertId);

        //计算3日/7日/30日胜率
        $data = $betRecordModel->statisticsWinning($betRecord);

        $data['bet_record'] = $betRecord;

        $this->responseJson($data);
    }


}