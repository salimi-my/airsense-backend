<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('aqi');
            $table->decimal('pm25', 8, 2)->nullable();
            $table->decimal('pm10', 8, 2)->nullable();
            $table->decimal('no2', 8, 2)->nullable();
            $table->decimal('o3', 8, 2)->nullable();
            $table->decimal('co', 8, 2)->nullable();
            $table->decimal('temperature', 5, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('wind_speed', 5, 2)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['station_id', 'fetched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
