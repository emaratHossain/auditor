<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            // { performance, accessibility, best_practices, seo, worst_checks[] }
            // Nullable on purpose: a failed Lighthouse run must degrade the score
            // to a labelled estimate, never fail the audit.
            $table->json('lighthouse')->nullable()->after('category_scores');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('lighthouse');
        });
    }
};
