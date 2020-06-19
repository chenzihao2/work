<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSmsLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 短信记录表
        Schema::create('sms_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('uid')->comment("用户标示");
            $table->string("mobile", 13)->comment("手机号");
            $table->text("content")->comment("短信内容");
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
        //
    }
}
