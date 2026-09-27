<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Allow the full rejection reason the spec asks for (up to 1000 chars). */
    public function up(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requests', function (Blueprint $table) {
            $table->string('rejection_reason', 255)->nullable()->change();
        });
    }
};
