<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_metrics', function (Blueprint $table) {
            // { "Features": 340 } — visitors clicking the same spot repeatedly
            // because nothing happens. Nullable on purpose: a null switches the
            // rage-click rule off entirely rather than reading as a zero.
            $table->json('rage_clicks')->nullable()->after('section_reach');
            $table->json('dead_clicks')->nullable()->after('rage_clicks');
        });
    }

    public function down(): void
    {
        Schema::table('page_metrics', function (Blueprint $table) {
            $table->dropColumn(['rage_clicks', 'dead_clicks']);
        });
    }
};
