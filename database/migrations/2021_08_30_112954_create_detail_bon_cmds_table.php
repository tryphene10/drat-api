<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDetailBonCmdsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('detail_bon_cmds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bon_commande_id');
            $table->unsignedBigInteger('cooperative_id');
            $table->string('signature_detail_bon_commande')->nullable();
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('cooperative_id')->references('id')->on('cooperatives');
            $table->foreign('bon_commande_id')->references('id')->on('bon_commandes');
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
        Schema::dropIfExists('detail_bon_cmds');
    }
}
