<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProduitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('produits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_prod_id')->nullable();
            $table->unsignedBigInteger('user_coop_id')->nullable();
            $table->unsignedBigInteger('commande_id')->nullable();
            $table->unsignedBigInteger('detail_bon_cmd_id')->nullable();
            $table->unsignedBigInteger('categorie_id');
            $table->unsignedBigInteger('unite_id');
            $table->unsignedBigInteger('statut_produit_id')->nullable();
            $table->unsignedBigInteger('volume_id');
            $table->string('delai')->nullable();
            $table->string('designation');
            $table->string('description')->nullable();
            $table->string('prix_produit')->nullable();
            $table->string('qte');
            $table->string('type_vente');
            $table->string('prix_min_enchere')->nullable();
            $table->boolean('statut')->default(0);
            $table->timestamp('begin_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('volume_id')->references('id')->on('volumes');
            $table->foreign('statut_produit_id')->references('id')->on('statut_produits');
            $table->foreign('unite_id')->references('id')->on('unites');
            $table->foreign('categorie_id')->references('id')->on('categories');
            $table->foreign('detail_bon_cmd_id')->references('id')->on('detail_bon_cmds');
            $table->foreign('commande_id')->references('id')->on('commandes');
            $table->foreign('user_coop_id')->references('id')->on('users');
            $table->foreign('user_prod_id')->references('id')->on('users');
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
        Schema::dropIfExists('produits');
    }
}
