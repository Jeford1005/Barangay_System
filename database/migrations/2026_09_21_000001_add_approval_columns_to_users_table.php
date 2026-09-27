<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('approved')->after('user_type');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('reviewed_by');

            $table->index(['status', 'user_type']);
        });

        // Self-registered users start as pending; everyone created before this
        // feature (admin accounts, seeded staff) stays approved.
        DB::table('users')
            ->where('user_type', 'resident')
            ->whereNull('approved_at')
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['status', 'user_type']);
            $table->dropColumn(['status', 'approved_at', 'reviewed_by', 'rejection_reason']);
        });
    }
};
