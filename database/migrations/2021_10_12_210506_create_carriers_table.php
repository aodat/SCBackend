<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarriersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('carriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('email', 64);
            $table->string('phone', 16);
            $table->double('balance', 8, 2)->default('0');;
            $table->enum('country_code', ['JO']);
            $table->enum('currency_code', ['JOD']);
            $table->json('documents')->nullable();
            $table->tinyInteger('is_email_verified')->default('0');
            $table->tinyInteger('is_phone_verified')->default('0');
            $table->tinyInteger('is_documents_verified')->default('0');
            $table->tinyInteger('is_active')->default('0');
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
        Schema::dropIfExists('carriers');
    }
}
