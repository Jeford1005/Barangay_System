<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Lost-card bridge for office 2FA: when true, the login challenge
            // sends a one-time email code instead of asking for TOTP or a
            // login-card code. Toggled per user by an administrator; the
            // password-reset code store is never reused for this.
            $table->boolean('email_otp_fallback')->default(false)->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_otp_fallback');
        });
    }
};
