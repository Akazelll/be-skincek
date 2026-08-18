<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skincare_products', function (Blueprint $table) {
            $table->string('gender', 20)->nullable()->default('unisex')->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('skincare_products', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};
