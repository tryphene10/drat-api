<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatutProduitsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('statut_produits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->unique();
            $table->boolean('published')->default(1);
            $table->string('ref');
            $table->string('alias');
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
        Schema::dropIfExists('statut_produits');
    }
}
