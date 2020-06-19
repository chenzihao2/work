<?php

namespace App\Admin\Controllers;

use App\resource;
use App\Sources;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use App\Clients;

class SourcesController extends Controller
{
    use ModelForm;

    /**
     * Index interface.
     *
     * @return Content
     */
    public function index()
    {
        return Admin::content(function (Content $content) {

            $content->header('header');
            $content->description('description');

            $content->body($this->grid());
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function edit($id)
    {
        return Admin::content(function (Content $content) use ($id) {

            $content->header('header');
            $content->description('description');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Create interface.
     *
     * @return Content
     */
    public function create()
    {
        return Admin::content(function (Content $content) {

            $content->header('header');
            $content->description('description');

            $content->body($this->form());
        });
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        return Admin::grid(Sources::class, function (Grid $grid) {
            $grid->disableBatchDeletion();
            $grid->disableCreation();
            $grid->model()->orderBy('id', 'desc');

            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('title', "标题");
                $filter->between('created_at', '创建时间')->datetime();
            });

            $grid->id('ID')->sortable();

            // 提现人昵称
            $grid->uid("用户昵称")->value(function($uid) {
                return Clients::find($uid)->nickname;
            });
            $grid->title('标题')->display(function($text) {
                return str_limit($text, 30, '...');
            });

            $grid->resources("资源详情")->value(function($sid) {
                $content = "";
                $sid = explode(",", $sid);
                $sources = resource::select("position", "type")->wherein("rid", $sid)->get();
                foreach($sources as $key => $value) {

                    if($value['type'] == 1) {
                        $content .= "文字：{$value['position']}<br>";
                    } else if ($value['type'] == 2) {
                        $content .= "图片：<img src='https://zy.qiudashi.com/{$value['position']}' width='100'><br>";
                    } else if ($value['type'] == 3) {
                        $content .= "<a target='_blank' href='https://zy.qiudashi.com/{$value['position']}'>语音</a><br>";
                    } else if ($value['type'] == 4) {
                        $content .= "<a target='_blank' href='https://zy.qiudashi.com/{$value['position']}'>视频</a><br>";
                    } else if ($value['type'] == 4) {
                        $content .= "<a target='_blank' href='https://zy.qiudashi.com/{$value['position']}'>文件</a><br>";
                    }

                }
                return $content;
            });

            $grid->price('价格');
            $grid->num('数量');
            $grid->sold_money('当前收益');
            $grid->created_at("创建时间");
            $grid->updated_at("修改时间");
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Sources::class, function (Form $form) {

            $form->display('id', 'ID');
            
//            $form->text('uid', '用户id');
    //        $form->text('text', '标题');
     //       $from->text('resources', '资源id');
            $form->currency('price', '价格');
            $form->text('num', '售卖数量');
//:            $from->radio('is_num')->options(['0' => '不限', '1' => '限制']);
            $form->display('created_at', 'Created At');
            $form->display('updated_at', 'Updated At');
        });
    }
}
