<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screenshot_section_id')->nullable()->constrained()->nullOnDelete();

            $table->string('section_name');
            $table->unsignedTinyInteger('ai_score');

            // [{ what, why, fix, severity, category }] — F07 composes the
            // user-facing recommendation out of these fields. No second AI call.
            $table->json('problems');

            // Kept so a surprising result can be traced back to what was actually said.
            $table->json('raw_response')->nullable();

            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_findings');
    }
};
