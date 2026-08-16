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
        Schema::table('doctor_verifications', function (Blueprint $table) {
            $table->string('title')->nullable()->after('str_number');
            $table->string('sub_specialization')->nullable()->after('specialization');
            $table->unsignedSmallInteger('experience_years')->nullable()->after('sub_specialization');
            $table->string('alma_mater')->nullable()->after('experience_years');
            $table->json('practice_locations')->nullable()->after('alma_mater');
            $table->json('professional_organizations')->nullable()->after('practice_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doctor_verifications', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'sub_specialization',
                'experience_years',
                'alma_mater',
                'practice_locations',
                'professional_organizations',
            ]);
        });
    }
};
