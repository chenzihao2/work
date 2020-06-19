<?php

namespace App\Admin\Controllers;

use App\Extract;

use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Layout\Content;
use App\Http\Controllers\Controller;
use Encore\Admin\Controllers\ModelForm;
use App\Clients;

class ExtractController extends Controller
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

            $content->header('提现管理');
            $content->description('服务费为总金额的百分之五');

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

            $content->header('提现管理');
            $content->description('提现');

            $content->body($this->form()->edit($id));
        });
    }

    /**
     * Edit interface.
     *
     * @param $id
     * @return Content
     */
    public function status()
    {
        $id = request("id", "");
        $extract = Extract::where("id", $id)->first();
        if($extract['status'] != 2) {
            Extract::where("id", $id)->update(['status' => '2']);
            Clients::where("id", $extract['uid'])->decrement('balance', $extract['font_balance']);
        }
        echo "<script>alert('操作成功'); window.location.href='https://glm9.qiudashi.com/admin/extract';</script>";
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
        return Admin::grid(Extract::class, function (Grid $grid) {

            // 禁用创建按钮  id 倒序显示
            $grid->disableBatchDeletion();
            $grid->disableCreation();
            $grid->model()->orderBy('id', 'desc');

            $grid->filter(function ($filter) {
                $filter->equal('code', "提现码");
                $filter->between('created_at', '创建时间')->datetime();
            });

//            $grid->id('ID')->sortable("desc");

            // 提现人昵称
            $grid->uid("提现人昵称")->value(function ($uid) {
                return Clients::find($uid)->nickname;
            });

            $grid->code("提现码");
            $grid->font_balance("提现金额");
            $grid->server_balance("服务费");
            $grid->in_balance("应打款");

            $grid->status('支付状态')->value(function ($s){
                if($s == 2) {
                    return "<span style='color: blue;'>打款完成</span>";
                } else {
                    return "<span style='color: red;'>请联系打款</span>";
                }
            });

//            $grid->status("提现状态")->select([0 => '未审核', 1 => '已审核', 2 => '打款完成']);

            $grid->column('id', '提现状态')->value(function ($s){
                return "<a href='extract/status?id={$s}'>修改提现状态</a>";
            })->sortable("desc");

            $grid->created_at("创建时间");
            $grid->updated_at("修改时间");


//            $grid->rows(function ($row) {
                // 未审核为红色  已审核为蓝色 审核完成为绿色
                // if($row->status == 0) {
                //     $row->style("color:red");
                // } else if ($row->status == 1) {
                //     $row->style("color:blue");
                // } else if ($row->status == 2) {
                //     $row->style("color:green");
                // }
//            });
        });
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    protected function form()
    {
        return Admin::form(Extract::class, function (Form $form) {
            $form->display('id', 'ID');
            $form->select('status', "提现状态")->options([0 => '未审核', 1 => '已审核', 2 => '打款完成']);
            $form->display('created_at', 'Created At');
            $form->display('updated_at', 'Updated At');
        });
    }
}
