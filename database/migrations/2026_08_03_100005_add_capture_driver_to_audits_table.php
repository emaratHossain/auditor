<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // stub | playwright. Recorded so the report can say whether anything
            // ever actually opened the page — a report full of invented findings
            // about a real client URL, presented as fact, is the exact
            // dishonesty this product exists to avoid.
            $table->string('capture_driver')->nullable()->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('capture_driver');
        });
    }
};
