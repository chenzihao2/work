<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOrderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('orderNum')->unique();
            $table->string('buy_uid')->comment('购买者用户id');
            $table->string('sell_id')->comment('出售用户id');
            $table->string('sid')->comment('资源id');
            $table->string('sell_balance')->comment('出售用户当前余额');
            $table->string('pay_status')->comment('是否支付(0未支付，1已支付)');
            $table->string('source_price')->comment('资源价格');
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
