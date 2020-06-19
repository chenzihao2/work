<?php

namespace App\Admin\Controllers;

use App\Clients;
use App\orders;

use App\Sources;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;

class OrdersController extends Controller
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
        return Admin::grid(orders::class, function (Grid $grid) {

            // 禁用创建按钮  id 倒序显示
            $grid->disableBatchDeletion();
            $grid->disableCreation();
            $grid->model()->orderBy('id', 'desc');

            $grid->filter(function ($filter) {
                $filter->equal('orderNum', "订单号");
                $filter->between('created_at', '创建时间')->datetime();
            });

            $grid->id('ID')->sortable("desc");

            $grid->orderNum('订单号');

            $grid->buy_uid("购买人昵称")->value(function ($id) {
                return Clients::find($id)->nickname;
            });
            $grid->sell_id("收款人昵称")->value(function ($uid) {
                return Clients::find($uid)->nickname;
            });
            $grid->sid("资源名称")->value(function ($id) {
                return Sources::find($id)->title;
            });
            $grid->source_price("资源价格");
            $grid->pay_status('支付状态')->value(function ($s){
                if($s == 0) {
                    return "<span style='color: red;'>未支付</span>";
                } else {
                    return "<span style='color: blue;'>已支付</span>";
                }
            });

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
        return Admin::form(orders::class, function (Form $form) {

            $form->display('id', 'ID');

            $form->display('created_at', 'Created At');
            $form->display('updated_at', 'Updated At');
        });
    }
}
