<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBonLivraisonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bon_livraisons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_livreur_id')->nullable();
            $table->unsignedBigInteger('user_gsp_id');
            $table->unsignedBigInteger('statut_bon_livraison_id');
            $table->string('signature_bon_livraison')->nullable();
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('statut_bon_livraison_id')->references('id')->on('statut_bon_livraisons');
            $table->foreign('user_gsp_id')->references('id')->on('users');
            $table->foreign('user_livreur_id')->references('id')->on('users');
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
        Schema::dropIfExists('bon_livraisons');
    }
}
