<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string("latitude");
            $table->string("longitude");
            $table->float("amount");
            $table->integer("order_stat_id");
            $table->integer("driver_id");
        });

        Schema::table('orders', function (Blueprint $table) {        
            $table->foreign('order_stat_id')->references('id')->on('order_statuses');
            $table->foreign('driver_id')->references('id')->on('drivers');
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('orders');
    }
};
