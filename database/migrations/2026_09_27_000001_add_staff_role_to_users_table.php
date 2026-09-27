<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('user_type', ['admin', 'resident', 'staff'])
                ->default('admin')
                ->change();
        });
    }

    public function down(): void
    {
        // Do not silently demote staff accounts if this migration is rolled back.
        if (DB::table('users')->where('user_type', 'staff')->exists()) {
            throw new RuntimeException('Cannot remove the staff role while staff accounts exist.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('user_type', ['admin', 'resident'])
                ->default('admin')
                ->change();
        });
    }
};
