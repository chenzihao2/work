<?php
/**
 * 数据抓取爬虫操作
 * User: WangHui
 * Date: 2018/10/31
 * Time: 下午2:53
 */

namespace QK\HaoLiao\Model;


use QK\HaoLiao\Common\PinYin;
use QK\HaoLiao\DAL\DALMatchLeague;
use QK\HaoLiao\DAL\DALMatchSchedule;

class CrawlerModel extends BaseModel {

    protected $_redisMatchModel;

    public function __construct() {
        parent::__construct();
        $this->_redisMatchModel = new RedisModel("match");
    }

    /**
     * 新联赛入库
     * @param $name
     * @param $url
     * @param $type
     * @return mixed
     */
    public function newLeague($name, $url, $type) {
        $dalMatchLeague = new DALMatchLeague($this->_appSetting);
        $info = $dalMatchLeague->getLeagueInfo($name, $type);
        if (!$info) {
            $pinYin = new PinYin();
            $params['match_type'] = $type;
            $params['league_name'] = $name;
            $params['crawler_name'] = $name;
            $params['crawler_url'] = $url;
            $params['initial'] = $pinYin->getFirstChar($name);
            $params['league_status'] = 1;
            $dalMatchLeague->newMatchLeague($params);
            return $dalMatchLeague->getInsertId();
        } else {
            return $info['league_id'];
        }
    }


    /**
     * 赛事信息入库
     * @param $params
     */
    public function scheduleToDB($params) {
        $dalMatchSchedule = new DALMatchSchedule($this->_appSetting);
        //检查是否存在
        $scheduleId = $dalMatchSchedule->checkSchedule($params['master_team'], $params['guest_team'], $params['schedule_time']);
        if (!$scheduleId) {
            $dalMatchSchedule->newMatchSchedule($params);
        } else {
            $dalMatchSchedule->updateMatchSchedule($scheduleId, $params);

            $this->_redisMatchModel->redisDel(MATCH_SCHEDULE_INFO . $scheduleId);
        }
    }

}