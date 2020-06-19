<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateExtractTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
//        Schema::create('extract', function (Blueprint $table) {
//            $table->increments('id');
//            $table->integer('uid')->comment('用户id');
//            $table->integer('code')->comment('提现码');
//            $table->decimal('font_balance')->comment('当前用户余额');
//            $table->decimal('in_balance')->comment('提取金额');
//            $table->decimal('server_balance')->comment('服务费');
//            $table->integer('status')->comment('提现状态（0 提交 1 审核中 2 已打款）');
//            $table->timestamps();
//        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
