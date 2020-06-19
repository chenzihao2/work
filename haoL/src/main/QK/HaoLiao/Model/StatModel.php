<?php
/**
 * 统计数据
 * User: WangHui
 * Date: 2018/10/22
 * Time: 下午3:56
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\DAL\DALFinancial;
use QK\HaoLiao\DAL\DALOrder;
use QK\HaoLiao\DAL\DALStatBetRecord;

class StatModel extends BaseModel {

    /**
     * 红黑单统计
     * @param $expert
     * @param $time
     * @param $matchType
     * @param $type
     */
    public function betRecordStat($expert, $time, $matchType, $type) {
        $dalStatBet = new DALStatBetRecord($this->_appSetting);
        $params = [];
        $redisKey[] = BETRECORD_DATE_INFO . $time;
        $redisKey[] = BETRECORD_DATE_INFO_DESC . $time;
        $redisKey[] = BETRECORD_EXPERT_INFO . $expert . ':' . $time;

        $redisModel = new RedisModel('betRecord');
        $redisModel->redisDel($redisKey);

        if ($this->checkBetRecordExist($expert, $time, $matchType) == 1) {
            //更新数据
            if ($type == 1||$type == 4||$type == 5||$type == 6) {
                $params['red'] = "+ 1";
            } elseif ($type == 2) {
                $params['go'] = "+ 1";

            } elseif ($type == 3||$type == 7) {
                $params['black'] = "+ 1";
            }
            $dalStatBet->setStatIncOrDec($expert, $time, $matchType, $params);

        } else {
            //新建数据
            $params['date'] = $time;
            $params['expert_id'] = $expert;
            $params['match_type'] = $matchType;
            if ($type == 1||$type == 4||$type == 5||$type == 6) {
                $params['red'] = 1;
            } elseif ($type == 2) {
                $params['go'] = 1;

            } elseif ($type == 3||$type == 7) {
                $params['black'] = 1;
            }
            $dalStatBet->newStat($params);
        }

    }

    /**
     * 检查统计数据是否存在
     * @param $expert
     * @param $time
     * @param $matchType
     * @return mixed
     */
    public function checkBetRecordExist($expert, $time, $matchType) {
        $dalStatBetRecord = new DALStatBetRecord($this->_appSetting);
        return $dalStatBetRecord->checkBetRecordExist($expert, $time, $matchType);
    }


    /**
     * 财务数据日统计
     * @param $time
     */
    public function getFinancialStat($time) {
        //统计一天前的数据
        $time = $time - 24 * 3600;
        $startTime = strtotime(date("Y-m-d 0:0:0", $time));
        $endTime = strtotime(date("Y-m-d 23:59:59", $time));
        $dalStatBetRecord = new DALOrder($this->_appSetting);
        $orderStatData = $dalStatBetRecord->getStatData($startTime, $endTime);
        //日期
        $statData['date'] = date("Y-m-d", $time);
        //流水总额
        $statData['account_flow'] = $orderStatData['amount'];
        //交易单数
        $statData['order_total'] = $orderStatData['order_count'];
        //购买人数
        $statData['buy_user_total'] = $orderStatData['buyer_count'];
        //订阅信息
        $subscribeStatData = $dalStatBetRecord->getSubscribeStatData($startTime, $endTime);
        //订阅总额
        $statData['subscribe_money_total'] = $subscribeStatData['amount'];
        //专栏订阅数
        $statData['subscribe_total'] = $subscribeStatData['count'];

        //TODO
        //退款总额
        $statData['refund_money'] = $orderStatData['buyer_count'];
        //退款单数
        $statData['refund_total'] = $orderStatData['buyer_count'];
        //退款人数
        $statData['refund_user_total'] = $orderStatData['buyer_count'];
        //原始服务费
        $statData['original_service_fee'] = $orderStatData['buyer_count'];
        //优惠发放服务费
        $statData['discount_fee'] = $orderStatData['buyer_count'];
        //腾讯服务费
        $statData['provider_fee'] = $orderStatData['buyer_count'];
        //毛利
        $statData['profit'] = $orderStatData['buyer_count'];


//        流水总额=所有料订单总额 - 不中退款订单退款总额+当天订阅专栏订单总额
//毛利润=（所有料订单总额 - 不中退款订单退款总额+当天订阅专栏订单总额）*（1-专家分成比例-分销商比例）
//成交单数=当天0-24点所有完成的订单数，订阅专栏算1单 (算不算退款单)
//退款总额=当天不中退款订单退款总额
//退款单数=当天不中退款订单数
//退款人数=当天不中退款人数

        //统计数据入库
        $dalFinancial = new DALFinancial($this->_appSetting);
        $dalFinancial->newData($statData);
    }

}