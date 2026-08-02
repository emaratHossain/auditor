<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insight_id')->nullable()->constrained()->nullOnDelete();

            $table->string('section_name');

            // The five parts, always in this order, so people learn to read them fast.
            $table->string('title');               // 1. Problem
            $table->text('evidence');              // 2. Evidence — carries a real number
            $table->text('suggested_fix');         // 3. Suggested fix
            $table->string('expected_impact');     // 4. Expected impact — always a RANGE
            $table->string('priority');            // 5. high | medium | low

            $table->decimal('priority_score', 8, 4);
            $table->unsignedTinyInteger('effort');
            $table->unsignedTinyInteger('severity');
            $table->decimal('traffic_share', 5, 4);
            $table->decimal('confidence', 3, 2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
