<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateRemoveReorderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pickups', function (Blueprint $table) {
            $table->dropColumn('address_id');

            $table->json('address_info');
            $table->date('pickup_date')->nullable()->change();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropColumn('notes');
        });

        DB::statement("ALTER TABLE shipments CHANGE resource resource enum('WEB','API','PLUGIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'API' NOT NULL AFTER logs");
        DB::statement("ALTER TABLE transactions CHANGE resource resource enum('WEB','API','PLUGIN') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'API' NOT NULL AFTER created_by");
        DB::statement("ALTER TABLE pickups CHANGE address_info address_info json NOT NULL AFTER status");
        DB::statement("ALTER TABLE pickups CHANGE pickup_date pickup_date date NULL AFTER address_info");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('remove_reorder');
    }
}
