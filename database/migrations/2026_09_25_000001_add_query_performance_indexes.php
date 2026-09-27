<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('deleted_at');
            $table->index(['first_name', 'last_name', 'birth_date']);
        });

        Schema::table('households', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::table('blotter', function (Blueprint $table) {
            $table->index('deleted_at');
        });

        Schema::table('welfare', function (Blueprint $table) {
            $table->index('request_date');
            $table->index(['status', 'request_date']);
            $table->index(['assistance_type', 'request_date']);
        });

        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->index(['resident_id', 'document_id', 'status']);
        });

        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->index(['status', 'id']);
            $table->index('created_at');
            $table->index('deleted_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['event', 'occurred_at']);
            $table->dropIndex(['user_id', 'occurred_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['status', 'id']);
        });

        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropIndex(['resident_id', 'document_id', 'status']);
        });

        Schema::table('welfare', function (Blueprint $table) {
            $table->dropIndex(['assistance_type', 'request_date']);
            $table->dropIndex(['status', 'request_date']);
            $table->dropIndex(['request_date']);
        });

        Schema::table('blotter', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex(['deleted_at']);
        });

        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex(['first_name', 'last_name', 'birth_date']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['user_id']);
        });
    }
};
