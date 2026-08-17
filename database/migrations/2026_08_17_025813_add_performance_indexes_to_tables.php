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
            $table->index(['verification_status', 'created_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });

        Schema::table('skincare_products', function (Blueprint $table) {
            $table->index(['is_active', 'concern_id', 'skin_type_id']);
        });

        Schema::table('skin_recommendations', function (Blueprint $table) {
            $table->index(['is_active', 'concern_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'created_at']);
        });

        Schema::table('prediction_histories', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->index(['model_type', 'model_id', 'collection_name', 'order_column']);
        });
    }

    public function down(): void
    {
        Schema::table('doctor_verifications', function (Blueprint $table) {
            $table->dropIndex(['verification_status', 'created_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('skincare_products', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'concern_id', 'skin_type_id']);
        });

        Schema::table('skin_recommendations', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'concern_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'created_at']);
        });

        Schema::table('prediction_histories', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });

        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['model_type', 'model_id', 'collection_name', 'order_column']);
        });
    }
};
