<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The resident asks for a number of copies when submitting a request
        // (the office may still print a different number when issuing).
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->unsignedTinyInteger('copies')->default(1)->after('purpose');
        });

        // Why an administrator rejected an account — shown to the applicant
        // on the sign-in page instead of a bare "rejected" notice.
        Schema::table('users', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('suspension_reason');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->dropColumn('copies');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
