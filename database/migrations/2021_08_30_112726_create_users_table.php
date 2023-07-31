<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('cooperative_id')->nullable();
            $table->unsignedBigInteger('surface_partage_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('surname')->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('quartier')->nullable();
            $table->string('ville')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('ref')->unique()->nullable();
            $table->string('alias')->unique()->nullable();
            $table->boolean('published')->default(1)->nullable();
            $table->string('confirmation_token')->nullable();
            $table->string('activation_code')->nullable();
            $table->dateTime('activation_date')->nullable();
            $table->dateTime('deactivation_date')->nullable();
            $table->rememberToken();
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('surface_partage_id')->references('id')->on('surface_partages');
            $table->foreign('cooperative_id')->references('id')->on('cooperatives');
            $table->foreign('role_id')->references('id')->on('roles');
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
        Schema::dropIfExists('users');
    }
}
