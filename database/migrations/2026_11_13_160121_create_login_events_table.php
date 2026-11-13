<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // logon | unlock | failed | remote
            $table->string('username')->nullable();
            $table->string('source_id')->nullable(); // bv. Windows event RecordId, voorkomt dubbele invoer
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['device_id', 'source_id']);
            $table->index(['device_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
