<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bridge from an account to the resident record it represents
        // (residents also carry an email so accounts can auto-match).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('resident_id')
                ->nullable()
                ->after('reviewed_by')
                ->constrained('residents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['resident_id']);
        });
    }
};
