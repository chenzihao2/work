<?php

namespace App\Admin\Controllers;

use App\Clients;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class ClientsController extends Controller
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

            $content->header('用户管理');
            $content->description('用户展示');

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
//        echo '禁止修改';die;
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
        return Admin::grid(Clients::class, function (Grid $grid) {
            $grid->disableBatchDeletion();
            $grid->disableCreation();
            $grid->model()->orderBy('id', 'desc');
            $grid->filter(function ($filter) {
                $filter->disableIdFilter();
                $filter->like('nickname', "昵称");
                $filter->between('created_at', '创建时间')->datetime();
            });

            // 禁止创建
            $grid->disableCreation();

            $grid->id('ID')->sortable();

            $grid->nickname("用户昵称");
            $grid->sex('性别')->select([0 => '妖', 1 => '男', 2 => '女']);
            $grid->city("城市");
            $grid->province("省份");
            $grid->avatarurl("头像")->image(50,50);
            $grid->balance("余额");
            $grid->created_at('创建时间');
            $grid->updated_at('修改时间');
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Clients::class, function (Form $form) {

            $form->display('id', 'ID');

            $form->text("openid", "微信标识");
            $form->text("nickname", "昵称");
            $form->select('sex', "性别")->options(['0'=>'妖', '1' => '男', '2' => '女']);
            $form->text("city", "城市");
            $form->text("province", "省份");
            $form->text("avatarurl", "头像");
            $form->text("balance", "余额");
            $form->datetime('created_at', 'Created At');
            $form->datetime('updated_at', 'Updated At');
        });
    }
}
