<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyPathsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->string('mapAreaId', 10)->change();
            $table->string('mapId', 10)->change();
        });
    }

    /**
     * Reverse the migrations.
 *
     * @return void
     */
    public function down()
    {
        Schema::table('paths', function (Blueprint $table) {
            $table->integer('mapAreaId')->change();
            $table->integer('mapId')->change();
        });
    }
}