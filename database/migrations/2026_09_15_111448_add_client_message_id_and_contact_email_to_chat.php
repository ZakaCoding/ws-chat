<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->uuid('client_message_id')->nullable();
            $table->unique(['participant_id', 'client_message_id']);
        });
        Schema::table('conversations', function (Blueprint $table): void {
            $table->text('contact_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique(['participant_id', 'client_message_id']);
            $table->dropColumn('client_message_id');
        });
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('contact_email');
        });
    }
};
