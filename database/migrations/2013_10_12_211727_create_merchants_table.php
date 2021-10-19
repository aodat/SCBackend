<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('email', 64);
            $table->string('phone', 16);
            $table->double('actual_balance', 8, 2)->default('0');;
            $table->double('available_balance', 8, 2)->default('0');;
            $table->enum('country_code', ['JOR']);
            $table->enum('currency_code', ['JOD']);
            $table->json('documents')->nullable();
            $table->json('addresses')->nullable();
            $table->json('payment_methods')->nullable();
            $table->json('dom_rates')->nullable();
            $table->json('exp_rates')->nullable();
            $table->json('senders')->nullable();
            $table->enum('type', ['individual','corporate']);
            $table->tinyInteger('is_email_verified')->default('0');
            $table->tinyInteger('is_phone_verified')->default('0');
            $table->tinyInteger('is_documents_verified')->default('0');
            $table->tinyInteger('is_active')->default('0');
            $table->tinyInteger('is_instant_payment_active')->default('0');
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
        Schema::dropIfExists('merchants');
    }
}
