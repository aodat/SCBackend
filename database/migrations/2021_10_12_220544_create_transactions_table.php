<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['CASHIN','CASHOUT']);
            $table->integer('fk_id')->nullable();
            $table->double('amount', 8, 2)->default('0');
            $table->double('balance_after', 8, 2)->default('0');
            $table->string('description', 256)->nullable();
            $table->string('notes', 256)->nullable();
            $table->string('attachments', 256)->nullable();
            $table->enum('status', ['PROCESSING','COMPLETED','REJECTED'])->default('PROCESSING');
            $table->enum('source',['SHIPMENT','CREDITCARD','INVOICE','ORDER']);
            $table->json('payment_method')->nullable();
            $table->unsignedBigInteger('created_by');
            
            $table->timestamps();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');;
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
