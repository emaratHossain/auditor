<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screenshot_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();

            $table->string('section_name');
            $table->string('viewport');            // desktop | mobile
            $table->string('screenshot_path');

            // How far down the page this section starts, and how tall it is.
            // DropOffBeforeSection is built entirely on these two numbers, so a
            // wrong value here produces a confidently wrong insight. See #89.
            $table->unsignedInteger('position');
            $table->unsignedInteger('height');
            $table->unsignedInteger('page_height');

            // Ordering as the visitor meets them, top to bottom.
            $table->unsignedTinyInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screenshot_sections');
    }
};
