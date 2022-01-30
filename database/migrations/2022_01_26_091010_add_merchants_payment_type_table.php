<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMerchantsPaymentTypeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->enum('payment_type',['POSTPAID','PREPAID'])->default('PREPAID')->after('cod_balance');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchants', function (Blueprint $table) {
            //
        });
    }
}
