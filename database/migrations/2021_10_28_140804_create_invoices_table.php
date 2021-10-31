<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('fk_id')->nullable()->index();
            
            $table->string('customer_name', 64);
            $table->string('customer_email', 32);
            $table->text('description')->nullable();
            $table->double('amount', 6, 2)->default('0');

            $table->date('paid_at', 32)->nullable();
            $table->enum('status', ['DRAFT','PAID'])->default('DRAFT');
            $table->text('link')->nullable();
            $table->timestamps();

            $table->foreign('merchant_id')->references('id')->on('merchants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('invoices');
    }
}
