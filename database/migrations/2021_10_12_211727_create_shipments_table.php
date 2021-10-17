<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShipmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()  
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->enum('carrier', ['ARAMEX','DHL']);
            $table->integer('internal_awb');
            $table->integer('external_awb')->nullable();

            $table->string('reference1', 128)->nullable();
            $table->string('reference2', 128)->nullable();
            $table->string('reference3', 128)->nullable();
            $table->string('reference4', 128)->nullable();

            $table->string('sender_name', 64);
            $table->string('sender_email', 64);
            $table->string('sender_phone', 16);
            $table->string('sender_country', 3);
            $table->string('sender_city', 32);
            $table->string('sender_area', 32);
            $table->string('sender_address_description', 128);

            $table->string('consignee_name', 64);
            $table->string('consignee_email', 32);
            $table->string('consignee_phone', 16);
            $table->string('consignee_country', 3);
            $table->string('consignee_city', 32);
            $table->string('consignee_area', 32);
            $table->string('consignee_address_description', 128);

            $table->integer('pieces')->default('1');
            $table->string('content', 64);
            $table->double('actual_weight', 3, 2)->default('0');
            $table->double('chargable_weight', 3, 2)->default('0');
            $table->string('notes', 128)->nullable();
            $table->double('cod', 8, 2)->default('0');
            $table->double('fees', 8, 2)->default('0');
            $table->double('extra_fees', 8, 2)->default('0');
            $table->double('cash_collected', 8, 2)->default('0');
            $table->set('extra_services', ['DOMCOD'])->nullable();
            $table->double('extra_services_fees', 8, 2)->default('0');
            $table->enum('group', ['EXP','DOM']);
            $table->enum('status', ['DRAFT','PROCESSING','COMPLETED'])->default('DRAFT');

            $table->date('delivered_at', 32)->nullable();
            $table->date('returned_at', 32)->nullable();
            $table->date('paid_at', 32)->nullable();
            $table->integer('transaction_id')->unsigned()->nullable();

            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('merchant_id');
            $table->unsignedBigInteger('carrier_id');

            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign('carrier_id')->references('id')->on('carriers');

            $table->json('logs')->nullable();
            $table->softDeletes();
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
        Schema::dropIfExists('shipments');
    }
}
