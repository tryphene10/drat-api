<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBonCommandesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bon_commandes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_gsp_id');
            $table->unsignedBigInteger('user_coursier_id')->nullable();
            $table->string('signature_bon_cmd')->nullable();
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('user_coursier_id')->references('id')->on('users');
            $table->foreign('user_gsp_id')->references('id')->on('users');
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
        Schema::dropIfExists('bon_commandes');
    }
}
