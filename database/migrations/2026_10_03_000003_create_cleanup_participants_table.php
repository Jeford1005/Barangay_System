<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cleanup_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('drive_id');
            $table->unsignedBigInteger('resident_id');
            $table->boolean('attended')->default(false);
            $table->decimal('hours', 4, 1)->nullable();
            $table->dateTime('checked_in_at')->nullable();
            $table->timestamps();

            $table->foreign('drive_id')->references('id')->on('cleanup_drives')->onDelete('cascade');
            $table->foreign('resident_id')->references('id')->on('residents')->onDelete('cascade');

            // A resident signs up for a drive at most once. This index is
            // the final guard against double sign-ups: the join path
            // catches its violation into a friendly validation error.
            $table->unique(['drive_id', 'resident_id']);

            $table->index('drive_id');
            $table->index('resident_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleanup_participants');
    }
};
