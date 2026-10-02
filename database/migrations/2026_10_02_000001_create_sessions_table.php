<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The sessions table backs SESSION_DRIVER=database, the shipped
     * default. File/array state is per-instance, so any multi-instance
     * (or ephemeral-filesystem) host loses logins on the next request;
     * the database keeps the session reachable from every instance.
     */
    public function up(): void
    {
        // Databases created before this migration may already carry a
        // sessions table made out-of-band (e.g. this dev database did).
        // Creating it again would abort the whole migrate run, so skip
        // when it exists; the driver only needs the standard columns.
        if (Schema::hasTable('sessions')) {
            return;
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
