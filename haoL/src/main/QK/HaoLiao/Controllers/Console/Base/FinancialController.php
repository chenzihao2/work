<?php
/**
 * 财务数据管理
 * User: YangChao
 * Date: 2018/11/19
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\FinancialModel;

class FinancialController extends ConsoleController {

    /**
     * 获取财务数据列表
     */
    public function financialList(){
        $params = $this->checkApiParam([], ['query' => '', 'page' => 1, 'pagesize' => 15]);
        $page = intval($params['page']);
        $pageSize = intval($params['pagesize']);
        $query = json_decode($params['query'], true);
        $startDate = isset($query['date_start']) ? date('Y-m-d', $query['date_start']) : date('Y-m-d', strtotime('-15 days'));
        $endDate = isset($query['date_end']) ? date('Y-m-d', $query['date_end']) : date('Y-m-d');

        $financialModel = new FinancialModel();
        $financialList = $financialModel->getFinancialList($startDate, $endDate, $page, $pageSize);

        $data = [];
        $data['total'] = (strtotime($endDate) - strtotime($startDate)) / 86400;
        $data['financial'] = $financialList;
        $this->responseJson($data);
    }
}