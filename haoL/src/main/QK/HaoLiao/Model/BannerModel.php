<?php
/**
 * User: twenj
 * Date: 2019/03/04
 * Time: 10:42
 */

namespace QK\HaoLiao\Model;

use \QK\HaoLiao\DAL\DALBanner;
use QK\HaoLiao\Common\StringHandler;

class BannerModel extends BaseModel {

    private $_dalBanner;
    private $_otherRedisKeyManageModel;

    public function __construct() {
        parent::__construct();
        $this->_dalBanner = new DALBanner($this->_appSetting);
        $this->_otherRedisKeyManageModel = new RedisKeyManageModel('other');
    }

    public function lists($condition = '', $fields = '', $order = '') {
        //$redisModel = new RedisModel('other');
        //$list = $redisModel->redisGet(OTHER_BANNER_LIST, true);
        //if (!$list) {
            $list = $this->_dalBanner->listsBanner($condition, $fields, $order);
            //$redisModel->redisSet(OTHER_BANNER_LIST, $list);
        //}
        return $list;
    }

    public function insert($params) {
        $insertData['title'] = StringHandler::newInstance()->stringExecute($params['title']);
        $insertData['image'] = StringHandler::newInstance()->stringExecute($params['image']);
        $insertData['type'] = intval($params['type']);
        $insertData['platform'] = intval($params['platform']);
        $insertData['source'] = intval($params['source']);
        $insertData['oid'] = StringHandler::newInstance()->stringExecute($params['oid']);
        $insertData['sort'] = $this->_dalBanner->getMaxSort();
        $nowtime = time();
        $insertData['create_time'] = $nowtime;
        $insertData['modify_time'] = $nowtime;
        // 删除缓存
        $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_dalBanner->newBanner($insertData);
    }

    public function update($id, $params) {
        $id = intval($id);
        $upData['title'] = StringHandler::newInstance()->stringExecute($params['title']);
        $upData['image'] = StringHandler::newInstance()->stringExecute($params['image']);
        $upData['type'] = intval($params['type']);
        $upData['platform'] = intval($params['platform']);
        $upData['source'] = intval($params['source']);
        $upData['oid'] = StringHandler::newInstance()->stringExecute($params['oid']);
        $upData['modify_time'] = time();
        // 删除缓存
        $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_dalBanner->updateBanner($id, $upData);
    }

    public function del($id) {
        $upData['deleted'] = 1;
        $upData['modify_time'] = time();
        // 删除缓存
        $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_dalBanner->updateBanner($id, $upData);
    }

    public function sort($id, $sort_type, $platform = 1) {
        $id = intval($id);

        $sort = $this->_dalBanner->bannerInfo('deleted = 0 AND id = ' . $id, 'id, sort');
        if (empty($sort)) {
            return false;
        }
        $condition = 'deleted = 0 AND platform = '.$platform;
        if ($sort_type == 1) {  // 上移
            $condition .= ' AND sort < ' . $sort['sort'];
            $order = 'sort desc';
        } else {  // 下移
            $condition .= ' AND sort > ' . $sort['sort'];
            $order = 'sort asc';
        }
        $other = $this->_dalBanner->listsBanner($condition, 'id, sort', $order, 0, 1);
        if (!$other) {
            return false;
        }
        $nowtime = time();
        $this->_dalBanner->updateBanner($sort['id'], ['sort' => $other[0]['sort'], 'modify_time' => $nowtime]);
        $this->_dalBanner->updateBanner($other[0]['id'], ['sort' => $sort['sort'], 'modify_time' => $nowtime]);
        // 删除缓存
        $this->_otherRedisKeyManageModel->delBannerList();
        return true;
    }

}
