<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->float('cpu_percent')->nullable();
            $table->unsignedInteger('memory_used_mb')->nullable();
            $table->unsignedInteger('memory_total_mb')->nullable();
            $table->float('disk_used_gb')->nullable();
            $table->float('disk_total_gb')->nullable();
            $table->unsignedTinyInteger('battery_percent')->nullable();
            $table->boolean('charging')->nullable();
            $table->float('battery_temperature')->nullable();
            $table->timestamp('booted_at')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['device_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
