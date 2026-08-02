<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();

            // Required. Without these there is no audit worth running.
            $table->unsignedInteger('visitors');
            $table->decimal('bounce_rate', 5, 2);
            $table->decimal('conversion_rate', 5, 2);

            // Optional on purpose. A null here switches off the rules that need it —
            // it must NEVER become a guessed number. That is the honesty rule.
            $table->decimal('cta_click_rate', 5, 2)->nullable();
            $table->decimal('mobile_share', 5, 2)->nullable();
            $table->decimal('mobile_bounce_rate', 5, 2)->nullable();

            // { "Hero": 92.0, "Pricing": 21.5 } — how far down people actually get.
            $table->json('section_reach')->nullable();

            // Where the numbers came from: manual | ga4 | clarity | csv
            $table->string('source')->default('manual');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_metrics');
    }
};
