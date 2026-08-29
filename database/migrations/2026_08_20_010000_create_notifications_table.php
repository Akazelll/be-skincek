<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);        // success|warning|error|info
            $table->string('category', 50);     // welcome|scan_complete|chat_message|etc
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('action_url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'idx_user_read');
            $table->index(['user_id', 'created_at'], 'idx_user_created');
            $table->index('created_at', 'idx_cleanup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
