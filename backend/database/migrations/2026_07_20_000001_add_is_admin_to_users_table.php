<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('is_guest');
        });

        // Preserve existing access: password-provider accounts were the only
        // way admins were ever created, so promote them so the current admin
        // isn't locked out after this migration (deploys migrate but don't seed).
        DB::table('users')
            ->where('auth_provider', 'password')
            ->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
