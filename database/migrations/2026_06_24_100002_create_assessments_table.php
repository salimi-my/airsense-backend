<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('age_group');
            $table->json('conditions');
            $table->string('activity');
            $table->string('risk_level');
            $table->text('advice');
            $table->json('precautions')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->boolean('used_fallback')->default(false);
            $table->timestamp('assessed_at');
            $table->timestamps();

            $table->index('assessed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
