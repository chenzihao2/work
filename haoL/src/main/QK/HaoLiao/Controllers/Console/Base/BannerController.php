<?php
/**
 * banner 管理
 * User: twenj
 * Date: 2019/03/04
 * Time: 上午10:17
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\BannerModel;
use QK\HaoLiao\Model\ExpertModel;
use QK\HaoLiao\Model\ResourceModel;
use QK\HaoLiao\Model\NewsModel;

class BannerController extends ConsoleController {

    // banner 列表
  public function lists() {
    $param = $this->checkApiParam([], ['platform' => -1,'source'=>0]);
    $platform = $param['platform'];
    $source = $param['source'];
    $bannerModel = new BannerModel();
    $condition = " deleted = 0 AND source=$source";
    if($platform > 0) {
      $condition .= " AND `platform` in (0, $platform)";
    } else if($platform == 0) {
       $condition .= " AND `platform` = $platform";
    }
        $list = $bannerModel->lists($condition, 'id, platform, title, image, type, oid', 'sort asc');
        !$list && $list = [];
        $this->responseJson($list);
    }

    // 新增编辑banner
    public function edit() {
        $params = $this->checkApiParam(['title', 'image', 'type', 'oid'], ['id' => '', 'platform' => 0,'source'=>0]);

        $bannerModel = new BannerModel();

        if (empty($params['id'])) {
            $option = '添加';
            $r = $bannerModel->insert($params);
        } else {
            $option = '编辑';
            $r = $bannerModel->update($params['id'], $params);
        }

        if ($r) {
            $this->responseJson([], $option . '成功');
        } else {
            $this->responseJsonError(1000, $option . '失败');
        }
    }

    // 删除 banner
    public function del() {
        $params = $this->checkApiParam(['id']);
        $id = $params['id'];
        $bannerModel = new BannerModel();
        $r = $bannerModel->del($id);
        if ($r) {
            $this->responseJson();
        } else {
            $this->responseJsonError(1000, '删除失败');
        }
    }

    // 排序 banner
    public function sort() {
        $params = $this->checkApiParam(['id', 'sort_type', 'platform']);
        $id = $params['id'];
        $sort_type = ($params['sort_type'] == 2) ? 2 : 1;
        $platform = $params['platform'];

        $bannerModel = new BannerModel();
        $r = $bannerModel->sort($id, $sort_type, $platform);

        if ($r) {
            $this->responseJson();
        } else {
            if ($sort_type == 1) { // 上移
                $msg = '已位于首位';
            } else { // 下移
                $msg = '已位于末位';
            }
            $this->responseJsonError(1000, $msg);
        }
    }

    //banner的跳转列表选择
    public function distList() {
      $params = $this->checkApiParam(['type'], []);
      $type = $params['type'];

      $result = array();
      if($type == 1) {
        //专家
        $expertModel = new ExpertModel();
        $condition = array('expert_status' => 1);
        $orderBy = array('create_time' => 'desc');
        $expert_result = $expertModel->newExpertListV2($condition, ['expert_id', 'expert_name', 'platform'], 0, 0, $orderBy);
        foreach($expert_result as $value) {
          $result[] = array(
            'id' => $value['expert_id'],
            'title' => $value['expert_name'],
            'platform' => $value['platform']
          );
        }
      } else if($type == 2) {
        //方案
        $resourceModel = new ResourceModel();
        $condition = array('resource_status' => 1, 'create_time' => ['>=', strtotime('-2 day')]);
        $orderBy = array('release_time' => 'desc');
        $resource_result = $resourceModel->getResourceListV2($condition, ['resource_id', 'title', 'wx_display', 'bd_display'], 0, 0, $orderBy);
        foreach($resource_result as $value) {
          $platform = 0;
          if($value['bd_display'] && !$value['wx_display'])     $platform = 1;
          if($value['wx_display'] && !$value['bd_display'])     $platform = 2;
          $result[] = array(
            'id' => $value['resource_id'],
            'title' => $value['title'],
            'platform' => $platform
          );
        }
      } else if($type == 3) {
        //资讯
        $newsModel = new NewsModel();
        $condition = array('status' => 1, 'create_time' => ['>=', strtotime('-2 day')]);
        $orderBy = ['create_time' => 'DESC'];
        $news_result = $newsModel->getNewsListV2($condition, array(), 0, 0, $orderBy);
        foreach($news_result as $value) {
          $result[] = array(
            'id' => $value['nid'],
            'title' => $value['title'],
            'url' => $value['url'],
            'target' => $value['target'],
            'create_time' => $value['create_time'],
            'views' => $value['views'],
            'platform' => 0
          );
        }
      }
      $this->responseJson($result);
    }
}
