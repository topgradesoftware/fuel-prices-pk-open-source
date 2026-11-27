<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('device_uuid')->unique();
            $table->string('device_name')->nullable();
            $table->string('platform', 32)->index();
            $table->string('app_version')->nullable();
            $table->string('os_version')->nullable();
            $table->string('firebase_token', 512)->nullable()->index();
            $table->string('locale', 16)->default('en');
            $table->string('timezone')->nullable();
            $table->boolean('alerts_enabled')->default(true);
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->string('device_secret')->nullable();
            $table->text('device_public_key')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->string('last_country', 8)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
