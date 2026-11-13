<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('browsing_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->string('domain')->nullable();
            $table->string('title')->nullable();
            $table->string('browser')->nullable();
            $table->boolean('incognito')->default(false);
            $table->timestamp('visited_at');
            $table->timestamps();

            $table->index(['device_id', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('browsing_visits');
    }
};
