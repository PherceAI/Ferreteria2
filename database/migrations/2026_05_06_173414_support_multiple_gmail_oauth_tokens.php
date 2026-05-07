<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pherce_intel.gmail_oauth_tokens', function (Blueprint $table) {
            $table->string('email', 255)->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('token_type');
            $table->timestamp('connected_at')->nullable()->after('expires_at');
            $table->timestamp('last_used_at')->nullable()->after('connected_at');
        });

        DB::table('pherce_intel.gmail_oauth_tokens')
            ->whereNull('connected_at')
            ->update(['connected_at' => now()]);

        Schema::table('pherce_intel.gmail_oauth_tokens', function (Blueprint $table) {
            $table->unique('email');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('pherce_intel.gmail_oauth_tokens', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['email', 'is_active', 'connected_at', 'last_used_at']);
        });
    }
};
