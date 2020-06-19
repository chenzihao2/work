<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProfileTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //用户简介表
        Schema::create('profile', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uid')->comment('用户id');
            $table->string('grade', 3)->comment('用户级别');
            $table->string('profit', 3)->comment('用户利率');
            $table->string('is_buy', 3)->comment('是否为买家');
            $table->string('is_sell', 3)->comment('是否为卖家');
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
