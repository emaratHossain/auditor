<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();

            $table->string('section_name');
            $table->string('rule_key');            // seen_but_not_clicked, etc.

            // The plain sentence a human reads.
            $table->text('statement');

            // { metric, value, unit, comparison } — the evidence guarantee means a
            // row cannot exist here without all of metric, value and section_name.
            $table->json('evidence');

            $table->decimal('confidence', 3, 2);   // 0.00 – 1.00
            $table->unsignedTinyInteger('severity');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insights');
    }
};
