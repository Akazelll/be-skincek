<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });

        Schema::table('doctor_verifications', function (Blueprint $table) {
            $table->unique('doctor_id');
        });

        Schema::table('skin_types', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('skin_types', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('doctor_verifications', function (Blueprint $table) {
            $table->dropUnique(['doctor_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->renameColumn('full_name', 'name');
        });
    }
};
