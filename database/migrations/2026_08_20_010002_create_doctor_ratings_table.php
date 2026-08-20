<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('doctor_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('review')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'doctor_id']);
            $table->index(['doctor_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_ratings');
    }
};
