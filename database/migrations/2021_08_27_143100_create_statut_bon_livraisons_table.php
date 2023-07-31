<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateStatutBonLivraisonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('statut_bon_livraisons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->boolean('published');
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
        Schema::dropIfExists('statut_bon_livraisons');
    }
}
