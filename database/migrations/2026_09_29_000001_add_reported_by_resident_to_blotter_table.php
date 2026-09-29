<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Marks blotter cases filed by a resident through the portal, so the
     * office can tell a self-reported incident from one taken at the desk.
     * Existing rows stay false: they were all written by office staff.
     */
    public function up(): void
    {
        Schema::table('blotter', function (Blueprint $table) {
            $table->boolean('reported_by_resident')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('blotter', function (Blueprint $table) {
            $table->dropColumn('reported_by_resident');
        });
    }
};
