<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('personal_access_tokens')->whereNull('uuid')->orderBy('id')->eachById(function ($token) {
            DB::table('personal_access_tokens')->where('id', $token->id)->update(['uuid' => Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
