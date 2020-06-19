<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSourcesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('uid')->comment('用户id');
            $table->string('title')->comment('标题');
            $table->string('resources',30)->comment('资源id（根据，进行分割）');
            $table->decimal('price')->comment('资源价格');
            $table->integer('num')->comment('售卖数量');
            $table->string('is_num',3)->comment('是否限制售卖数量（0不限，1限制）');
            $table->integer('Sold_num')->comment('已售出数量');
            $table->decimal('sold_money')->comment('该商品销售金额');
            $table->string('status',3)->comment('用户是否删除(0已删除，1未删除)');
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
        Schema::dropIfExists('sources');
    }
}
