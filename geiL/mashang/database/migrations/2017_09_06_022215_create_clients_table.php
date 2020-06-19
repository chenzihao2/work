<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateClientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->increments('id');
            $table->string('openid')->comment('微信用户唯一标识');
            $table->string('nickname',50)->comment('用户昵称');
            $table->string('sex',3)->comment('性别（0人妖，1，男 2， 女）');
            $table->string('city',50)->comment('城市')->nullable();
            $table->string('province',50)->comment('省份')->nullable();
            $table->string('country',50)->comment('国家')->nullable();
            $table->string('avatarurl',100)->comment('用户头像')->nullable();
            $table->decimal('balance',10,2)->comment('用户余额');
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
