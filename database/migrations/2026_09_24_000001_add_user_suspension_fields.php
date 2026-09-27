<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('status');
            $table->foreignId('suspended_by')
                ->nullable()
                ->after('suspended_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('suspended_by');
            $table->index(['user_type', 'suspended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropIndex(['user_type', 'suspended_at']);
            $table->dropColumn(['suspended_at', 'suspended_by', 'suspension_reason']);
        });
    }
};
