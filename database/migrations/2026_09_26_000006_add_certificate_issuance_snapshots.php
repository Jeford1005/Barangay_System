<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->json('recipient_snapshot')->nullable()->after('resident_id');
            $table->json('document_snapshot')->nullable()->after('document_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_issuances', function (Blueprint $table) {
            $table->dropColumn(['recipient_snapshot', 'document_snapshot']);
        });
    }
};
