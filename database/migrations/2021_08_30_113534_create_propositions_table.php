<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePropositionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('propositions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_client_id');
            $table->unsignedBigInteger('produit_id');
            $table->unsignedBigInteger('statut_proposition_id');
            $table->integer('prix_proposition');
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
            $table->foreign('statut_proposition_id')->references('id')->on('statut_propositions');
            $table->foreign('produit_id')->references('id')->on('produits');
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
        Schema::dropIfExists('propositions');
    }
}
