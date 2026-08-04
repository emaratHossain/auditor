<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many findings the model returned that could not be put on a captured
 * section.
 *
 * Those findings are dropped later by the evidence guarantee, quietly and
 * correctly — with no section there is no number for them to stand on. What hid
 * behind that silence was a model naming sections its own way, which emptied
 * whole reports without raising anything anywhere. A count makes it visible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedInteger('unmatched_findings')->default(0)->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('unmatched_findings');
        });
    }
};
