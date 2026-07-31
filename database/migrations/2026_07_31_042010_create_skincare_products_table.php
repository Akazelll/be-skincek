<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('skincare_products', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('concern_id')->constrained('skin_concerns')->cascadeOnDelete();
            $table->foreignId('skin_type_id')->nullable()->constrained('skin_types')->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->text('key_ingredients')->nullable();
            $table->text('usage_instruction');
            $table->text('warning')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skincare_products');
    }
};
