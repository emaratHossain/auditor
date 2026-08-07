<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenshot_sections', function (Blueprint $table) {
            // { headline: {...}, subhead: {...}, ctas: [...] } — the section's own
            // words, read during capture. An attribute of a section we already
            // record, so it needs no table of its own.
            $table->json('copy')->nullable()->after('screenshot_path');
        });
    }

    public function down(): void
    {
        Schema::table('screenshot_sections', function (Blueprint $table) {
            $table->dropColumn('copy');
        });
    }
};
