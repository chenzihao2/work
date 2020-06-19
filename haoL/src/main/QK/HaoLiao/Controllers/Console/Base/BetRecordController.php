<?php
/**
 * 战绩相关接口
 * User: YangChao
 * Date: 2018/11/16
 */

namespace QK\HaoLiao\Controllers\Console\Base;


use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\BetRecordModel;

class BetRecordController extends ConsoleController{


    /**
     * 获取战绩列表API
     */
    public function betRecordList(){
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 15]);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $query = json_decode($params['query'], true);
        $dateStart = isset($query['date_start']) ? $query['date_start'] : date('Y-m-d', strtotime('-15 days'));
        $dateEnd = isset($query['date_end']) ? $query['date_end'] : date('Y-m-d', strtotime('-1 day'));

        $start = ($page - 1) * $pageSize;
        $startDate = date('Y-m-d',strtotime("-$start day", strtotime($dateEnd)));
        $endDate = date('Y-m-d',strtotime("-$pageSize day", strtotime($startDate)));

        $data = [];
        $data['total'] = (strtotime($dateEnd) - strtotime($dateStart)) / 86400;

        $betRecordModel = new BetRecordModel();
        $date = $startDate;
        $key = 0;
        while ($date > $endDate && $date > $dateStart) {
            $data['combat_gains'][$key] = $betRecordModel->getBetRecordStatByDate($date);
            $data['combat_gains'][$key]['date'] = $date;
            $date = date('Y-m-d',strtotime("-1 day", strtotime($date)));
            $key++;
        }

        $this->responseJson($data);
    }

    /**
     * 获取单日战绩列表
     */
    public function betRecordExpertList(){
        $params = $this->checkApiParam(['date'], ['page' => 1, 'pagesize' => 15]);
        $date = trim($params['date']);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $betRecordModel = new BetRecordModel();
        $total = $betRecordModel->getBetRecordTotalByDate($date);
        $betRecordTotal = $betRecordModel->getBetRecordStatByDate($date);
        $expertList = $betRecordModel->getExpertBetRecordStatByDate($date, $page, $pageSize);
        $data = [];
        $data['total'] = $total;
        $data['betRecordTotal'] = $betRecordTotal;
        $data['expertList'] = $expertList;
        $this->responseJson($data);
    }

    /**
     * 设置战绩单日推荐语
     */
    public function setBetRecordDesc(){
        $params = $this->checkApiParam(['date', 'desc']);
        $date = trim($params['date']);
        $desc = trim($params['desc']);
        $betRecordModel = new BetRecordModel();
        $betRecordModel->setBetRecordDesc($date, $desc);
        $this->responseJson();
    }

}