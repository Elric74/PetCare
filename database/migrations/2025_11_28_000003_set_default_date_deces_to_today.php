<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Note: Use raw SQL to avoid requiring the doctrine/dbal package for column changes.
     * This sets the default for the `date_deces` column to the current date.
     *
     * @return void
     */
    public function up()
    {
        // MySQL syntax to set default to CURRENT_DATE for a DATE column
        DB::statement("ALTER TABLE `pets` MODIFY `date_deces` DATE DEFAULT (CURRENT_DATE)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `pets` MODIFY `date_deces` DATE DEFAULT NULL");
    }
};
