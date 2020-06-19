<?php
/**
 *分类相关接口
 * User: zyj
 * Date: 2019/09/2
 */

namespace QK\HaoLiao\Controllers\Console\Base;

use QK\HaoLiao\Controllers\Console\ConsoleController;
use QK\HaoLiao\Model\CategoryModel;
use QK\WSF\Settings\AppSetting;



class CategoryController extends ConsoleController{
    /**
     * f分类列表
     */
    public function categoryList(){
        $param = $this->checkApiParam(['type'], []);
        $platform = $param['type'];
        $categoryModel = new CategoryModel();
        $condition = " type = $platform and deleted=0";
        $list = $categoryModel->lists($condition, '', 'sort asc');
        !$list && $list = [];
        $this->responseJson($list);
    }
    /**
     * 添加分类
     */
    public function addCategory(){
        $param = $this->checkApiParam(['name','type'],['id'=>0,'image'=>'','sort'=>'']);

        $categoryModel = new CategoryModel();
        //创建
        if(!$param['id']){
			$r=$categoryModel->getNameCategoryInfo($param['name'],$param['type']);
			if($r){
				$this->responseJsonError(1000, '此分类已存在');
				return;
			}
            unset($param['id']);
            $res=$categoryModel->insert($param);
        }else{
            $res=$categoryModel->update($param['id'],$param);
        }
         $this->responseJson();
    }

    /*
     * 分类信息
     */
    public function categoryInfo(){
        $param = $this->checkApiParam(['id'],[]);
        if($param['id']){
            $categoryModel = new CategoryModel();
            $info=$categoryModel->getCategoryInfo($param['id']);
        }
        //dump($info);
        $this->responseJson($info);
    }


    /**
     * 删除分类
     */

    public function delCategory(){
        $param = $this->checkApiParam(['id'],[]);
        $categoryModel = new CategoryModel();
        $res=$categoryModel->del($param['id']);
        $this->responseJson();
    }



    // 分类排序
    public function sort() {
        $params = $this->checkApiParam(['id', 'sort_type', 'type']);
        $id = $params['id'];
        $sort_type = ($params['sort_type'] == 2) ? 2 : 1;
        $type = $params['type'];

        $categoryModel = new CategoryModel();
        $r = $categoryModel->sort($id, $sort_type, $type);

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




}
