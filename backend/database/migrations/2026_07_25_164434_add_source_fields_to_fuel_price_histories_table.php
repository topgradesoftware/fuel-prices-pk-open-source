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
        Schema::table('fuel_price_histories', function (Blueprint $table) {
            $table->string('source')->nullable()->after('effective_at');
            $table->string('source_url')->nullable()->after('source');
            $table->string('notification_ref')->nullable()->after('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fuel_price_histories', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_url', 'notification_ref']);
        });
    }
};
