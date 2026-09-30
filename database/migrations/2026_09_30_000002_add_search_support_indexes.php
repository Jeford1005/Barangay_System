<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Plain single-column indexes for the remaining heavily-searched columns.
// All names are explicit and unique across the schema; every index is a
// plain (non-partial, non-expression) btree so it runs on both MySQL and
// SQLite. Columns already covered by an earlier index — including via a
// composite leftmost prefix (e.g. welfare.status via status+request_date,
// users.name/email via users_name_search_idx) — are intentionally skipped.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blotter', function (Blueprint $table) {
            $table->index('complainant_name', 'blotter_complainant_name_idx');
            $table->index('accused_name', 'blotter_accused_name_idx');
            $table->index('complaint_type', 'blotter_complaint_type_idx');
        });

        Schema::table('welfare', function (Blueprint $table) {
            $table->index('beneficiary_name', 'welfare_beneficiary_name_idx');
        });

        Schema::table('households', function (Blueprint $table) {
            $table->index('street', 'households_street_idx');
            $table->index('barangay', 'households_barangay_idx');
        });

        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->index('purpose', 'cert_issuances_purpose_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index('title', 'documents_title_idx');
        });

        Schema::table('puroks', function (Blueprint $table) {
            $table->index('code', 'puroks_code_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('actor_email', 'audit_logs_actor_email_idx');
            $table->index('subject_label', 'audit_logs_subject_label_idx');
            $table->index('ip_address', 'audit_logs_ip_address_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_ip_address_idx');
            $table->dropIndex('audit_logs_subject_label_idx');
            $table->dropIndex('audit_logs_actor_email_idx');
        });

        Schema::table('puroks', function (Blueprint $table) {
            $table->dropIndex('puroks_code_idx');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('documents_title_idx');
        });

        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->dropIndex('cert_issuances_purpose_idx');
        });

        Schema::table('households', function (Blueprint $table) {
            $table->dropIndex('households_barangay_idx');
            $table->dropIndex('households_street_idx');
        });

        Schema::table('welfare', function (Blueprint $table) {
            $table->dropIndex('welfare_beneficiary_name_idx');
        });

        Schema::table('blotter', function (Blueprint $table) {
            $table->dropIndex('blotter_complaint_type_idx');
            $table->dropIndex('blotter_accused_name_idx');
            $table->dropIndex('blotter_complainant_name_idx');
        });
    }
};
