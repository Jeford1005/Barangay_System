<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->index(['last_name', 'first_name'], 'residents_name_search_idx');
        });
        Schema::table('households', function (Blueprint $table) {
            $table->index('household_code', 'households_code_search_idx');
        });
        Schema::table('blotter', function (Blueprint $table) {
            $table->index('case_number', 'blotter_case_search_idx');
        });
        Schema::table('welfare', function (Blueprint $table) {
            $table->index('program_name', 'welfare_program_search_idx');
        });
        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->index('control_number', 'certificates_control_search_idx');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->index(['name', 'email'], 'users_name_search_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_name_search_idx'));
        Schema::table('certificate_issuances', fn (Blueprint $table) => $table->dropIndex('certificates_control_search_idx'));
        Schema::table('welfare', fn (Blueprint $table) => $table->dropIndex('welfare_program_search_idx'));
        Schema::table('blotter', fn (Blueprint $table) => $table->dropIndex('blotter_case_search_idx'));
        Schema::table('households', fn (Blueprint $table) => $table->dropIndex('households_code_search_idx'));
        Schema::table('residents', fn (Blueprint $table) => $table->dropIndex('residents_name_search_idx'));
    }
};
