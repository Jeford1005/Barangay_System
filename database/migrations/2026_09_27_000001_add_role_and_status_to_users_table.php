<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // role: admin | staff | resident — only admins grant admin/staff.
            $table->string('role', 20)->default('resident');
            // status: pending | active | rejected | suspended
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('suspended_at')->nullable();
            $table->string('suspension_reason', 255)->nullable();

            $table->index(['role', 'status']);
        });

        // Anyone already in the database predates the approval flow, so they
        // are grandfathered in as active. The seeded administrator keeps admin.
        DB::table('users')->whereNull('approved_at')->update([
            'role' => DB::raw("CASE WHEN email = 'admin@barangay.local' THEN 'admin' ELSE 'staff' END"),
            'status' => 'active',
            'approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['role', 'status']);
            $table->dropColumn(['role', 'status', 'approved_at', 'suspended_at', 'suspension_reason']);
        });
    }
};
