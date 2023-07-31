<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCommandesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_client_id');
            $table->unsignedBigInteger('bon_livraison_id')->nullable();
            $table->unsignedBigInteger('bon_commande_id')->nullable();
            //$table->unsignedBigInteger('quartier_id')->nullable();
            $table->unsignedBigInteger('statut_cmd_id');
            $table->unsignedBigInteger('type_livraison_id')->nullable();
            $table->unsignedBigInteger('statut_livraison_id')->nullable();
            $table->unsignedBigInteger('surface_partage_id')->nullable();
            $table->string('mode_payment')->nullable();
            $table->string('montant')->nullable();
            $table->string('paie_phone')->nullable();
            $table->string('transaction')->nullable();
            $table->text('image_qrcode')->nullable();
            $table->string('cni')->nullable();
            $table->string('signature')->nullable();
            $table->string('lieu_livraison')->nullable();
            $table->string('longitude')->nullable();
            $table->string('latitude')->nullable();
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('surface_partage_id')->references('id')->on('surface_partages');
            $table->foreign('statut_livraison_id')->references('id')->on('statut_livraisons');
            $table->foreign('type_livraison_id')->references('id')->on('type_livraisons');
            $table->foreign('statut_cmd_id')->references('id')->on('statut_cmds');
            //$table->foreign('quartier_id')->references('id')->on('quartiers');
            $table->foreign('bon_commande_id')->references('id')->on('bon_commandes');
            $table->foreign('bon_livraison_id')->references('id')->on('bon_livraisons');
            $table->foreign('user_client_id')->references('id')->on('users');
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
        Schema::dropIfExists('commandes');
    }
}
