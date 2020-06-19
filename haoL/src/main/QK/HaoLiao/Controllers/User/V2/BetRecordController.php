<?php
/**
 * 战绩相关接口
 * User: YangChao
 * Date: 2018/10/17
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\Base\BetRecordController as BetRecord;
use QK\HaoLiao\Model\BetRecordModel;
use QK\HaoLiao\Model\ExpertModel;

class BetRecordController extends BetRecord {

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
        $data = $betRecordModel->statisticsWinning($betRecord, 0);

        //计算1,3,5,7,15,30日胜率
        $winningBetRecord = $betRecordModel->statisticsWinningV2($betRecord);

        $data['winning_bet_record'] = $winningBetRecord;

        $this->responseJson($data);
    }

    public function expertStatChart(){
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0, 'platform' => 2]);

        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        }
        $expertId = $param['expert_id'];
        $expertModel = new ExpertModel();

        $data = [];

        $platform = intval($param['platform']);
        $winningBetRecord = $expertModel->calBetRecordStatList($expertId, $platform);
        $data['winning_bet_record'] = $winningBetRecord;

        $this->responseJson($data);
    }


    public function expertStatChartV2(){
        $param = $this->checkApiParam(['expert_id'], ['user_id' => 0, 'platform' => 2]);

        $userId = $param['user_id'];
        if($userId){
            $this->checkToken();
        }
        $expertId = $param['expert_id'];
        $expertModel = new ExpertModel();

        $data = [];

        $platform = intval($param['platform']);
        $winningBetRecord = $expertModel->calBetRecordStatListV2($expertId, $platform);
        $data['winning_bet_record'] = $winningBetRecord;

        $this->responseJson($data);
    }


    /**
     * 专家近十战战绩列表
     */
    public function nearTenRecord() {
        $params = $this->checkApiParam(['expert_id'], ['user_id' => 0, 'platform' => 2, 'display' => null]);

        $userId = $params['user_id'];
        if($userId){
            $this->checkToken();
        }

        //$display = intval($params['display']);
        $platform = intval($params['platform']);

        //if ( ! is_null($display)) {
        //    $platform = ($display == 1) ? 0 : 1;
        //}

        $betRecordModel = new BetRecordModel();
        $lists = $betRecordModel->nearTenRecord($params['expert_id'], $platform);
        $this->responseJson(!$lists ? [] : $lists);
    }

}
