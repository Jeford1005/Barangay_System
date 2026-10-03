<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cleanup_drives', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('purok_id')->nullable();
            $table->dateTime('scheduled_at');
            // Enum-ish string (not a hard enum): the workflow is guarded in
            // application code (CleanupDrive::TRANSITIONS) so future statuses
            // never need a schema change.
            $table->string('status', 20)->default('Scheduled');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('purok_id')->references('id')->on('puroks')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->index('purok_id');
            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleanup_drives');
    }
};
