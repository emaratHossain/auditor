<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rewrites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();

            $table->string('section_name');

            // headline | subhead | cta
            $table->string('element');

            $table->text('original');

            // [{ text, reason }, ...] — at most three
            $table->json('variants');

            $table->string('model')->nullable();
            $table->unsignedInteger('tokens')->default(0);

            $table->timestamps();

            // One stored rewrite per element, so the second click is free and the
            // seeded demo page can ship with its rewrites already in place.
            $table->unique(['audit_id', 'section_name', 'element']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rewrites');
    }
};
