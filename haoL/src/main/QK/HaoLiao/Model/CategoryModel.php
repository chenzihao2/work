<?php
/**
 * User: zyj
 * Date: 2019/09/02
 * Time: 11:42
 */

namespace QK\HaoLiao\Model;

use \QK\HaoLiao\DAL\DALCategory;
use QK\HaoLiao\Common\StringHandler;

class CategoryModel extends BaseModel {

    private $_Category;
    private $_otherRedisKeyManageModel;

    public function __construct() {
        parent::__construct();
        $this->_Category = new DALCategory($this->_appSetting);
//        $this->_otherRedisKeyManageModel = new RedisKeyManageModel('other');
    }
    //获取分类列表
    public function lists($condition = '', $fields = '', $order = '') {
        //$redisModel = new RedisModel('other');
        //$list = $redisModel->redisGet(OTHER_BANNER_LIST, true);
        //if (!$list) {
            $list = $this->_Category->listsCategory($condition, $fields, $order);
            //$redisModel->redisSet(OTHER_BANNER_LIST, $list);
        //}
        return $list;
    }
    //添加
    public function insert($params) {

        $insertData['name'] = StringHandler::newInstance()->stringExecute($params['name']);
        $insertData['img'] = StringHandler::newInstance()->stringExecute($params['image']);
        $insertData['type'] = intval($params['type']);
        $insertData['sort'] = $this->_Category->getMaxSort();
        $nowtime =time();
        $insertData['ctime'] =$nowtime;
        $insertData['modify_time'] = $nowtime;

        // 删除缓存
       // $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_Category->categoryInsert($insertData);
    }
    //修改分类
    public function update($id, $params) {

        $id = intval($id);
        $data['name'] = StringHandler::newInstance()->stringExecute($params['name']);
        $data['img'] = StringHandler::newInstance()->stringExecute($params['image']);
        $data['type'] = intval($params['type']);
        $data['sort'] = $this->_Category->getMaxSort();
        $nowtime =time();
        $data['modify_time'] = $nowtime;

        // 删除缓存
        //$this->_otherRedisKeyManageModel->delBannerList();
        return $this->_Category->updateCategory($id, $data);
    }
    //删除分类
    public function del($id) {
        $upData['deleted'] = 1;
        $upData['modify_time'] = time();
        // 删除缓存
       // $this->_otherRedisKeyManageModel->delBannerList();
        return $this->_Category->updateCategory($id, $upData);
    }


    /**
     * 获取分类信息
     * @param $d
     */
    public function getCategoryInfo($id) {
        $CategoryInfo = $this->_Category->getCategoryInfo($id);
        return $CategoryInfo;
    }


/**
     * 根据名称获取分类信息
     * @param $d
     */
    public function getNameCategoryInfo($name,$type) {
        $CategoryInfo = $this->_Category->getNameCategoryInfo($name,$type);
        return $CategoryInfo;
    }



    //排序
    public function sort($id, $sort_type, $type = 1) {
                $id = intval($id);
                $sort = $this->_Category->CategoryInfo('deleted = 0 AND id = ' . $id, 'id, sort');

                if (empty($sort)) {
                    return false;
                }
                $condition = 'deleted = 0 AND type = '.$type;
                if ($sort_type == 1) {  // 上移
                    $condition .= ' AND sort < ' . $sort['sort'];
                    $order = 'sort desc';
                } else {  // 下移
                    $condition .= ' AND sort > ' . $sort['sort'];
                    $order = 'sort asc';
                }

                $other = $this->_Category->listsCategory($condition, 'id, sort', $order, 0, 1);

                if (!$other) {
                    return false;
                }
                $nowtime = time();
                //两个分类排序互换
                $this->_Category->updateCategory($sort['id'], ['sort' => $other[0]['sort'], 'modify_time' => $nowtime]);
                $this->_Category->updateCategory($other[0]['id'], ['sort' => $sort['sort'], 'modify_time' => $nowtime]);
                // 删除缓存
                //$this->_otherRedisKeyManageModel->delBannerList();
                return true;
    }

    public function getNewsCategory() {
        $condition['type'] = 1;
        $fields = ['id', 'name'];
        $data = $this->_Category->getCategory($condition, $fields);
        foreach ($data as $k => $v) {
            $data[$k]['title'] = $v['name'];
        }
        return $data;
    }

    public function getVideoCategory() {
        $condition['type'] = 2;
        $fields = ['id', 'name', 'img'];
        return $this->_Category->getCategory($condition, $fields);
    }

}
