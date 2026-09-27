<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resident self-registration submits the full resident record as an
 * application; the barangay office reviews it and converts it into a
 * resident profile (AccountController::createResidentFromApplication).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix')->nullable();
            $table->date('birth_date');
            $table->string('sex');
            $table->string('civil_status');
            $table->string('phone_number', 30)->nullable();
            $table->string('email');
            $table->string('address');
            $table->foreignId('purok_id')->nullable()->constrained('puroks')->nullOnDelete();
            $table->foreignId('household_id')->nullable()->constrained('households')->nullOnDelete();
            // string rather than an enum so the set can evolve without a table rebuild
            $table->string('status', 20)->default('Pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_applications');
    }
};
