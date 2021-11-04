<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->string('name')->nullable();;
            $table->string('email')->unique();
            $table->string('phone', 16)->unique()->nullable();
            $table->string('pin_code', 16)->nullable();
            $table->tinyInteger('is_email_verified')->default('0');
            // $table->tinyInteger('is_phone_verified')->default('0');
            $table->timestamp('email_verified_at')->nullable();
            // $table->timestamp('phone_verified_at')->nullable();
            $table->string('password')->nullable();;
            $table->rememberToken();
            $table->timestamps();
            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
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
