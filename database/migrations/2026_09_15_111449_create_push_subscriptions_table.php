<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('participant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('owner_key', 80);
            $table->string('endpoint_hash', 64);
            $table->text('subscription');
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedSmallInteger('last_error_code')->nullable();
            $table->timestamps();
            $table->unique(['owner_key', 'endpoint_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
