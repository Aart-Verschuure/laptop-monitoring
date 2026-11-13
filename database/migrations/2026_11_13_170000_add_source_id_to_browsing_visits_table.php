<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * source_id voorkomt dubbele bezoeken bij imports en synchronisatie
     * (browsergeschiedenis, Google Takeout, gesynchroniseerde apparaten).
     */
    public function up(): void
    {
        Schema::table('browsing_visits', function (Blueprint $table) {
            $table->string('source_id', 100)->nullable()->after('incognito');
            $table->unique(['device_id', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('browsing_visits', function (Blueprint $table) {
            $table->dropUnique(['device_id', 'source_id']);
            $table->dropColumn('source_id');
        });
    }
};
