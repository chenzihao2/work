<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSourcesInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sources_info', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uid')->comment('用户id')->nullable();
            $table->string('type')->comment('资源类型（1，文字 2，语音 3，图片 4， 视频 5，文件）');
            $table->string('position')->comment('资源位置');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sources_info');
    }
}
