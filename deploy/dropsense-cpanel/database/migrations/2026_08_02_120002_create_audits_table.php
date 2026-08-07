<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();

            // pending | running | completed | failed. No audit may sit on running
            // forever — every job has a timeout and the chain has a catch(). See #84.
            $table->string('status')->default('pending')->index();

            // What the progress bar says: capturing | analysing | correlating | scoring
            $table->string('stage')->nullable();

            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->json('category_scores')->nullable();

            // What one audit cost, so nobody has to guess. See #92.
            $table->decimal('token_cost', 8, 5)->default(0);
            $table->string('ai_model')->nullable();

            // A plain sentence for the user, never a stack trace.
            $table->text('error_message')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
