<?php
/**
 * banner相关接口
 * User: twenj
 * Date: 2019/03/04
 */

namespace QK\HaoLiao\Controllers\User\V2;

use QK\HaoLiao\Controllers\User\Base\BannerController as Banner;
use QK\HaoLiao\Model\BannerModel;

class BannerController extends Banner {
    /**
     * 获取 banner 图
     */
  public function lists() {
    $param = $this->checkApiParam([], ['platform' => 1,'source'=>0]);
    $platform = $param['platform'];
	$source = $param['source'];
    $bannerModel = new BannerModel();
    $condition = " deleted = 0 AND source=$source";
    if($platform > 0) {
      $condition .= " AND `platform` in (0, $platform)";
    } else {
       $condition .= " AND `platform` = $platform";
    }
        $list = $bannerModel->lists($condition, 'id, platform, title, image, type, oid', 'sort asc');
        !$list && $list = [];
        $this->responseJson($list);
    }
}
